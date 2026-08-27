<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Vrátí do fronty doklady, které se tvářily jako zálohované na Google Drive,
 * ale žádný soubor na Drivu nemají.
 *
 * Synchronizace dřív razítkovala `google_drive_nahrano_at` i ve chvíli, kdy
 * nebyl aktivní žádný Drive. Když se aplikace po vypršení tokenu sama odpojila,
 * odrazítkovala takhle celou frontu jako hotovou, aniž by se cokoli nahrálo —
 * a už se k těm dokladům nevrátila. Poznají se podle chybějícího ID souboru.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Na čisté databázi sloupce ještě neexistují (doplní je až migrace
        // 2026_08_27_100000) a opravovat není co — data tam žádná nejsou.
        if (!Schema::hasColumn('fak_doklady', 'google_drive_nahrano_at')) {
            return;
        }

        $pocet = DB::table('fak_doklady')
            ->whereNotNull('google_drive_nahrano_at')
            ->whereNull('google_drive_file_id')
            ->whereNull('google_drive_ucetni_file_id')
            ->update(['google_drive_nahrano_at' => null]);

        echo "  Vráceno do fronty pro Google Drive: {$pocet} dokladů" . PHP_EOL;
    }

    public function down(): void
    {
        // Data-only migrace. Zpětně razítko nedoplňujeme — doklady bez souboru
        // na Drivu do fronty patří.
    }
};
