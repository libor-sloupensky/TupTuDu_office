<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Doklad extends Model
{
    protected $table = 'fak_doklady';

    protected $fillable = [
        'firma_ico', 'dodavatel_ico', 'nazev_souboru', 'cesta_souboru',
        'dodavatel_nazev', 'cislo_dokladu', 'datum_vystaveni', 'datum_splatnosti',
        'castka_celkem', 'mena', 'castka_dph', 'kategorie',
        'adresni', 'overeno_adresat', 'raw_text', 'raw_ai_odpoved',
        'stav', 'chybova_zprava',
    ];

    protected $casts = [
        'datum_vystaveni' => 'date',
        'datum_splatnosti' => 'date',
        'castka_celkem' => 'decimal:2',
        'castka_dph' => 'decimal:2',
        'adresni' => 'boolean',
        'overeno_adresat' => 'boolean',
    ];

    public function firma(): BelongsTo
    {
        return $this->belongsTo(Firma::class, 'firma_ico', 'ico');
    }

    public function dodavatel(): BelongsTo
    {
        return $this->belongsTo(Dodavatel::class, 'dodavatel_ico', 'ico');
    }
}
