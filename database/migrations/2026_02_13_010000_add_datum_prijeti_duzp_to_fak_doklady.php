<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fak_doklady', function (Blueprint $table) {
            $table->date('datum_prijeti')->nullable()->after('datum_vystaveni');
            $table->date('duzp')->nullable()->after('datum_prijeti');
        });

        // Nastavit datum_prijeti na created_at pro existující záznamy
        DB::statement('UPDATE fak_doklady SET datum_prijeti = DATE(created_at) WHERE datum_prijeti IS NULL');
    }

    public function down(): void
    {
        Schema::table('fak_doklady', function (Blueprint $table) {
            $table->dropColumn(['datum_prijeti', 'duzp']);
        });
    }
};
