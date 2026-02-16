<?php

namespace App\Services;

use App\Models\Dodavatel;
use App\Models\Doklad;
use App\Models\Firma;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;

class DokladProcessor
{
    /**
     * Zpracuje soubor dokladu - rozdělí PDF na stránky, Claude Vision AI + upload na S3 + uložení do DB.
     * Vrací pole Doklad objektů (může být víc dokladů z jednoho souboru).
     *
     * @return Doklad[]
     */
    private function logFailedFile(string $filename, string $firmaIco, string $error, string $fileBytes): void
    {
        $dir = storage_path('logs/failed_uploads');
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        // Save a copy of the failed file for later analysis
        $safeFilename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        $timestamp = date('Ymd_His');
        @file_put_contents("{$dir}/{$timestamp}_{$safeFilename}", $fileBytes);

        // Append to daily error log
        $entry = date('H:i:s') . " | {$firmaIco} | {$filename} | {$error}\n";
        @file_put_contents("{$dir}/" . date('Y-m-d') . '_errors.log', $entry, FILE_APPEND);
    }

    public function process(
        string $filePath,
        string $originalName,
        Firma $firma,
        string $fileHash,
        string $zdroj = 'upload'
    ): array {
        $fileBytes = file_get_contents($filePath);
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION)) ?: 'pdf';
        $isPdf = $ext === 'pdf' || str_starts_with($fileBytes, '%PDF');

        // Rozděl multi-page PDF na jednotlivé stránky
        if ($isPdf) {
            $pages = $this->splitPdfPages($fileBytes);
        } else {
            $pages = null; // obrázky se nerozdělují
        }

        // Multi-page PDF: zpracuj každou stránku zvlášť
        if ($pages !== null && count($pages) > 1) {
            return $this->processMultiPagePdf($pages, $originalName, $firma, $fileHash, $zdroj, $fileBytes);
        }

        // Jednoduchý soubor (1 stránka PDF nebo obrázek)
        return $this->processSingleFile($fileBytes, $ext, $originalName, $firma, $fileHash, $zdroj);
    }

    /**
     * Zpracuje multi-page PDF - každá stránka se posílá do AI zvlášť.
     *
     * @return Doklad[]
     */
    private function processMultiPagePdf(
        array $pages,
        string $originalName,
        Firma $firma,
        string $fileHash,
        string $zdroj,
        string $originalFileBytes
    ): array {
        $doklady = [];
        $globalIndex = 0;
        $baseName = pathinfo($originalName, PATHINFO_FILENAME);

        foreach ($pages as $pageNum => $pageBytes) {
            // Upload stránky na S3 jako temp
            $pageTempPath = "doklady/{$firma->ico}/_tmp/" . time() . "_{$fileHash}_p{$pageNum}.pdf";
            Storage::disk('s3')->put($pageTempPath, $pageBytes);

            try {
                $visionResult = $this->analyzeWithVision($pageBytes, 'pdf', $firma);
            } catch (\Exception $e) {
                Log::error("DokladProcessor Vision error page {$pageNum}: {$e->getMessage()}", [
                    'firma_ico' => $firma->ico,
                    'file' => $originalName,
                    'page' => $pageNum,
                ]);

                $this->logFailedFile("{$baseName}_p{$pageNum}.pdf", $firma->ico, $e->getMessage(), $pageBytes);

                $doklad = Doklad::create([
                    'firma_ico' => $firma->ico,
                    'nazev_souboru' => "{$baseName} (s{$pageNum}).pdf",
                    'cesta_souboru' => $pageTempPath,
                    'hash_souboru' => $fileHash,
                    'stav' => 'chyba',
                    'chybova_zprava' => "Chyba AI zpracování stránky {$pageNum}: " . $e->getMessage(),
                    'zdroj' => $zdroj,
                    'poradi_v_souboru' => ++$globalIndex,
                ]);
                $doklady[] = $doklad;
                continue;
            }

            $documents = $visionResult['dokumenty'] ?? [];

            if (empty($documents)) {
                // Stránka neobsahuje žádný rozpoznaný doklad - smazat temp
                try { Storage::disk('s3')->delete($pageTempPath); } catch (\Exception $e) {}
                continue;
            }

            $docsOnPage = count($documents);

            foreach ($documents as $docIdx => $docData) {
                $globalIndex++;
                $docNum = $docIdx + 1;

                // Název: "Epson_15012024 (s1-2).pdf" = stránka 1, doklad 2
                $nazev = $docsOnPage > 1
                    ? "{$baseName} (s{$pageNum}-{$docNum}).pdf"
                    : "{$baseName} (s{$pageNum}).pdf";

                try {
                    $doklad = $this->createDokladFromPage(
                        $docData, $firma, $nazev, $fileHash,
                        $zdroj, $pageTempPath, $pageBytes, $globalIndex
                    );
                    $doklady[] = $doklad;
                } catch (\Exception $e) {
                    Log::error("DokladProcessor create error: {$e->getMessage()}", [
                        'firma_ico' => $firma->ico,
                        'file' => $originalName,
                        'page' => $pageNum,
                        'doc' => $docNum,
                    ]);

                    $doklad = Doklad::create([
                        'firma_ico' => $firma->ico,
                        'nazev_souboru' => $nazev,
                        'cesta_souboru' => $pageTempPath,
                        'hash_souboru' => $fileHash,
                        'stav' => 'chyba',
                        'chybova_zprava' => $e->getMessage(),
                        'zdroj' => $zdroj,
                        'poradi_v_souboru' => $globalIndex,
                    ]);
                    $doklady[] = $doklad;
                }
            }

            // Smazat temp stránku (každý doklad má svoji S3 kopii)
            try { Storage::disk('s3')->delete($pageTempPath); } catch (\Exception $e) {}
        }

        if (empty($doklady)) {
            // Žádný doklad nenalezen na žádné stránce
            $tempPath = "doklady/{$firma->ico}/_tmp/" . time() . "_{$fileHash}.pdf";
            Storage::disk('s3')->put($tempPath, $originalFileBytes);
            $doklad = Doklad::create([
                'firma_ico' => $firma->ico,
                'nazev_souboru' => $originalName,
                'cesta_souboru' => $tempPath,
                'hash_souboru' => $fileHash,
                'stav' => 'chyba',
                'chybova_zprava' => 'AI nerozpoznalo žádný doklad v souboru (' . count($pages) . ' stránek).',
                'zdroj' => $zdroj,
            ]);
            $doklady[] = $doklad;
        }

        return $doklady;
    }

    /**
     * Zpracuje jednoduchý soubor (1 stránka PDF nebo obrázek).
     *
     * @return Doklad[]
     */
    private function processSingleFile(
        string $fileBytes,
        string $ext,
        string $originalName,
        Firma $firma,
        string $fileHash,
        string $zdroj
    ): array {
        $tempS3Path = "doklady/{$firma->ico}/_tmp/" . time() . "_{$fileHash}.{$ext}";
        Storage::disk('s3')->put($tempS3Path, $fileBytes);

        try {
            $visionResult = $this->analyzeWithVision($fileBytes, $ext, $firma);
        } catch (\Exception $e) {
            Log::error("DokladProcessor Vision error: {$e->getMessage()}", [
                'firma_ico' => $firma->ico,
                'file' => $originalName,
            ]);

            $this->logFailedFile($originalName, $firma->ico, $e->getMessage(), $fileBytes);

            $doklad = Doklad::create([
                'firma_ico' => $firma->ico,
                'nazev_souboru' => $originalName,
                'cesta_souboru' => $tempS3Path,
                'hash_souboru' => $fileHash,
                'stav' => 'chyba',
                'chybova_zprava' => 'Chyba AI zpracování: ' . $e->getMessage(),
                'zdroj' => $zdroj,
            ]);
            return [$doklad];
        }

        $documents = $visionResult['dokumenty'] ?? [];

        if (empty($documents)) {
            $doklad = Doklad::create([
                'firma_ico' => $firma->ico,
                'nazev_souboru' => $originalName,
                'cesta_souboru' => $tempS3Path,
                'hash_souboru' => $fileHash,
                'stav' => 'chyba',
                'chybova_zprava' => 'AI nerozpoznalo žádný doklad v souboru.',
                'raw_ai_odpoved' => json_encode($visionResult, JSON_UNESCAPED_UNICODE),
                'zdroj' => $zdroj,
            ]);
            return [$doklad];
        }

        $doklady = [];
        $celkem = count($documents);

        foreach ($documents as $index => $docData) {
            $poradi = $index + 1;
            $nazev = $celkem > 1
                ? pathinfo($originalName, PATHINFO_FILENAME) . " ({$poradi})." . $ext
                : $originalName;

            try {
                $doklad = $this->createDokladFromPage(
                    $docData, $firma, $nazev, $fileHash,
                    $zdroj, $tempS3Path, $fileBytes, $poradi
                );
                $doklady[] = $doklad;
            } catch (\Exception $e) {
                Log::error("DokladProcessor create error: {$e->getMessage()}", [
                    'firma_ico' => $firma->ico,
                    'file' => $originalName,
                    'poradi' => $poradi,
                ]);

                $doklad = Doklad::create([
                    'firma_ico' => $firma->ico,
                    'nazev_souboru' => $originalName,
                    'cesta_souboru' => $tempS3Path,
                    'hash_souboru' => $fileHash,
                    'stav' => 'chyba',
                    'chybova_zprava' => $e->getMessage(),
                    'zdroj' => $zdroj,
                    'poradi_v_souboru' => $poradi,
                ]);
                $doklady[] = $doklad;
            }
        }

        // Multi-doc na jedné stránce: smazat temp (každý doklad má svou S3 kopii)
        if ($celkem > 1) {
            try { Storage::disk('s3')->delete($tempS3Path); } catch (\Exception $e) {}
        }

        return $doklady;
    }

    /**
     * Vytvoří Doklad záznam z dat extrahovaných Claude Vision.
     * Každý doklad dostane vlastní soubor na S3 (kopii stránky/souboru).
     */
    private function createDokladFromPage(
        array $docData,
        Firma $firma,
        string $nazev,
        string $fileHash,
        string $zdroj,
        string $tempS3Path,
        string $pageBytes,
        int $poradi
    ): Doklad {
        $kvalita = $docData['kvalita'] ?? 'dobra';
        $typDokladu = $docData['typ_dokladu'] ?? 'faktura';

        // Stav podle kvality
        $stav = match ($kvalita) {
            'necitelna' => 'nekvalitni',
            'nizka' => 'nekvalitni',
            default => 'dokonceno',
        };

        $ext = strtolower(pathinfo($nazev, PATHINFO_EXTENSION)) ?: 'pdf';

        $doklad = Doklad::create([
            'firma_ico' => $firma->ico,
            'nazev_souboru' => $nazev,
            'cesta_souboru' => $tempS3Path,
            'hash_souboru' => $fileHash,
            'stav' => 'zpracovava_se',
            'zdroj' => $zdroj,
            'typ_dokladu' => $typDokladu,
            'kvalita' => $kvalita,
            'kvalita_poznamka' => $docData['kvalita_poznamka'] ?? null,
            'poradi_v_souboru' => $poradi,
            'raw_text' => $docData['raw_text'] ?? null,
            'raw_ai_odpoved' => json_encode($docData, JSON_UNESCAPED_UNICODE),
        ]);

        // S3 finální cesta - vlastní kopie pro tento doklad
        $s3Path = $this->buildS3Path($firma->ico, $doklad->id, $nazev, $docData['datum_vystaveni'] ?? null);
        Storage::disk('s3')->put($s3Path, $pageBytes);

        // Detekce obsahové duplicity (stejné číslo dokladu + dodavatel)
        $duplicitaId = null;
        $cisloDokladu = $docData['cislo_dokladu'] ?? null;
        $dodavatelIco = $docData['dodavatel_ico'] ?? null;

        if ($cisloDokladu && $dodavatelIco) {
            $existujici = Doklad::where('firma_ico', $firma->ico)
                ->where('cislo_dokladu', $cisloDokladu)
                ->where('dodavatel_ico', $dodavatelIco)
                ->where('id', '!=', $doklad->id)
                ->first();
            if ($existujici) {
                $duplicitaId = $existujici->id;
                if (!$existujici->duplicita_id) {
                    $existujici->update(['duplicita_id' => $doklad->id]);
                }
            }
        }

        // Auto-create/update dodavatel
        if ($dodavatelIco) {
            Dodavatel::updateOrCreate(
                ['ico' => $dodavatelIco],
                [
                    'nazev' => $docData['dodavatel_nazev'] ?? 'Neznámý',
                    'dic' => $docData['dodavatel_dic'] ?? null,
                ]
            );
        }

        // Ověření adresáta
        $adresni = !empty($dodavatelIco);
        $overenoAdresat = false;
        if ($adresni) {
            $odberatelIco = $docData['odberatel_ico'] ?? null;
            $overenoAdresat = $odberatelIco === $firma->ico;
        }

        $doklad->update([
            'dodavatel_ico' => $dodavatelIco,
            'dodavatel_nazev' => $docData['dodavatel_nazev'] ?? null,
            'cislo_dokladu' => $cisloDokladu,
            'duplicita_id' => $duplicitaId,
            'datum_vystaveni' => $docData['datum_vystaveni'] ?? null,
            'datum_prijeti' => now()->toDateString(),
            'duzp' => $docData['duzp'] ?? $docData['datum_vystaveni'] ?? null,
            'datum_splatnosti' => $docData['datum_splatnosti'] ?? null,
            'castka_celkem' => $docData['castka_celkem'] ?? null,
            'mena' => $docData['mena'] ?? 'CZK',
            'castka_dph' => $docData['castka_dph'] ?? null,
            'kategorie' => $docData['kategorie'] ?? null,
            'adresni' => $adresni,
            'overeno_adresat' => $overenoAdresat,
            'cesta_souboru' => $s3Path,
            'stav' => $stav,
        ]);

        return $doklad->fresh();
    }

    /**
     * Rozdělí multi-page PDF na jednotlivé stránky pomocí FPDI.
     * Vrací pole [pageNum => pageBytes] nebo null pokud není PDF / má 1 stránku.
     *
     * @return array<int, string>|null
     */
    private function splitPdfPages(string $pdfBytes): ?array
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'pdf_');
        file_put_contents($tmpFile, $pdfBytes);

        try {
            $fpdi = new Fpdi();
            $pageCount = $fpdi->setSourceFile($tmpFile);

            if ($pageCount <= 1) {
                return null; // Jednoduchý PDF - není třeba dělit
            }

            $pages = [];
            for ($i = 1; $i <= $pageCount; $i++) {
                $singlePage = new Fpdi();
                $singlePage->setSourceFile($tmpFile);
                $tplId = $singlePage->importPage($i);
                $size = $singlePage->getTemplateSize($tplId);
                $singlePage->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $singlePage->useTemplate($tplId);
                $pages[$i] = $singlePage->Output('S');
            }

            return $pages;
        } catch (\Exception $e) {
            Log::warning("PDF split failed, processing as single file: {$e->getMessage()}");
            return null; // Fallback - zpracuj celý PDF najednou
        } finally {
            @unlink($tmpFile);
        }
    }

    /**
     * Pošle dokument do Claude Vision API - jedno volání, kompletní analýza.
     * OCR + extrakce dat + klasifikace typu + hodnocení kvality.
     */
    private function analyzeWithVision(string $fileBytes, string $ext, Firma $firma): array
    {
        $apiKey = config('services.anthropic.key');
        if (empty($apiKey)) {
            throw new \RuntimeException('Anthropic API klíč není nastaven.');
        }

        $base64 = base64_encode($fileBytes);

        // Detekce PDF podle obsahu (magic bytes) - temp soubory nemají příponu
        $isPdf = $ext === 'pdf' || str_starts_with($fileBytes, '%PDF');

        $mediaTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
        ];
        $mediaType = $isPdf ? 'application/pdf' : ($mediaTypes[$ext] ?? 'image/jpeg');

        $contentBlock = $isPdf
            ? ['type' => 'document', 'source' => ['type' => 'base64', 'media_type' => $mediaType, 'data' => $base64]]
            : ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $mediaType, 'data' => $base64]];

        $systemPrompt = $this->buildSystemPrompt($firma);

        $response = Http::timeout(90)->withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->post('https://api.anthropic.com/v1/messages', [
            'model' => 'claude-haiku-4-5-20251001',
            'max_tokens' => 16384,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        $contentBlock,
                        [
                            'type' => 'text',
                            'text' => 'Analyzuj tento sken. POZOR: stránka může obsahovat VÍCE samostatných dokladů (účtenek/paragonů nalepených na papíře). Každý fyzicky oddělený doklad vrať jako samostatný objekt v poli "dokumenty". Vrať POUZE validní JSON.',
                        ],
                    ],
                ],
            ],
            'system' => $systemPrompt,
        ]);

        if ($response->failed()) {
            throw new \RuntimeException('Claude Vision API chyba (HTTP ' . $response->status() . '): ' . substr($response->body(), 0, 500));
        }

        $body = $response->json();
        $content = $body['content'][0]['text'] ?? '';
        $stopReason = $body['stop_reason'] ?? 'end_turn';

        // Pokud odpověď oříznutá (max_tokens), zkusit opravit neúplný JSON
        if ($stopReason === 'max_tokens') {
            Log::warning('AI odpověď oříznutá (max_tokens), pokus o opravu JSON', [
                'content_length' => strlen($content),
            ]);
            $content = $this->repairTruncatedJson($content);
        }

        // Parse JSON z odpovědi
        if (preg_match('/\{[\s\S]*\}/s', $content, $matches)) {
            $parsed = json_decode($matches[0], true);
            if (json_last_error() === JSON_ERROR_NONE && isset($parsed['dokumenty'])) {
                return $parsed;
            }
            // Pokud Claude vrátil jen jeden dokument bez wrapperu
            if (json_last_error() === JSON_ERROR_NONE && !isset($parsed['dokumenty'])) {
                return ['dokumenty' => [$parsed]];
            }
        }

        throw new \RuntimeException('Nepodařilo se parsovat AI odpověď: ' . substr($content, 0, 300));
    }

    /**
     * Sestaví systémový prompt pro Claude Vision - kompletní instrukce pro analýzu dokladů.
     */
    private function buildSystemPrompt(Firma $firma): string
    {
        $firmaInfo = "IČO odběratele (naše firma): {$firma->ico}";
        if ($firma->dic) {
            $firmaInfo .= ", DIČ: {$firma->dic}";
        }
        if ($firma->nazev) {
            $firmaInfo .= ", Název: {$firma->nazev}";
        }

        $prompt = <<<PROMPT
Jsi expert na zpracování účetních dokladů. Analyzuj přiložený dokument a extrahuj strukturovaná data.

KONTEXT: {$firmaInfo}

POSTUP:
1. NEJDŘÍVE zjisti kolik SAMOSTATNÝCH dokladů je na stránce/obrázku. Pozorně se podívej - naskenovaná stránka A4 často obsahuje 2-4 menší doklady (účtenky, paragony) nalepené nebo položené vedle sebe či nad sebou. Každý fyzicky oddělený doklad = samostatný záznam.
2. Pro KAŽDÝ doklad extrahuj data SAMOSTATNĚ do vlastního objektu v poli "dokumenty"
3. Zhodnoť kvalitu čitelnosti
4. Klasifikuj typ dokladu

TYPY DOKLADŮ:
- faktura: faktura, proforma faktura
- uctenka: pokladní účtenka, paragon, prodejka, zjednodušený daňový doklad
- pokladni_doklad: pokladní příjmový/výdajový doklad
- dobropis: dobropis, kreditní nota
- zalohova_faktura: zálohová faktura
- pokuta: pokuta, výzva k úhradě, penále, přestupek
- jine: výpis, oznámení, dopis, jiný dokument

KVALITA (buď tolerantní — běžné nedokonalosti skenu jsou OK):
- dobra: klíčové údaje (částka, dodavatel, číslo dokladu) jsou čitelné. Mírné rozmazání, šikmý úhel, nízké rozlišení, šum nebo stíny NEJSOU důvodem ke snížení kvality pokud údaje jdou přečíst.
- nizka: některé klíčové údaje jsou těžko čitelné nebo chybí, ale alespoň část dat jde extrahovat. Použij pouze pokud skutečně nemůžeš přečíst důležitá pole.
- necitelna: dokument je zcela nečitelný — nelze přečíst ani částku, ani dodavatele, ani číslo dokladu.

KATEGORIE NÁKLADŮ:
{$this->buildKategoriePrompt($firma)}

FORMÁT ODPOVĚDI - vrať POUZE validní JSON:
{
  "dokumenty": [
    {
      "typ_dokladu": "faktura",
      "kvalita": "dobra",
      "kvalita_poznamka": null,
      "raw_text": "přepis klíčového textu z dokladu",
      "dodavatel_nazev": "název dodavatele/vystavitele",
      "dodavatel_ico": "12345678",
      "dodavatel_dic": "CZ12345678",
      "odberatel_ico": "IČO odběratele",
      "cislo_dokladu": "číslo faktury/dokladu",
      "datum_vystaveni": "YYYY-MM-DD",
      "duzp": "YYYY-MM-DD",
      "datum_splatnosti": "YYYY-MM-DD",
      "castka_celkem": 0.00,
      "mena": "CZK",
      "castka_dph": 0.00,
      "kategorie": "služby"
    }
  ]
}

DŮLEŽITÁ PRAVIDLA:
- KRITICKÉ: Na naskenované stránce bývá VÍCE samostatných dokladů! Pokud vidíš 2 nebo více účtenek, paragonů nebo faktur na jedné stránce (vedle sebe, nad sebou, nalepené na papíru), MUSÍŠ každý vrátit jako SAMOSTATNÝ objekt v poli "dokumenty". Typický sken A4 obsahuje 2-4 účtenky. Nestačí je sloučit do jednoho záznamu!
- U neadresních dokladů (účtenky, paragony bez IČO odběratele) bude odberatel_ico null
- Pokud je doklad v cizí měně, uveď správnou měnu (EUR, USD, GBP, PLN atd.)
- Pokud údaj nelze z dokumentu zjistit, použij null - nikdy nevymýšlej data
- castka_celkem = celková částka K ÚHRADĚ (včetně DPH)
- castka_dph = samotná částka DPH (ne základ daně, ne celkem s DPH)
- Datumy vždy ve formátu YYYY-MM-DD
- IČO dodavatele: pouze číslice (bez CZ prefixu, 8 číslic pro české IČO)
- DIČ dodavatele: včetně prefixu země (CZ, SK, PT atd.)
- Pokud je kvalita "nizka", vyplň kvalita_poznamka s krátkým popisem problému
- Pokud je kvalita "necitelna", přesto vyplň co lze rozpoznat
- raw_text: přepis klíčového textu z dokladu (max 500 znaků na doklad). U účtenek stačí hlavička, položky a součet.
- Doklad může být v jakémkoliv jazyce - zpracuj ho bez ohledu na jazyk
PROMPT;

        return $prompt;
    }

    /**
     * Sestaví seznam kategorií pro AI prompt z databáze.
     */
    private function buildKategoriePrompt(Firma $firma): string
    {
        $kategorie = $firma->kategorie()->orderBy('poradi')->get();

        if ($kategorie->isEmpty()) {
            return 'ostatní';
        }

        return $kategorie->map(function ($kat) {
            $nazev = $kat->nazev;
            return $kat->popis ? "{$nazev}: {$kat->popis}" : $nazev;
        })->implode("\n");
    }

    /**
     * Pokusí se opravit oříznutý JSON z AI odpovědi.
     * Uzavře otevřené stringy, pole a objekty, aby JSON šel parsovat.
     */
    private function repairTruncatedJson(string $content): string
    {
        // Najdi začátek JSON objektu
        $start = strpos($content, '{');
        if ($start === false) {
            return $content;
        }

        $json = substr($content, $start);

        // Odstraň neúplný poslední objekt v poli dokumenty
        // Hledáme poslední kompletní objekt (uzavřený })
        if (preg_match_all('/\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\}/s', $json, $objects)) {
            // Zkusíme najít pole dokumenty a uzavřít ho
            $lastComplete = strrpos($json, '}');
            if ($lastComplete !== false) {
                $trimmed = substr($json, 0, $lastComplete + 1);
                // Spočítej otevřené závorky
                $openBraces = substr_count($trimmed, '{') - substr_count($trimmed, '}');
                $openBrackets = substr_count($trimmed, '[') - substr_count($trimmed, ']');

                // Uzavři otevřené závorky
                $trimmed .= str_repeat(']', max(0, $openBrackets));
                $trimmed .= str_repeat('}', max(0, $openBraces));

                $test = json_decode($trimmed, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $trimmed;
                }
            }
        }

        // Fallback: jednoduchý přístup - uzavři vše
        $lastCloseBrace = strrpos($json, '}');
        if ($lastCloseBrace !== false) {
            $json = substr($json, 0, $lastCloseBrace + 1);
            $openBraces = substr_count($json, '{') - substr_count($json, '}');
            $openBrackets = substr_count($json, '[') - substr_count($json, ']');
            $json .= str_repeat(']', max(0, $openBrackets));
            $json .= str_repeat('}', max(0, $openBraces));
        }

        return substr($content, 0, $start) . $json;
    }

    /**
     * Sestaví S3 cestu: doklady/{ICO}/{YYYY-MM}/{YYYY-MM-DD}_{ID}.{ext}
     */
    private function buildS3Path(string $ico, int $dokladId, string $originalName, ?string $datumVystaveni): string
    {
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION)) ?: 'pdf';
        $datum = $datumVystaveni ?: date('Y-m-d');
        $mesic = substr($datum, 0, 7);

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
}
