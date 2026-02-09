<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('faktury', function (Blueprint $table) {
            $table->id();
            $table->string('nazev_souboru');
            $table->string('cesta_souboru');
            $table->string('dodavatel')->nullable();
            $table->string('cislo_faktury', 100)->nullable();
            $table->date('datum_vystaveni')->nullable();
            $table->date('datum_splatnosti')->nullable();
            $table->decimal('castka_celkem', 12, 2)->nullable();
            $table->string('mena', 10)->nullable();
            $table->decimal('castka_dph', 12, 2)->nullable();
            $table->string('ico', 20)->nullable();
            $table->string('dic', 20)->nullable();
            $table->string('kategorie', 100)->nullable();
            $table->longText('raw_odpoved')->nullable();
            $table->enum('stav', ['nahrano', 'zpracovava_se', 'dokonceno', 'chyba'])->default('nahrano');
            $table->text('chybova_zprava')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('faktury');
    }
};
