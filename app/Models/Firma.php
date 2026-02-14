<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Firma extends Model
{
    protected $table = 'sys_firmy';
    protected $primaryKey = 'ico';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'ico', 'nazev', 'dic', 'ulice', 'mesto', 'psc',
        'email', 'email_doklady', 'email_doklady_heslo', 'telefon', 'je_ucetni',
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

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'sys_user_firma', 'firma_ico', 'user_id')
            ->withPivot('role')
            ->withTimestamps();
    }
}
