<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Doklad extends Model
{
    protected $table = 'fak_doklady';

    protected $fillable = [
        'firma_ico', 'dodavatel_ico', 'nazev_souboru', 'cesta_souboru', 'cesta_originalu', 'hash_souboru',
        'dodavatel_nazev', 'cislo_dokladu',
        'datum_vystaveni', 'datum_prijeti', 'duzp', 'datum_splatnosti',
        'castka_celkem', 'mena', 'castka_dph', 'kategorie',
        'adresni', 'overeno_adresat', 'raw_text', 'raw_ai_odpoved',
        'stav', 'chybova_zprava', 'zdroj', 'duplicita_id',
        'typ_dokladu', 'kvalita', 'kvalita_poznamka', 'poradi_v_souboru',
    ];

    protected $casts = [
        'datum_vystaveni' => 'date',
        'datum_prijeti' => 'date',
        'duzp' => 'date',
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

    public function duplicitaOriginal(): BelongsTo
    {
        return $this->belongsTo(Doklad::class, 'duplicita_id');
    }

    public function duplicity(): HasMany
    {
        return $this->hasMany(Doklad::class, 'duplicita_id');
    }
}
