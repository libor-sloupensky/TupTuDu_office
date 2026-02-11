<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sys_ucetni_vazby', function (Blueprint $table) {
            $table->id();
            $table->string('ucetni_ico', 20);
            $table->string('klient_ico', 20);
            $table->enum('stav', ['ceka_na_ucetni', 'ceka_na_firmu', 'schvaleno', 'zamitnuto'])->default('ceka_na_firmu');
            $table->timestamps();

            $table->foreign('ucetni_ico')->references('ico')->on('sys_firmy')->onDelete('cascade');
            $table->foreign('klient_ico')->references('ico')->on('sys_firmy')->onDelete('cascade');
            $table->unique(['ucetni_ico', 'klient_ico']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sys_ucetni_vazby');
    }
};
