<?php

namespace App\Services;

use App\Models\Dodavatel;
use App\Models\Doklad;
use App\Models\Firma;
use Aws\Textract\TextractClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DokladProcessor
{
    /**
     * Zpracuje soubor dokladu - OCR + AI extrakce + upload na S3 + uložení do DB.
     *
     * S3 cesta: doklady/{ICO}/{YYYY-MM}/{YYYY-MM-DD}_{ID}.{ext}
     * Upload probíhá AŽ po AI extrakci (potřebujeme datum_vystaveni pro cestu).
     *
     * @param string $filePath Absolutní cesta k souboru
     * @param string $originalName Původní název souboru
     * @param Firma $firma Firma, ke které doklad patří
     * @param string $fileHash SHA-256 hash souboru
     * @param string $zdroj Zdroj dokladu (upload, email)
     * @return Doklad
     */
    public function process(
        string $filePath,
        string $originalName,
        Firma $firma,
        string $fileHash,
        string $zdroj = 'upload'
    ): Doklad {
        $doklad = Doklad::create([
            'firma_ico' => $firma->ico,
            'nazev_souboru' => $originalName,
            'cesta_souboru' => '',
            'hash_souboru' => $fileHash,
            'stav' => 'zpracovava_se',
            'zdroj' => $zdroj,
        ]);

        try {
            // 0. Okamžitý upload na S3 (temp cesta) - soubor bude dostupný i při selhání OCR
            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION)) ?: 'pdf';
            $tempS3Path = "doklady/{$firma->ico}/_tmp/{$doklad->id}.{$ext}";
            Storage::disk('s3')->put($tempS3Path, file_get_contents($filePath));
            $doklad->update(['cesta_souboru' => $tempS3Path]);

            // 1. Textract OCR (s Claude Vision fallback)
            $extractedText = $this->runOcr($filePath);
            $doklad->update(['raw_text' => $extractedText]);

            // 2. Claude Haiku - strukturování dat
            $invoiceData = $this->extractInvoiceData($extractedText, $firma);

            if (!empty($invoiceData['_error'])) {
                $doklad->update([
                    'stav' => 'chyba',
                    'chybova_zprava' => $invoiceData['_error'],
                ]);
                return $doklad;
            }

            // 3. Detekce obsahové duplicity (stejné číslo dokladu + dodavatel)
            $duplicitaId = null;
            $cisloDokladu = $invoiceData['cislo_faktury'] ?? null;
            $dodavatelIcoRaw = $invoiceData['ico'] ?? null;
            if ($cisloDokladu && $dodavatelIcoRaw) {
                $existujici = Doklad::where('firma_ico', $firma->ico)
                    ->where('cislo_dokladu', $cisloDokladu)
                    ->where('dodavatel_ico', $dodavatelIcoRaw)
                    ->where('id', '!=', $doklad->id)
                    ->first();
                if ($existujici) {
                    $duplicitaId = $existujici->id;
                }
            }

            // 4. Přesunout z temp na finální S3 cestu (datum_vystaveni + ID)
            $s3Path = $this->buildS3Path($firma->ico, $doklad->id, $originalName, $invoiceData['datum_vystaveni'] ?? null);
            if ($s3Path !== $tempS3Path) {
                Storage::disk('s3')->move($tempS3Path, $s3Path);
            }
            $doklad->update(['cesta_souboru' => $s3Path]);

            // 5. Auto-create/update dodavatel
            $dodavatelIco = $dodavatelIcoRaw;
            if ($dodavatelIco) {
                Dodavatel::updateOrCreate(
                    ['ico' => $dodavatelIco],
                    [
                        'nazev' => $invoiceData['dodavatel'] ?? 'Neznámý',
                        'dic' => $invoiceData['dic'] ?? null,
                    ]
                );
            }

            // 6. Ověření adresáta
            $adresni = !empty($dodavatelIco);
            $overenoAdresat = false;
            if ($adresni) {
                $odberatelIco = $invoiceData['odberatel_ico'] ?? null;
                $overenoAdresat = $odberatelIco === $firma->ico;
            }

            // 7. Uložit do DB
            $doklad->update([
                'dodavatel_ico' => $dodavatelIco,
                'dodavatel_nazev' => $invoiceData['dodavatel'] ?? null,
                'cislo_dokladu' => $cisloDokladu,
                'duplicita_id' => $duplicitaId,
                'datum_vystaveni' => $invoiceData['datum_vystaveni'] ?? null,
                'datum_prijeti' => now()->toDateString(),
                'duzp' => $invoiceData['duzp'] ?? $invoiceData['datum_vystaveni'] ?? null,
                'datum_splatnosti' => $invoiceData['datum_splatnosti'] ?? null,
                'castka_celkem' => $invoiceData['castka_celkem'] ?? null,
                'mena' => $invoiceData['mena'] ?? 'CZK',
                'castka_dph' => $invoiceData['castka_dph'] ?? null,
                'kategorie' => $invoiceData['kategorie'] ?? null,
                'adresni' => $adresni,
                'overeno_adresat' => $overenoAdresat,
                'raw_ai_odpoved' => json_encode($invoiceData, JSON_UNESCAPED_UNICODE),
                'stav' => 'dokonceno',
            ]);
        } catch (\Exception $e) {
            Log::error("DokladProcessor error: {$e->getMessage()}", [
                'doklad_id' => $doklad->id,
                'firma_ico' => $firma->ico,
            ]);
            $doklad->update([
                'stav' => 'chyba',
                'chybova_zprava' => $e->getMessage(),
            ]);
        }

        return $doklad->fresh();
    }

    /**
     * Sestaví S3 cestu: doklady/{ICO}/{YYYY-MM}/{YYYY-MM-DD}_{ID}.{ext}
     */
    private function buildS3Path(string $ico, int $dokladId, string $originalName, ?string $datumVystaveni): string
    {
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION)) ?: 'pdf';
        $datum = $datumVystaveni ?: date('Y-m-d');
        $mesic = substr($datum, 0, 7); // YYYY-MM

        return "doklady/{$ico}/{$mesic}/{$datum}_{$dokladId}.{$ext}";
    }

    /**
     * Zkontroluje, zda soubor s daným hashem už existuje pro firmu.
     */
    public function isDuplicate(string $fileHash, string $firmaIco): ?Doklad
    {
        return Doklad::where('firma_ico', $firmaIco)
            ->where('hash_souboru', $fileHash)
            ->first();
    }

    private function runOcr(string $filePath): string
    {
        try {
            $client = new TextractClient([
                'region' => config('services.aws.region', 'eu-central-1'),
                'version' => 'latest',
                'credentials' => [
                    'key' => config('services.aws.key'),
                    'secret' => config('services.aws.secret'),
                ],
            ]);

            $fileBytes = file_get_contents($filePath);
            $result = $client->detectDocumentText([
                'Document' => ['Bytes' => $fileBytes],
            ]);

            $lines = [];
            foreach ($result['Blocks'] as $block) {
                if ($block['BlockType'] === 'LINE') {
                    $lines[] = $block['Text'];
                }
            }

            return implode("\n", $lines);
        } catch (\Exception $e) {
            Log::warning("Textract failed, falling back to Claude Vision: {$e->getMessage()}", [
                'file' => basename($filePath),
            ]);
            return $this->runVisionOcr($filePath);
        }
    }

    /**
     * Fallback OCR: pošle soubor přímo do Claude Vision API jako obrázek/PDF.
     * Použije se, když Textract nedokáže zpracovat soubor (UnsupportedDocumentException apod.).
     */
    private function runVisionOcr(string $filePath): string
    {
        $apiKey = config('services.anthropic.key');
        if (empty($apiKey)) {
            throw new \RuntimeException('Anthropic API klíč není nastaven - nelze použít Vision OCR fallback.');
        }

        $fileBytes = file_get_contents($filePath);
        $base64 = base64_encode($fileBytes);
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        // Detekce PDF podle obsahu souboru (magic bytes), ne jen podle přípony
        // PHP upload temp soubory nemají příponu, proto kontrolujeme obsah
        $isPdf = $ext === 'pdf' || str_starts_with($fileBytes, '%PDF');

        $mediaTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
        ];
        $mediaType = $isPdf ? 'application/pdf' : ($mediaTypes[$ext] ?? 'image/jpeg');

        // PDF se posílá jako document, obrázky jako image
        if ($isPdf) {
            $contentBlock = [
                'type' => 'document',
                'source' => [
                    'type' => 'base64',
                    'media_type' => $mediaType,
                    'data' => $base64,
                ],
            ];
        } else {
            $contentBlock = [
                'type' => 'image',
                'source' => [
                    'type' => 'base64',
                    'media_type' => $mediaType,
                    'data' => $base64,
                ],
            ];
        }

        $response = Http::timeout(120)->withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->post('https://api.anthropic.com/v1/messages', [
            'model' => 'claude-haiku-4-5-20251001',
            'max_tokens' => 4096,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        $contentBlock,
                        [
                            'type' => 'text',
                            'text' => 'Přepiš veškerý text z tohoto dokumentu/obrázku. Zachovej rozložení řádků. Vrať POUZE přepsaný text, žádné komentáře ani formátování.',
                        ],
                    ],
                ],
            ],
        ]);

        if ($response->failed()) {
            throw new \RuntimeException('Claude Vision OCR selhalo: ' . $response->body());
        }

        $body = $response->json();
        return $body['content'][0]['text'] ?? '';
    }

    private function extractInvoiceData(string $text, Firma $firma): array
    {
        $apiKey = config('services.anthropic.key');

        if (empty($apiKey)) {
            return ['_error' => 'Anthropic API klíč není nastaven v .env'];
        }

        $firmaInfo = "IČO odběratele (naše firma): {$firma->ico}, DIČ: {$firma->dic}";

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->post('https://api.anthropic.com/v1/messages', [
            'model' => 'claude-haiku-4-5-20251001',
            'max_tokens' => 1024,
            'messages' => [
                ['role' => 'user', 'content' => $text],
            ],
            'system' => "Jsi asistent pro zpracování českých faktur. Z textu faktury extrahuj strukturovaná data a vrať POUZE validní JSON objekt (žádný další text). {$firmaInfo}
Použij tyto klíče:
{
  \"dodavatel\": \"název dodavatele/firmy která fakturu VYSTAVILA\",
  \"ico\": \"IČO dodavatele (vystavitele)\",
  \"dic\": \"DIČ dodavatele (vystavitele)\",
  \"odberatel_ico\": \"IČO odběratele (komu je faktura adresována)\",
  \"cislo_faktury\": \"číslo faktury/dokladu\",
  \"datum_vystaveni\": \"datum vystavení ve formátu YYYY-MM-DD\",
  \"duzp\": \"datum uskutečnění zdanitelného plnění (DUZP) ve formátu YYYY-MM-DD, pokud není uvedeno vrať null\",
  \"datum_splatnosti\": \"datum splatnosti ve formátu YYYY-MM-DD\",
  \"castka_celkem\": 0.00,
  \"mena\": \"CZK\",
  \"castka_dph\": 0.00,
  \"kategorie\": \"jedna z: služby, materiál, energie, telekomunikace, nájem, pojištění, ostatní\"
}
Pokud některý údaj nelze z textu zjistit, použij null. U neadresních dokladů (paragony, bloky) bude odberatel_ico null.",
        ]);

        if ($response->failed()) {
            return ['_error' => 'Claude API chyba: ' . $response->body()];
        }

        $body = $response->json();
        $content = $body['content'][0]['text'] ?? '';

        if (preg_match('/\{[\s\S]*\}/', $content, $matches)) {
            $parsed = json_decode($matches[0], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $parsed;
            }
        }

        return ['_error' => 'Nepodařilo se parsovat odpověď z Claude: ' . $content];
    }
}
