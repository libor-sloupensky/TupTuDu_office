<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Polozka extends Model
{
    protected $table = 'fak_polozky';

    protected $fillable = [
        'doklad_id', 'poradi', 'text',
        'mnozstvi', 'jednotka', 'cena_za_jednotku',
        'zaklad_dane', 'sazba_dph', 'castka_dph', 'castka_celkem',
    ];

    protected $casts = [
        'mnozstvi' => 'decimal:3',
        'cena_za_jednotku' => 'decimal:2',
        'zaklad_dane' => 'decimal:2',
        'sazba_dph' => 'decimal:2',
        'castka_dph' => 'decimal:2',
        'castka_celkem' => 'decimal:2',
    ];

    public function doklad(): BelongsTo
    {
        return $this->belongsTo(Doklad::class);
    }
}
