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
        'dodavatel_nazev', 'odberatel_ico', 'odberatel_nazev', 'cislo_dokladu',
        'variabilni_symbol', 'cislo_uctu', 'iban', 'zpusob_platby', 'reverse_charge',
        'datum_vystaveni', 'datum_prijeti', 'duzp', 'datum_splatnosti',
        'castka_celkem', 'castka_zaklad', 'mena', 'castka_dph', 'kategorie', 'poznamka',
        'adresni', 'overeno_adresat', 'raw_text', 'raw_ai_odpoved',
        'stav', 'druh', 'chybova_zprava', 'zdroj', 'nahral', 'duplicita_id',
        'typ_dokladu', 'kvalita', 'kvalita_poznamka', 'poradi_v_souboru',
        'google_drive_file_id', 'google_drive_ucetni_file_id', 'google_drive_nahrano_at',
    ];

    protected $casts = [
        'datum_vystaveni' => 'date',
        'datum_prijeti' => 'date',
        'duzp' => 'date',
        'datum_splatnosti' => 'date',
        'castka_celkem' => 'decimal:2',
        'castka_zaklad' => 'decimal:2',
        'castka_dph' => 'decimal:2',
        'adresni' => 'boolean',
        'overeno_adresat' => 'boolean',
        'reverse_charge' => 'boolean',
    ];

    /** Záznam se uložil, ale nikdy se nevytěžil. */
    public function jeNevytezeny(): bool
    {
        return $this->stav === 'ulozeno';
    }

    /**
     * Jde u záznamu spustit vytěžení?
     *
     * Platí pro dokument (ten se nevytěžuje automaticky) i pro doklad, který
     * zůstal nevytěžený — třeba proto, že firmě došly kredity. Chybný záznam
     * jde zkusit znovu; hotový doklad se přetěžovat nemá.
     */
    public function lzeVytezit(): bool
    {
        return $this->cesta_souboru !== null
            && in_array($this->stav, ['ulozeno', 'chyba'], true);
    }

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

    public function polozky(): HasMany
    {
        return $this->hasMany(Polozka::class, 'doklad_id')->orderBy('poradi');
    }
}
