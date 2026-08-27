<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rozliší, co se nahrálo: doklad, nebo dokument.
 *
 * Doklad je účetní podklad — ten se vytěžuje. Dokument je cokoli, co se má jen
 * uložit a být k dohledání (smlouva, protokol, objednávka); u něj se AI ani
 * nespouští. Rozhodnutí padá při nahrávání a jde změnit — dodatečné vytěžení
 * je tlačítko, ne jednosměrka.
 *
 * Nový stav `ulozeno` znamená „soubor je uložený, ale nevytěžený". Sedí na
 * dokument i na doklad, u kterého se vytěžení zatím nespustilo.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('fak_doklady', 'druh')) {
            return;
        }

        Schema::table('fak_doklady', function (Blueprint $table) {
            $table->enum('druh', ['doklad', 'dokument'])->default('doklad')->after('stav');
            $table->index(['firma_ico', 'druh']);
        });
    }

    public function down(): void
    {
        Schema::table('fak_doklady', function (Blueprint $table) {
            $table->dropIndex(['firma_ico', 'druh']);
            $table->dropColumn('druh');
        });
    }
};
