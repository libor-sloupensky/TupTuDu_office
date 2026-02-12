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
            // 1. Textract OCR
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

            // 3. Upload na S3 s finální cestou (datum_vystaveni + ID)
            $s3Path = $this->buildS3Path($firma->ico, $doklad->id, $originalName, $invoiceData['datum_vystaveni'] ?? null);
            Storage::disk('s3')->put($s3Path, file_get_contents($filePath));
            $doklad->update(['cesta_souboru' => $s3Path]);

            // 4. Auto-create/update dodavatel
            $dodavatelIco = $invoiceData['ico'] ?? null;
            if ($dodavatelIco) {
                Dodavatel::updateOrCreate(
                    ['ico' => $dodavatelIco],
                    [
                        'nazev' => $invoiceData['dodavatel'] ?? 'Neznámý',
                        'dic' => $invoiceData['dic'] ?? null,
                    ]
                );
            }

            // 5. Ověření adresáta
            $adresni = !empty($dodavatelIco);
            $overenoAdresat = false;
            if ($adresni) {
                $odberatelIco = $invoiceData['odberatel_ico'] ?? null;
                $overenoAdresat = $odberatelIco === $firma->ico;
            }

            // 6. Uložit do DB
            $doklad->update([
                'dodavatel_ico' => $dodavatelIco,
                'dodavatel_nazev' => $invoiceData['dodavatel'] ?? null,
                'cislo_dokladu' => $invoiceData['cislo_faktury'] ?? null,
                'datum_vystaveni' => $invoiceData['datum_vystaveni'] ?? null,
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
