<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Firma extends Model
{
    protected $table = 'sys_firmy';
    protected $primaryKey = 'ico';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'ico', 'nazev', 'dic', 'ulice', 'mesto', 'psc',
        'email', 'telefon', 'je_ucetni',
    ];

    protected $casts = [
        'je_ucetni' => 'boolean',
    ];

    public function doklady(): HasMany
    {
        return $this->hasMany(Doklad::class, 'firma_ico', 'ico');
    }

    public function klienti()
    {
        return $this->belongsToMany(Firma::class, 'sys_ucetni_vazby', 'ucetni_ico', 'klient_ico')
            ->wherePivot('stav', 'schvaleno');
    }

    public function ucetni()
    {
        return $this->belongsToMany(Firma::class, 'sys_ucetni_vazby', 'klient_ico', 'ucetni_ico')
            ->wherePivot('stav', 'schvaleno');
    }
}
