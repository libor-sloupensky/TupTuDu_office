<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UcetniVazba extends Model
{
    protected $table = 'sys_ucetni_vazby';

    protected $fillable = [
        'ucetni_ico',
        'klient_ico',
        'stav',
        'zadost_odeslana_at',
    ];

    protected $casts = [
        'zadost_odeslana_at' => 'datetime',
    ];

    public function ucetniFirma(): BelongsTo
    {
        return $this->belongsTo(Firma::class, 'ucetni_ico', 'ico');
    }

    public function klientFirma(): BelongsTo
    {
        return $this->belongsTo(Firma::class, 'klient_ico', 'ico');
    }
}
