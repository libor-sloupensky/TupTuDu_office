<?php

namespace App\Console\Commands;

use App\Models\Firma;
use App\Services\Kredity;
use Illuminate\Console\Command;

/**
 * Přidělí firmě kredity. Než bude mít partner vlastní obrazovku, stačí konzole.
 *
 * Pozor: firma bez zůstatku není omezená vůbec. Prvním přidělením se omezenou
 * stává — od té chvíle se odečítá a po vyčerpání spadne na úroveň Uložení.
 */
class PripsatKredity extends Command
{
    protected $signature = 'kredity:pripsat
                            {ico : IČO firmy}
                            {kolik : Počet kreditů}
                            {--duvod=pripis : Popis pohybu}';

    protected $description = 'Přidělí firmě kredity';

    public function handle(Kredity $kredity): int
    {
        $firma = Firma::find($this->argument('ico'));

        if (!$firma) {
            $this->error('Firma s tímto IČO v systému není.');
            return 1;
        }

        $kolik = (int) $this->argument('kolik');
        if ($kolik <= 0) {
            $this->error('Počet kreditů musí být kladný.');
            return 1;
        }

        $puvodni = $firma->kredity === null ? 'bez omezení' : $firma->kredity;
        $kredity->pripis($firma, $kolik, $this->option('duvod'));

        $this->info("{$firma->nazev} ({$firma->ico}): {$puvodni} → {$firma->kredity} kreditů");

        return 0;
    }
}
