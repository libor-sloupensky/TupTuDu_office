<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Osobní prostor pro soukromé doklady.
 *
 * Není to nová entita, ale další řádek v `sys_firmy` s vlastním kódem místo
 * IČO. Díky tomu funguje beze změny všechno, co na firmu navazuje — nahrávání,
 * hledání, mobilní aplikace, e-mailový příjem i záloha na Disk.
 *
 * Kód má tvar OS + 8 znaků, tedy deset míst. IČO má osm číslic, takže se ty
 * dva zápisy nemůžou potkat ani teď, ani až někdo takové IČO dostane.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sys_firmy', function (Blueprint $table) {
            if (!Schema::hasColumn('sys_firmy', 'je_osobni')) {
                $table->boolean('je_osobni')->default(false);
            }
            if (!Schema::hasColumn('sys_firmy', 'vlastnik_user_id')) {
                // Osobní prostor patří jednomu člověku. U firem zůstává prázdné.
                $table->unsignedBigInteger('vlastnik_user_id')->nullable();
                $table->foreign('vlastnik_user_id')->references('id')->on('sys_users')->onDelete('cascade');
                $table->index('vlastnik_user_id');
            }
            if (!Schema::hasColumn('sys_firmy', 'osobni_aktivni')) {
                // Vypnutí schová prostor z výběru, ale doklady nemaže.
                $table->boolean('osobni_aktivni')->default(true);
            }
        });
    }

    public function down(): void
    {
        Schema::table('sys_firmy', function (Blueprint $table) {
            $table->dropForeign(['vlastnik_user_id']);
            $table->dropColumn(['je_osobni', 'vlastnik_user_id', 'osobni_aktivni']);
        });
    }
};
