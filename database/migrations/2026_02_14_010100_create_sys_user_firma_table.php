<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sys_user_firma', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('sys_users')->onDelete('cascade');
            $table->string('firma_ico', 20);
            $table->enum('role', ['ucetni', 'firma', 'dodavatel']);
            $table->timestamps();

            $table->foreign('firma_ico')->references('ico')->on('sys_firmy')->onDelete('cascade');
            $table->unique(['user_id', 'firma_ico']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sys_user_firma');
    }
};
