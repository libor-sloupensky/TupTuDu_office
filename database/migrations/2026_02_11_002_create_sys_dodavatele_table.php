<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sys_dodavatele', function (Blueprint $table) {
            $table->string('ico', 20)->primary();
            $table->string('nazev');
            $table->string('dic', 20)->nullable();
            $table->string('ulice')->nullable();
            $table->string('mesto')->nullable();
            $table->string('psc', 10)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sys_dodavatele');
    }
};
