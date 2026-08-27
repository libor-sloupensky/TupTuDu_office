<?php

namespace App\Services;

use App\Models\AiVolani;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Měření nákladů na placené služby.
 *
 * Postup i ceník převzatý z projektu Kalkulio (`app/Services/ClaudeApi.php`),
 * kde je to odladěné — včetně toho, že čtení z cache stojí desetinu ceny
 * vstupu, vytvoření cache 125 % a dávkové zpracování má poloviční sazbu.
 * Oproti Kalkuliu je tu navíc firma, doklad a Textract: náklad na stránku se
 * skládá ze dvou položek a bez firmy by se nedal přiřadit nájemci ani
 * partnerovi.
 *
 * Zápis nikdy neshodí hlavní volání — když selže logování, doklad se zpracuje
 * dál a chyba jde jen do logu.
 */
class NakladyAi
{
    /** Ceny v USD za milion tokenů. Aktualizovat při změně ceníku Anthropicu. */
    private const CENY_MODELU = [
        'claude-haiku-4-5' => ['vstup' => 1.00, 'vystup' => 5.00],
        'claude-haiku-4-5-20251001' => ['vstup' => 1.00, 'vystup' => 5.00],
        'claude-sonnet-4-6' => ['vstup' => 3.00, 'vystup' => 15.00],
        'claude-sonnet-5' => ['vstup' => 3.00, 'vystup' => 15.00],
        'claude-opus-4-8' => ['vstup' => 5.00, 'vystup' => 25.00],
    ];

    /** AWS Textract DetectDocumentText — cena v USD za stránku. */
    private const CENA_TEXTRACT_STRANKA = 0.0015;

    public function zalogujClaude(
        string $model,
        array $usage,
        ?string $firmaIco,
        ?int $dokladId,
        bool $uspesne,
        ?int $httpStatus,
        int $trvaniMs,
        ?string $poznamka = null,
    ): void {
        $vstup = (int) ($usage['input_tokens'] ?? 0);
        $vystup = (int) ($usage['output_tokens'] ?? 0);
        $cacheRead = (int) ($usage['cache_read_input_tokens'] ?? 0);
        $cacheCreate = (int) ($usage['cache_creation_input_tokens'] ?? 0);

        $this->zapis([
            'firma_ico' => $firmaIco,
            'doklad_id' => $dokladId,
            'sluzba' => 'claude',
            'model' => $model,
            'vstupni_tokens' => $vstup,
            'vystupni_tokens' => $vystup,
            'cache_read_tokens' => $cacheRead,
            'cache_create_tokens' => $cacheCreate,
            'cena_usd' => $uspesne ? $this->cenaClaude($model, $vstup, $vystup, $cacheRead, $cacheCreate) : 0.0,
            'uspesne' => $uspesne,
            'http_status' => $httpStatus,
            'trvani_ms' => $trvaniMs,
            'poznamka' => $poznamka,
        ]);
    }

    public function zalogujTextract(
        int $stranky,
        ?string $firmaIco,
        ?int $dokladId,
        bool $uspesne,
        int $trvaniMs,
        ?string $poznamka = null,
    ): void {
        $this->zapis([
            'firma_ico' => $firmaIco,
            'doklad_id' => $dokladId,
            'sluzba' => 'textract',
            'stranky' => $stranky,
            'cena_usd' => $uspesne ? round($stranky * self::CENA_TEXTRACT_STRANKA, 6) : 0.0,
            'uspesne' => $uspesne,
            'trvani_ms' => $trvaniMs,
            'poznamka' => $poznamka,
        ]);
    }

    private function cenaClaude(
        string $model,
        int $vstup,
        int $vystup,
        int $cacheRead,
        int $cacheCreate,
        bool $davka = false,
    ): float {
        // Neznámý model raději spočítat nejlevnější sazbou než vůbec — číslo
        // se pak dá dohledat podle názvu modelu v logu.
        $ceny = self::CENY_MODELU[$model] ?? ['vstup' => 1.00, 'vystup' => 5.00];
        $sleva = $davka ? 0.5 : 1.0;

        $celkem = $vstup / 1_000_000 * $ceny['vstup']
            + $vystup / 1_000_000 * $ceny['vystup']
            + $cacheRead / 1_000_000 * $ceny['vstup'] * 0.10
            + $cacheCreate / 1_000_000 * $ceny['vstup'] * 1.25;

        return round($celkem * $sleva, 6);
    }

    private function zapis(array $data): void
    {
        try {
            if (isset($data['poznamka'])) {
                $data['poznamka'] = Str::limit($data['poznamka'], 240);
            }
            $data['vytvoreno'] = now();

            AiVolani::create($data);
        } catch (\Throwable $e) {
            Log::warning('Zápis nákladu selhal: ' . $e->getMessage());
        }
    }
}
