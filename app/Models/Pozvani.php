<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pozvani extends Model
{
    protected $table = 'sys_pozvani';

    public $timestamps = false;

    protected $fillable = [
        'firma_ico',
        'jmeno',
        'email',
        'interni_role',
        'token',
        'expires_at',
        'accepted_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
    ];

    public function firma(): BelongsTo
    {
        return $this->belongsTo(Firma::class, 'firma_ico', 'ico');
    }

    public function jePlatna(): bool
    {
        return !$this->accepted_at && $this->expires_at->isFuture();
    }
}
