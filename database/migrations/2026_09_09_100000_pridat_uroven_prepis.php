<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Třetí úroveň zpracování: Přepis.
 *
 * Doklad projde jen Textractem — uloží se přesný přepis textu, ale nikdo mu
 * nerozumí, takže pole zůstávají prázdná. Doklad je díky tomu plnotextově
 * dohledatelný podle libovolného slova, které na něm stojí.
 *
 * Stojí ~3 haléře za stránku (Textract účtuje za stránku bez ohledu na to,
 * jestli je to A4 nebo malý paragon), tedy zhruba sedmkrát méně než plné
 * vyčtení. Proto se hodí jako to, co jde nabídnout zdarma.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('sys_firmy', 'uroven_zpracovani')) {
            return;
        }

        DB::statement(
            "ALTER TABLE sys_firmy MODIFY uroven_zpracovani
             ENUM('ulozeni', 'prepis', 'vycteni') NOT NULL DEFAULT 'vycteni'"
        );
    }

    public function down(): void
    {
        // Firmy na nové úrovni je potřeba nejdřív srovnat, jinak by je zúžení
        // enumu potichu překlopilo na prázdnou hodnotu.
        DB::table('sys_firmy')->where('uroven_zpracovani', 'prepis')->update(['uroven_zpracovani' => 'ulozeni']);

        DB::statement(
            "ALTER TABLE sys_firmy MODIFY uroven_zpracovani
             ENUM('ulozeni', 'vycteni') NOT NULL DEFAULT 'vycteni'"
        );
    }
};
