<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable;

    protected $table = 'sys_users';

    protected $fillable = [
        'jmeno',
        'prijmeni',
        'email',
        'telefon',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function firmy(): BelongsToMany
    {
        return $this->belongsToMany(Firma::class, 'sys_user_firma', 'user_id', 'firma_ico')
            ->withPivot('role', 'interni_role')
            ->withTimestamps();
    }

    public function getCeleJmenoAttribute(): string
    {
        return "{$this->jmeno} {$this->prijmeni}";
    }

    public function aktivniFirma(): ?Firma
    {
        $ico = session('aktivni_firma_ico');
        if ($ico && $this->firmy()->where('ico', $ico)->exists()) {
            return Firma::find($ico);
        }
        return $this->firmy()->first();
    }

    public function maRoli(string $role, ?string $ico = null): bool
    {
        $ico = $ico ?? session('aktivni_firma_ico');
        return $this->firmy()->where('ico', $ico)->wherePivot('role', $role)->exists();
    }

    public function jeSuperadmin(?string $ico = null): bool
    {
        $ico = $ico ?? session('aktivni_firma_ico');
        return $this->firmy()->where('ico', $ico)->wherePivot('interni_role', 'superadmin')->exists();
    }

    public function dostupneIco(): array
    {
        $icos = $this->firmy()->pluck('ico')->toArray();

        $ucetniIcos = $this->firmy()->wherePivot('role', 'ucetni')->pluck('ico')->toArray();
        foreach ($ucetniIcos as $uIco) {
            $firma = Firma::find($uIco);
            if ($firma) {
                $klienti = $firma->klienti()->pluck('ico')->toArray();
                $icos = array_merge($icos, $klienti);
            }
        }

        return array_unique($icos);
    }
}
