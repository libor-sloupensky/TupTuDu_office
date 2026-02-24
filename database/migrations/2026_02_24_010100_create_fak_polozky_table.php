<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fak_polozky', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doklad_id')->constrained('fak_doklady')->cascadeOnDelete();
            $table->smallInteger('poradi')->default(1);
            $table->string('text', 500);
            $table->decimal('mnozstvi', 12, 3)->nullable();
            $table->string('jednotka', 20)->nullable();
            $table->decimal('cena_za_jednotku', 12, 2)->nullable();
            $table->decimal('zaklad_dane', 12, 2)->nullable();
            $table->decimal('sazba_dph', 5, 2)->nullable();
            $table->decimal('castka_dph', 12, 2)->nullable();
            $table->decimal('castka_celkem', 12, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fak_polozky');
    }
};
