<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lhůta na rozmyšlenou u smazání účtu.
 *
 * Smazání se nedělá hned — účet se uzavře a teprve po uplynutí lhůty se
 * nenávratně smaže. Do té doby ho jde obnovit odkazem z e-mailu nebo po
 * přihlášení.
 *
 * `smazani_k` je okamžik, kdy se smazání stane definitivním. `obnoveni_token`
 * slouží odkazu v e-mailu — přihlásit se totiž uživatel v té době normálně
 * nemůže, takže se nemá jak prokázat jinak.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sys_users', function (Blueprint $table) {
            if (!Schema::hasColumn('sys_users', 'smazani_k')) {
                $table->timestamp('smazani_k')->nullable();
                $table->index('smazani_k');
            }
            if (!Schema::hasColumn('sys_users', 'obnoveni_token')) {
                $table->string('obnoveni_token', 64)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('sys_users', function (Blueprint $table) {
            $table->dropIndex(['smazani_k']);
            $table->dropColumn(['smazani_k', 'obnoveni_token']);
        });
    }
};
