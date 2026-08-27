<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Partner je firma, která nástroj nabízí dál svým klientům — účetní kancelář
 * nebo jiná služba. Je to obchodní kanál a plátce, ne správce dat: k dokladům
 * napojených firem se nedostane. Do dokladů smí výhradně účetní firma se
 * schválenou vazbou v `sys_ucetni_vazby`.
 *
 * Napojení se vede s historií — řádek se neruší, jen se orazítkuje `odpojeno_at`.
 * Zároveň platí, že firma může mít v jednu chvíli nejvýš jednoho partnera.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sys_partneri', function (Blueprint $table) {
            $table->id();
            $table->string('nazev');
            $table->string('kontaktni_email')->nullable();
            // Klíč se ukládá jen jako otisk — plain text vidí partner jednou
            // při založení a pak už nikdy, ani my.
            $table->char('api_klic_hash', 64)->unique();
            $table->boolean('aktivni')->default(true);
            $table->timestamps();
        });

        Schema::create('sys_firma_partner', function (Blueprint $table) {
            $table->id();
            $table->string('firma_ico', 20);
            $table->foreignId('partner_id')->constrained('sys_partneri')->onDelete('cascade');
            $table->timestamp('napojeno_at');
            $table->timestamp('odpojeno_at')->nullable();
            $table->timestamps();

            $table->foreign('firma_ico')->references('ico')->on('sys_firmy')->onDelete('cascade');
            $table->index(['partner_id', 'odpojeno_at']);
            $table->index('firma_ico');
        });

        // Pojistka proti dvěma souběžným napojením téže firmy. Obyčejný unique
        // by nestačil — v MySQL se NULL nerovná NULL, takže by odpojené řádky
        // index nehlídal. Generovaný sloupec drží IČO jen u aktivního napojení,
        // u odpojeného je NULL, a těch smí být kolik chce.
        Schema::table('sys_firma_partner', function (Blueprint $table) {
            $table->string('aktivni_firma_ico', 20)
                ->nullable()
                ->storedAs('IF(odpojeno_at IS NULL, firma_ico, NULL)');
            $table->unique('aktivni_firma_ico');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sys_firma_partner');
        Schema::dropIfExists('sys_partneri');
    }
};
