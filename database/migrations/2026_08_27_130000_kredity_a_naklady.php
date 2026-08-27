<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Úroveň služby, kredity a měření nákladů.
 *
 * Úrovně jsou zatím dvě: `ulozeni` (soubor se jen uloží) a `vycteni` (Textract
 * + Claude vyplní pole). Volí se v nastavení firmy, ne u jednotlivého dokladu.
 *
 * Kredity se počítají za stránku. `kredity = NULL` znamená bez omezení — a to
 * je i výchozí stav, aby se stávajícím firmám nic nezměnilo. Omezení začne
 * platit, až někdo firmě kredity přidělí.
 *
 * `sys_ai_volani` měří skutečné náklady na API. Není to podklad pro ceník,
 * ceny jsou obchodní rozhodnutí — je to podklad pro znalost marže.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sys_firmy', function (Blueprint $table) {
            if (!Schema::hasColumn('sys_firmy', 'uroven_zpracovani')) {
                $table->enum('uroven_zpracovani', ['ulozeni', 'vycteni'])
                    ->default('vycteni')
                    ->after('pravidla_zpracovani');
            }
            if (!Schema::hasColumn('sys_firmy', 'kredity')) {
                $table->integer('kredity')->nullable()->after('uroven_zpracovani');
            }
        });

        if (!Schema::hasTable('sys_kredity_pohyby')) {
            Schema::create('sys_kredity_pohyby', function (Blueprint $table) {
                $table->id();
                $table->string('firma_ico', 20);
                $table->integer('zmena');
                $table->integer('zustatek_po')->nullable();
                $table->string('duvod', 100);
                $table->unsignedBigInteger('doklad_id')->nullable();
                $table->timestamp('vytvoreno')->useCurrent();

                $table->foreign('firma_ico')->references('ico')->on('sys_firmy')->onDelete('cascade');
                $table->index(['firma_ico', 'vytvoreno']);
            });
        }

        if (!Schema::hasTable('sys_ai_volani')) {
            Schema::create('sys_ai_volani', function (Blueprint $table) {
                $table->id();
                $table->string('firma_ico', 20)->nullable();
                $table->unsignedBigInteger('doklad_id')->nullable();
                $table->enum('sluzba', ['claude', 'textract']);
                $table->string('model', 60)->nullable();
                $table->unsignedInteger('vstupni_tokens')->default(0);
                $table->unsignedInteger('vystupni_tokens')->default(0);
                $table->unsignedInteger('cache_read_tokens')->default(0);
                $table->unsignedInteger('cache_create_tokens')->default(0);
                // Textract se účtuje po stránkách, ne po tokenech.
                $table->unsignedInteger('stranky')->default(0);
                $table->decimal('cena_usd', 12, 6)->default(0);
                $table->boolean('uspesne')->default(true);
                $table->unsignedSmallInteger('http_status')->nullable();
                $table->unsignedInteger('trvani_ms')->default(0);
                $table->string('poznamka', 250)->nullable();
                $table->timestamp('vytvoreno')->useCurrent();

                // Firma se nemaže kaskádou — náklad zůstává i po jejím odchodu,
                // jinak by se zpětně rozpadla čísla za uzavřené měsíce.
                $table->index(['firma_ico', 'vytvoreno']);
                $table->index('doklad_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sys_ai_volani');
        Schema::dropIfExists('sys_kredity_pohyby');

        Schema::table('sys_firmy', function (Blueprint $table) {
            $table->dropColumn(['uroven_zpracovani', 'kredity']);
        });
    }
};
