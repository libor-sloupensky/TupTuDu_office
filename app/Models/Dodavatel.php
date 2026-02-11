<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dodavatel extends Model
{
    protected $table = 'sys_dodavatele';
    protected $primaryKey = 'ico';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'ico', 'nazev', 'dic', 'ulice', 'mesto', 'psc',
    ];

    public function doklady(): HasMany
    {
        return $this->hasMany(Doklad::class, 'dodavatel_ico', 'ico');
    }
}
