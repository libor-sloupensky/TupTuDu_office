<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fak_doklady', function (Blueprint $table) {
            $table->id();
            $table->string('firma_ico', 20);
            $table->string('dodavatel_ico', 20)->nullable();
            $table->string('nazev_souboru');
            $table->string('cesta_souboru');
            $table->string('dodavatel_nazev')->nullable();
            $table->string('cislo_dokladu', 100)->nullable();
            $table->date('datum_vystaveni')->nullable();
            $table->date('datum_splatnosti')->nullable();
            $table->decimal('castka_celkem', 12, 2)->nullable();
            $table->string('mena', 10)->default('CZK');
            $table->decimal('castka_dph', 12, 2)->nullable();
            $table->string('kategorie', 100)->nullable();
            $table->boolean('adresni')->default(true);
            $table->boolean('overeno_adresat')->default(false);
            $table->longText('raw_text')->nullable();
            $table->longText('raw_ai_odpoved')->nullable();
            $table->enum('stav', ['nahrano', 'zpracovava_se', 'dokonceno', 'chyba'])->default('nahrano');
            $table->text('chybova_zprava')->nullable();
            $table->timestamps();

            $table->foreign('firma_ico')->references('ico')->on('sys_firmy')->onDelete('cascade');
            $table->foreign('dodavatel_ico')->references('ico')->on('sys_dodavatele')->onDelete('set null');
            $table->index('firma_ico');
            $table->index('dodavatel_ico');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fak_doklady');
    }
};
