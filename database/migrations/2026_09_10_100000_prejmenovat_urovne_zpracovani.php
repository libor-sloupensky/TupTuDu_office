<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Přejmenování úrovní zpracování.
 *
 *   prepis  → vycteni     (Textract vyčte text z obrázku)
 *   vycteni → rozpoznani  (AI rozpozná, co které číslo znamená)
 *
 * Pojem „přepis" byl zavádějící a „vyčtení" sedí přesně na to, co Textract
 * dělá. Nejvyšší úroveň se tím uvolnila pro „rozpoznání".
 *
 * Pořadí kroků je podstatné: nejdřív se odstěhuje `vycteni`, teprve pak se na
 * jeho místo posune `prepis`. Obráceně by se obojí slilo do jednoho.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('sys_firmy', 'uroven_zpracovani')) {
            return;
        }

        // Dočasně se do enumu vejdou stará i nová jména naráz.
        DB::statement(
            "ALTER TABLE sys_firmy MODIFY uroven_zpracovani
             ENUM('ulozeni', 'prepis', 'vycteni', 'rozpoznani') NOT NULL DEFAULT 'vycteni'"
        );

        DB::table('sys_firmy')->where('uroven_zpracovani', 'vycteni')->update(['uroven_zpracovani' => 'rozpoznani']);
        DB::table('sys_firmy')->where('uroven_zpracovani', 'prepis')->update(['uroven_zpracovani' => 'vycteni']);

        DB::statement(
            "ALTER TABLE sys_firmy MODIFY uroven_zpracovani
             ENUM('ulozeni', 'vycteni', 'rozpoznani') NOT NULL DEFAULT 'rozpoznani'"
        );

        // Důvod pohybu kreditů nese název úrovně, ať historie zůstane čitelná.
        if (Schema::hasTable('sys_kredity_pohyby')) {
            DB::table('sys_kredity_pohyby')->where('duvod', 'vycteni')->update(['duvod' => 'rozpoznani']);
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('sys_firmy', 'uroven_zpracovani')) {
            return;
        }

        DB::statement(
            "ALTER TABLE sys_firmy MODIFY uroven_zpracovani
             ENUM('ulozeni', 'prepis', 'vycteni', 'rozpoznani') NOT NULL DEFAULT 'vycteni'"
        );

        DB::table('sys_firmy')->where('uroven_zpracovani', 'vycteni')->update(['uroven_zpracovani' => 'prepis']);
        DB::table('sys_firmy')->where('uroven_zpracovani', 'rozpoznani')->update(['uroven_zpracovani' => 'vycteni']);

        DB::statement(
            "ALTER TABLE sys_firmy MODIFY uroven_zpracovani
             ENUM('ulozeni', 'prepis', 'vycteni') NOT NULL DEFAULT 'vycteni'"
        );

        if (Schema::hasTable('sys_kredity_pohyby')) {
            DB::table('sys_kredity_pohyby')->where('duvod', 'rozpoznani')->update(['duvod' => 'vycteni']);
        }
    }
};
