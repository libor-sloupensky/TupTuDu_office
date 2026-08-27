<?php

namespace App\Console\Commands;

use App\Models\Partner;
use Illuminate\Console\Command;

/**
 * Založí partnera a vypíše jeho API klíč. Klíč se nikam neukládá v čitelné
 * podobě, takže tenhle výpis je jediná příležitost si ho opsat.
 *
 * Správa partnerů zatím nemá vlastní obrazovku — než jich bude víc, stačí
 * příkaz z konzole.
 */
class ZalozitPartnera extends Command
{
    protected $signature = 'partner:zalozit
                            {nazev : Název partnera}
                            {--email= : Kontaktní e-mail}
                            {--obnovit-klic : Místo založení vygeneruje nový klíč existujícímu partnerovi}';

    protected $description = 'Založí partnera a vypíše jeho API klíč';

    public function handle(): int
    {
        $nazev = $this->argument('nazev');

        if ($this->option('obnovit-klic')) {
            $partner = Partner::where('nazev', $nazev)->first();

            if (!$partner) {
                $this->error("Partner „{$nazev}\" neexistuje.");
                return 1;
            }
        } else {
            if (Partner::where('nazev', $nazev)->exists()) {
                $this->error("Partner „{$nazev}\" už existuje. Nový klíč: --obnovit-klic");
                return 1;
            }

            $partner = new Partner([
                'nazev' => $nazev,
                'kontaktni_email' => $this->option('email'),
                'aktivni' => true,
            ]);
            // Klíč doplní vygenerujApiKlic(), sloupec ale nesmí být prázdný.
            $partner->api_klic_hash = str_repeat('0', 64);
            $partner->save();
        }

        $klic = $partner->vygenerujApiKlic();

        $this->info($this->option('obnovit-klic') ? 'Klíč obnoven.' : 'Partner založen.');
        $this->newLine();
        $this->line("  Partner:  {$partner->nazev}  (id {$partner->id})");
        $this->line("  API klíč: {$klic}");
        $this->newLine();
        $this->comment('Klíč se nikde neukládá čitelně — opiš si ho teď, znovu už ho nezobrazíme.');

        return 0;
    }
}
