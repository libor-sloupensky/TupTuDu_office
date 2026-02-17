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
        'perm_vkladat',
        'perm_upravovat',
        'perm_mazat',
    ];

    protected $casts = [
        'zadost_odeslana_at' => 'datetime',
        'perm_vkladat' => 'boolean',
        'perm_upravovat' => 'boolean',
        'perm_mazat' => 'boolean',
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
