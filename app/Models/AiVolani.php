<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Jeden záznam o volání placené služby — Claude nebo Textract.
 *
 * Slouží ke znalosti skutečných nákladů a marže. S ceníkem pro zákazníka to
 * nesouvisí; ten je obchodní rozhodnutí.
 */
class AiVolani extends Model
{
    protected $table = 'sys_ai_volani';

    public $timestamps = false;

    protected $fillable = [
        'firma_ico', 'doklad_id', 'sluzba', 'model',
        'vstupni_tokens', 'vystupni_tokens', 'cache_read_tokens', 'cache_create_tokens',
        'stranky', 'cena_usd', 'uspesne', 'http_status', 'trvani_ms', 'poznamka', 'vytvoreno',
    ];

    protected $casts = [
        'cena_usd' => 'decimal:6',
        'uspesne' => 'boolean',
        'vytvoreno' => 'datetime',
    ];
}
