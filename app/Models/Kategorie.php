<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Kategorie extends Model
{
    protected $table = 'fak_kategorie';

    protected $fillable = [
        'firma_ico',
        'nazev',
        'popis',
        'poradi',
    ];

    public function firma(): BelongsTo
    {
        return $this->belongsTo(Firma::class, 'firma_ico', 'ico');
    }
}
