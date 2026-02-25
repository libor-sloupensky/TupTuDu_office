<?php

namespace App\Services;

use App\Models\Doklad;
use Carbon\Carbon;

class DrivePathBuilder
{
    /** Default šablona pokud firma nemá vlastní */
    public const DEFAULT_TEMPLATE = '{nahrano:YYYY}/{duzp:YY-MM-DD}_{dodavatel:15}_{id}';

    /** Povolené tokeny (whitelist) */
    private const ALLOWED_TOKENS = [
        'id', 'nahrano', 'duzp', 'vystaveni', 'splatnost',
        'dodavatel', 'dodavatel_ico', 'ico', 'castka', 'vs',
        'typ', 'kategorie', 'cislo',
    ];

    /** Datumové tokeny → název sloupce v DB */
    private const DATE_TOKENS = [
        'nahrano'   => 'created_at',
        'duzp'      => 'duzp',
        'vystaveni' => 'datum_vystaveni',
        'splatnost' => 'datum_splatnosti',
    ];

    /**
     * Sestaví cestu ze šablony a dat dokladu.
     *
     * @return array{folders: string[], filename: string}
     */
    public function build(string $template, Doklad $doklad): array
    {
        // Nahradit tokeny hodnotami
        $result = preg_replace_callback(
            '/\{([a-z_]+)(?::([^}]*))?\}/',
            fn (array $m) => $this->resolveToken($m[1], $m[2] ?? null, $doklad),
            $template
        );

        // Pokud v šabloně chybí {id}, přidat na konec
        if (! str_contains($template, '{id}')) {
            $result .= '_' . $doklad->id;
        }

        // Rozdělit na segmenty (složky + název souboru)
        $segments = array_values(array_filter(
            explode('/', $result),
            fn (string $s) => $s !== ''
        ));

        if (empty($segments)) {
            return ['folders' => [], 'filename' => (string) $doklad->id];
        }

        // Poslední segment = název souboru, zbytek = složky
        $filename = $this->sanitizeSegment(array_pop($segments));
        $folders = array_map(fn (string $s) => $this->sanitizeSegment($s), $segments);

        // Fallback: pokud je filename prázdný po sanitizaci
        if ($filename === '') {
            $filename = (string) $doklad->id;
        }

        return ['folders' => $folders, 'filename' => $filename];
    }

    /**
     * Validace šablony — vrací pole chybových hlášek (prázdné = OK).
     *
     * @return string[]
     */
    public function validate(string $template): array
    {
        $errors = [];

        if (trim($template) === '') {
            $errors[] = 'Šablona nesmí být prázdná.';
            return $errors;
        }

        if (str_contains($template, '..')) {
            $errors[] = 'Šablona nesmí obsahovat "..".';
        }

        if (mb_strlen($template) > 200) {
            $errors[] = 'Šablona je příliš dlouhá (max 200 znaků).';
        }

        // Najdi všechny tokeny a zkontroluj whitelist
        preg_match_all('/\{([a-z_]+)(?::[^}]*)?\}/', $template, $matches);
        foreach ($matches[1] as $token) {
            if (! in_array($token, self::ALLOWED_TOKENS, true)) {
                $errors[] = "Neznámý token: {" . $token . "}";
            }
        }

        // Zkontroluj neuzavřené závorky
        if (preg_match('/\{[^}]{50,}/', $template)) {
            $errors[] = 'Šablona obsahuje neuzavřenou závorku.';
        }

        return $errors;
    }

    /**
     * Sestaví náhledový řetězec s ukázkovými daty (pro JS preview v nastavení).
     */
    public function preview(string $template): string
    {
        $fakeDoklad = new Doklad([
            'id'               => 12345,
            'created_at'       => Carbon::parse('2026-02-25'),
            'duzp'             => Carbon::parse('2026-01-12'),
            'datum_vystaveni'  => Carbon::parse('2026-01-10'),
            'datum_splatnosti' => Carbon::parse('2026-02-10'),
            'dodavatel_nazev'  => 'Kaufland s.r.o.',
            'dodavatel_ico'    => '12345678',
            'firma_ico'        => '87700484',
            'castka_celkem'    => 1250.00,
            'variabilni_symbol' => '2024001',
            'typ_dokladu'      => 'faktura',
            'kategorie'        => 'materiál',
            'cislo_dokladu'    => 'FV-2024-001',
        ]);
        // Force id (not mass-assignable)
        $fakeDoklad->id = 12345;

        $result = $this->build($template, $fakeDoklad);

        $path = implode('/', array_merge($result['folders'], [$result['filename']]));

        return $path . '.pdf';
    }

    /**
     * Nahradí jeden token hodnotou z dokladu.
     */
    private function resolveToken(string $token, ?string $format, Doklad $doklad): string
    {
        if (! in_array($token, self::ALLOWED_TOKENS, true)) {
            return '';
        }

        // ID dokladu
        if ($token === 'id') {
            return (string) $doklad->id;
        }

        // Datumové tokeny
        if (isset(self::DATE_TOKENS[$token])) {
            $column = self::DATE_TOKENS[$token];
            $date = $doklad->{$column};

            if (! $date) {
                return 'nezname';
            }

            $carbon = $date instanceof Carbon ? $date : Carbon::parse($date);

            return $this->formatDate($carbon, $format);
        }

        // Textové tokeny
        $value = match ($token) {
            'dodavatel'     => $doklad->dodavatel_nazev,
            'dodavatel_ico' => $doklad->dodavatel_ico,
            'ico'           => $doklad->firma_ico,
            'castka'        => $doklad->castka_celkem !== null ? number_format((float) $doklad->castka_celkem, 2, '.', '') : null,
            'vs'            => $doklad->variabilni_symbol,
            'typ'           => $doklad->typ_dokladu,
            'kategorie'     => $doklad->kategorie,
            'cislo'         => $doklad->cislo_dokladu,
            default         => null,
        };

        if ($value === null || $value === '') {
            return 'nezname';
        }

        $value = (string) $value;

        // Limit délky (format = max počet znaků pro textové tokeny)
        if ($format !== null && ctype_digit($format) && (int) $format > 0) {
            $value = mb_substr($value, 0, (int) $format);
        }

        return $value;
    }

    /**
     * Formátuje datum podle uživatelského formátu (YYYY, YY, MM, DD).
     */
    private function formatDate(Carbon $date, ?string $format): string
    {
        if ($format === null || $format === '') {
            return $date->format('Y-m-d');
        }

        $replacements = [
            'YYYY' => $date->format('Y'),
            'YY'   => $date->format('y'),
            'MM'   => $date->format('m'),
            'DD'   => $date->format('d'),
        ];

        // Nahrazujeme od nejdelšího (YYYY před YY)
        return str_replace(
            array_keys($replacements),
            array_values($replacements),
            $format
        );
    }

    /**
     * Sanitizuje jeden segment cesty (složku nebo název souboru).
     */
    private function sanitizeSegment(string $segment): string
    {
        // Odebrat nebezpečné znaky (Windows/Drive nepovolené + path traversal)
        $segment = str_replace(['\\', ':', '*', '?', '"', '<', '>', '|', '..'], '', $segment);

        // Trim mezery a tečky na začátku/konci
        $segment = trim($segment, " \t\n\r\0\x0B.");

        // Max délka segmentu
        if (mb_strlen($segment) > 100) {
            $segment = mb_substr($segment, 0, 100);
        }

        return $segment;
    }
}
