<?php

namespace App\Console\Commands;

use App\Models\Doklad;
use App\Services\DokladProcessor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Zpětně doplní souřadnice slov ke starším dokladům, aby v nich šlo
 * zvýrazňovat hledaný výraz.
 *
 * Každý doklad projde znovu Textractem, takže to stojí peníze — ~3 haléře za
 * stránku. Proto se jede po dávkách a bez `--doopravdy` se jen spočítá, kolik
 * by to stálo.
 */
class DoplnitSlova extends Command
{
    protected $signature = 'doklady:doplnit-slova
                            {--limit=25 : Kolik dokladů zpracovat v jedné dávce}
                            {--firma= : Omezit na jedno IČO}
                            {--doopravdy : Bez tohoto přepínače se jen spočítá cena}';

    protected $description = 'Doplní souřadnice slov ke starším dokladům (zvýrazňování hledaného výrazu)';

    /** Cena Textractu za stránku v USD — jen pro odhad, přesná čísla jsou v sys_ai_volani. */
    private const CENA_STRANKA_USD = 0.0015;

    public function handle(): int
    {
        $processor = new DokladProcessor();

        $dotaz = Doklad::whereNotNull('cesta_souboru')
            ->when($this->option('firma'), fn ($q, $ico) => $q->where('firma_ico', $ico))
            ->orderBy('id');

        $celkem = (clone $dotaz)->count();

        if (!$this->option('doopravdy')) {
            // Odhad bere jednu stránku na doklad. Vícestránkové PDF vyjdou dráž,
            // takže je to spodní hranice, ne přesné číslo.
            $usd = round($celkem * self::CENA_STRANKA_USD, 2);
            $this->info("Dokladů se souborem: {$celkem}");
            $this->line('  Odhad ceny: nejméně $' . number_format($usd, 2) . ' (jedna stránka na doklad)');
            $this->line('  Kolik z nich souřadnice ještě nemá, se pozná až při průchodu.');
            $this->newLine();
            $this->comment('Nic se nezpracovalo. Spusť s --doopravdy.');

            return 0;
        }

        $hotovo = 0;
        $preskoceno = 0;
        $chyb = 0;
        $limit = (int) $this->option('limit');

        // Prochází se celá tabulka po částech, ne jen prvních pár set dokladů.
        // Jestli doklad souřadnice má, se pozná až podle úložiště, takže se to
        // nedá odfiltrovat dotazem — a s okénkem od začátku by se na konec
        // archivu nikdy nedošlo.
        $dotaz->chunkById(200, function ($davka) use ($processor, $limit, &$hotovo, &$preskoceno, &$chyb) {
            foreach ($davka as $doklad) {
                if ($hotovo >= $limit) {
                    return false; // dost pro tentokrát
                }

                if ($processor->maSlova($doklad)) {
                    $preskoceno++;
                    continue;
                }

                try {
                    $pocet = $processor->doplnSlova($doklad);
                    $hotovo++;
                    $this->line("  #{$doklad->id} — {$pocet} slov");
                } catch (\Throwable $e) {
                    $chyb++;
                    Log::warning("Doplnění slov selhalo u dokladu {$doklad->id}: {$e->getMessage()}");
                    $this->line("  #{$doklad->id} — chyba: {$e->getMessage()}");
                }
            }

            return true;
        });

        $this->info("Doplněno {$hotovo}, přeskočeno {$preskoceno} (už měly), chyb {$chyb}.");

        return 0;
    }
}
