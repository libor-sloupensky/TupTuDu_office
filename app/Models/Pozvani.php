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

    /**
     * Přijme všechny platné čekající pozvánky pro e-mail uživatele.
     *
     * Uživatel může mít pozvánek víc (do různých firem), ale registrace
     * zpracuje jen tu jednu, přes kterou přišel. Zbytek by jinak zůstal
     * viset — proto se dojíždí při přihlášení a po ověření e-mailu.
     *
     * Přijímáme jen ověřenému uživateli — jinak by stačilo zaregistrovat se
     * na cizí e-mail a pozvánka do cizí firmy by se přijala sama.
     *
     * Pokud už vazba na firmu existuje, roli neměníme (aby pozvánka
     * nepřepsala superadmina na správce) — jen pozvánku uzavřeme.
     *
     * @return int počet přijatých pozvánek
     */
    public static function prijmoutCekajiciPro(User $user): int
    {
        if (! $user->hasVerifiedEmail()) {
            return 0;
        }

        $cekajici = static::where('email', $user->email)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->get();

        $prijato = 0;

        foreach ($cekajici as $pozvani) {
            if (! Firma::find($pozvani->firma_ico)) {
                continue; // firma mezitím zanikla — pozvánku necháme být
            }

            if (! $user->firmy()->where('ico', $pozvani->firma_ico)->exists()) {
                $user->firmy()->attach($pozvani->firma_ico, [
                    'role' => 'firma',
                    'interni_role' => $pozvani->interni_role,
                ]);
            }

            $pozvani->update(['accepted_at' => now()]);
            $prijato++;
        }

        return $prijato;
    }
}
