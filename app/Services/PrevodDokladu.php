<?php

namespace App\Services;

use App\Models\Doklad;
use App\Models\Firma;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Přesun dokladu mezi účty téhož člověka — mezi jeho firmami nebo do osobních
 * dokladů.
 *
 * Vytěžená data se **nezahazují**. Dodavatel, částky, data i položky popisují
 * samotný doklad, ne firmu, takže po přesunu platí dál a není důvod platit za
 * další čtení AI. Znovu se ale musí posoudit to, co na firmě záviselo:
 *
 *   - **adresát** — doklad vystavený jedné firmě nesmí u druhé zůstat označený
 *     jako ověřený,
 *   - **kategorie** — každá firma má vlastní seznam; co v cílové není, se
 *     zahodí, aby tam nezůstal cizí název,
 *   - **duplicita** — hlídá se v rámci firmy, takže vazba na doklad té staré
 *     ztrácí smysl,
 *   - **záloha na Disk** — razítko se smaže, aby se doklad nahrál do složky
 *     nové firmy.
 *
 * Soubor se v úložišti přesune pod cílovou firmu. Kdyby se to nepovedlo,
 * převod se neprovede vůbec — lepší než záznam ukazující do prázdna.
 */
class PrevodDokladu
{
    public function __construct(private DokladProcessor $processor = new DokladProcessor()) {}

    /**
     * @return array{zmeny: array<int, string>}  Co se při převodu změnilo
     */
    public function prevedNa(Doklad $doklad, Firma $cil): array
    {
        if ($doklad->firma_ico === $cil->ico) {
            throw new \InvalidArgumentException('Doklad už u téhle firmy je.');
        }

        $zmeny = [];
        $puvodniCesta = $doklad->cesta_souboru;
        $novaCesta = $puvodniCesta ? $this->presunSoubor($puvodniCesta, $doklad->firma_ico, $cil->ico) : null;

        $data = ['firma_ico' => $cil->ico];

        if ($novaCesta) {
            $data['cesta_souboru'] = $novaCesta;
        }

        // Adresát se posuzuje proti nové firmě.
        $adresat = $this->processor->overAdresata($doklad->odberatel_ico, $doklad->odberatel_nazev, $cil);
        if ($adresat['overeno'] !== (bool) $doklad->overeno_adresat) {
            $zmeny[] = $adresat['overeno']
                ? 'Doklad je adresovaný cílové firmě.'
                : 'Doklad je adresovaný někomu jinému než cílové firmě.';
        }
        $data['adresni'] = $adresat['adresni'];
        $data['overeno_adresat'] = $adresat['overeno'];

        // Kategorie, kterou cílová firma nezná, by tam jen mátla.
        if ($doklad->kategorie && !$cil->kategorie()->where('nazev', $doklad->kategorie)->exists()) {
            $zmeny[] = "Kategorie „{$doklad->kategorie}\" u cílové firmy není, zůstala prázdná.";
            $data['kategorie'] = null;
        }

        // Duplicita se hlídá v rámci firmy.
        $data['duplicita_id'] = $this->najdiDuplicitu($doklad, $cil);

        // Ať se doklad zálohuje do složky nové firmy.
        if ($doklad->google_drive_nahrano_at) {
            $zmeny[] = 'Na Disku zůstala kopie u původní firmy; do nové se nahraje znovu.';
        }
        $data['google_drive_nahrano_at'] = null;
        $data['google_drive_file_id'] = null;
        $data['google_drive_ucetni_file_id'] = null;

        DB::transaction(function () use ($doklad, $data) {
            // Doklady, které ukazovaly na tenhle jako na svůj originál, zůstávají
            // u původní firmy — vazba přes hranici firem nedává smysl.
            Doklad::where('duplicita_id', $doklad->id)
                ->where('firma_ico', $doklad->firma_ico)
                ->update(['duplicita_id' => null]);

            $doklad->update($data);
        });

        if ($novaCesta && $puvodniCesta) {
            $this->smazPuvodni($puvodniCesta);
        }

        return ['zmeny' => $zmeny];
    }

    /**
     * Zkopíruje soubor i odložené souřadnice slov pod cílovou firmu.
     * Vrací novou cestu, nebo null, když se cesta měnit nemá.
     */
    private function presunSoubor(string $cesta, string $zFirmy, string $doFirmy): ?string
    {
        $disk = Storage::disk('s3');

        if (!str_contains($cesta, "doklady/{$zFirmy}/") || !$disk->exists($cesta)) {
            // Netypická cesta nebo chybějící soubor — necháváme být, ať se
            // převod kvůli tomu nezastaví. Přístup se stejně řídí záznamem.
            return null;
        }

        $nova = str_replace("doklady/{$zFirmy}/", "doklady/{$doFirmy}/", $cesta);

        $disk->copy($cesta, $nova);

        $slova = DokladProcessor::cestaSlov($cesta);
        if ($disk->exists($slova)) {
            $disk->copy($slova, DokladProcessor::cestaSlov($nova));
        }

        return $nova;
    }

    private function smazPuvodni(string $cesta): void
    {
        $disk = Storage::disk('s3');

        try {
            $disk->delete($cesta);

            $slova = DokladProcessor::cestaSlov($cesta);
            if ($disk->exists($slova)) {
                $disk->delete($slova);
            }
        } catch (\Throwable $e) {
            // Kopie na novém místě už je, takže doklad funguje. Zbytek je jen
            // úklid a nemá smysl kvůli němu převod shazovat.
            Log::warning("Původní soubor se po převodu nesmazal: {$e->getMessage()}", ['cesta' => $cesta]);
        }
    }

    /** Obsahová duplicita v rámci cílové firmy — stejné číslo od téhož dodavatele. */
    private function najdiDuplicitu(Doklad $doklad, Firma $cil): ?int
    {
        if (!$doklad->cislo_dokladu || !$doklad->dodavatel_ico) {
            return null;
        }

        return Doklad::where('firma_ico', $cil->ico)
            ->where('cislo_dokladu', $doklad->cislo_dokladu)
            ->where('dodavatel_ico', $doklad->dodavatel_ico)
            ->where('id', '!=', $doklad->id)
            ->value('id');
    }
}
