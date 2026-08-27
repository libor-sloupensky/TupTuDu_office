<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Napojení firmy na partnera. Řádky se nemažou — ukončené napojení dostane
 * razítko `odpojeno_at` a zůstane v historii.
 */
class FirmaPartner extends Model
{
    protected $table = 'sys_firma_partner';

    protected $fillable = [
        'firma_ico',
        'partner_id',
        'napojeno_at',
        'odpojeno_at',
    ];

    protected $casts = [
        'napojeno_at' => 'datetime',
        'odpojeno_at' => 'datetime',
    ];

    public function firma(): BelongsTo
    {
        return $this->belongsTo(Firma::class, 'firma_ico', 'ico');
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }

    public function jeAktivni(): bool
    {
        return $this->odpojeno_at === null;
    }
}
