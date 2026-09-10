<?php

namespace App\Services;

use App\Models\Firma;
use Illuminate\Support\Facades\DB;

/**
 * Kredity firmy — počítají se za stránku podle zvolené úrovně zpracování.
 *
 * `kredity = NULL` znamená bez omezení. To je výchozí stav, takže firma, které
 * nikdo kredity nepřidělil, funguje jako dosud. Jakmile se zůstatek nastaví,
 * začne se odečítat a po vyčerpání firma spadne na úroveň *Uložení* — doklady
 * se dál ukládají, jen se nerozpoznávají a dají se rozpoznat později.
 */
class Kredity
{
    /** Kolik kreditů stojí jedna stránka na dané úrovni. */
    private const CENIK = [
        'ulozeni' => 0,
        // Vyčtení stojí jen Textract, tedy ~3 haléře za stránku. Zatím je zdarma;
        // až se bude dělat ceník, je tohle místo, kde se to rozhodne.
        'vycteni' => 0,
        'rozpoznani' => 1,
    ];

    public function cenaZaStranku(string $uroven): int
    {
        return self::CENIK[$uroven] ?? 0;
    }

    /**
     * Na jaké úrovni se tenhle soubor opravdu zpracuje.
     *
     * Vychází z nastavení firmy, ale když na zvolenou úroveň nezbývají kredity,
     * spadne se níž. Vrací vždy platnou úroveň, takže volající se nemusí ptát
     * dvakrát.
     */
    public function urovenProZpracovani(Firma $firma, int $stranky): string
    {
        $uroven = $firma->uroven_zpracovani ?: 'rozpoznani';

        if (!array_key_exists($uroven, self::CENIK)) {
            $uroven = 'rozpoznani';
        }

        $cena = $this->cenaZaStranku($uroven) * max($stranky, 1);

        if ($cena === 0 || $firma->kredity === null || $firma->kredity >= $cena) {
            return $uroven;
        }

        // Kredity došly. Doklad se nezahodí — jen se uloží a rozpoznat ho půjde
        // později tlačítkem. Že se nespadne rovnou na Vyčtení, je zatím záměr:
        // i to něco stojí a patří to do rozhodnutí o ceníku.
        return 'ulozeni';
    }

    /**
     * Odečte kredity za zpracované stránky a zapíše pohyb.
     *
     * Odečítá se až po zpracování — když vytěžení selže, firma o kredity
     * nepřijde. Zůstatek se snižuje přímo v databázi, aby se dva souběžné
     * uploady nepřepsaly navzájem.
     */
    public function odecti(Firma $firma, int $stranky, ?int $dokladId = null): void
    {
        if ($firma->kredity === null) {
            return; // bez omezení — není co odečítat
        }

        // Do pohybu se zapisuje úroveň, která kredity spotřebovala — ať je
        // v historii vidět, za co se platilo.
        $uroven = $firma->uroven_zpracovani ?: 'rozpoznani';
        $cena = $this->cenaZaStranku($uroven) * max($stranky, 1);

        if ($cena === 0) {
            return;
        }

        DB::transaction(function () use ($firma, $cena, $dokladId, $uroven) {
            Firma::where('ico', $firma->ico)->update([
                'kredity' => DB::raw('GREATEST(kredity - ' . (int) $cena . ', 0)'),
            ]);

            $zustatek = (int) Firma::where('ico', $firma->ico)->value('kredity');

            DB::table('sys_kredity_pohyby')->insert([
                'firma_ico' => $firma->ico,
                'zmena' => -$cena,
                'zustatek_po' => $zustatek,
                'duvod' => $uroven,
                'doklad_id' => $dokladId,
                'vytvoreno' => now(),
            ]);

            $firma->kredity = $zustatek;
        });
    }

    /** Přidělí firmě kredity. */
    public function pripis(Firma $firma, int $kolik, string $duvod = 'pripis'): void
    {
        if ($kolik <= 0) {
            return;
        }

        DB::transaction(function () use ($firma, $kolik, $duvod) {
            // Dosud neomezená firma se přidělením kreditů stává omezenou —
            // od té chvíle má smysl zůstatek sledovat.
            $vychozi = $firma->kredity ?? 0;

            Firma::where('ico', $firma->ico)->update(['kredity' => $vychozi + $kolik]);

            DB::table('sys_kredity_pohyby')->insert([
                'firma_ico' => $firma->ico,
                'zmena' => $kolik,
                'zustatek_po' => $vychozi + $kolik,
                'duvod' => $duvod,
                'vytvoreno' => now(),
            ]);

            $firma->kredity = $vychozi + $kolik;
        });
    }
}
