<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fak_doklady', function (Blueprint $table) {
            $table->string('variabilni_symbol', 20)->nullable()->after('cislo_dokladu');
            $table->string('cislo_uctu', 50)->nullable()->after('variabilni_symbol');
            $table->string('iban', 34)->nullable()->after('cislo_uctu');
            $table->string('zpusob_platby', 20)->nullable()->after('iban');
            $table->boolean('reverse_charge')->default(false)->after('zpusob_platby');
            $table->decimal('castka_zaklad', 12, 2)->nullable()->after('castka_celkem');
            $table->text('poznamka')->nullable()->after('kategorie');
        });
    }

    public function down(): void
    {
        Schema::table('fak_doklady', function (Blueprint $table) {
            $table->dropColumn([
                'variabilni_symbol', 'cislo_uctu', 'iban',
                'zpusob_platby', 'reverse_charge', 'castka_zaklad', 'poznamka',
            ]);
        });
    }
};
