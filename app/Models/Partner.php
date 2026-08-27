<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

/**
 * Partner — firma, která nástroj nabízí dál svým klientům.
 *
 * K dokladům napojených firem se partner nedostane. Vidí, které firmy přivedl,
 * a (až budou) jejich spotřebu. Nic víc.
 */
class Partner extends Model
{
    protected $table = 'sys_partneri';

    protected $fillable = [
        'nazev',
        'kontaktni_email',
        'api_klic_hash',
        'aktivni',
    ];

    protected $casts = [
        'aktivni' => 'boolean',
    ];

    protected $hidden = [
        'api_klic_hash',
    ];

    /** Firmy, které má partner napojené právě teď. */
    public function firmy(): BelongsToMany
    {
        return $this->belongsToMany(Firma::class, 'sys_firma_partner', 'partner_id', 'firma_ico', 'id', 'ico')
            ->withPivot(['napojeno_at', 'odpojeno_at'])
            ->wherePivotNull('odpojeno_at');
    }

    /** Všechna napojení včetně ukončených — kvůli historii. */
    public function vsechnaNapojeni(): BelongsToMany
    {
        return $this->belongsToMany(Firma::class, 'sys_firma_partner', 'partner_id', 'firma_ico', 'id', 'ico')
            ->withPivot(['napojeno_at', 'odpojeno_at']);
    }

    /**
     * Vygeneruje nový API klíč a uloží jeho otisk.
     *
     * Vrací klíč v čitelné podobě — je to jediná chvíle, kdy ho lze přečíst.
     * V databázi zůstane jen SHA-256; klíč je dost dlouhý a náhodný na to, aby
     * se proti němu nedal vést slovníkový útok, takže sůl není potřeba a otisk
     * jde rovnou použít k vyhledání.
     */
    public function vygenerujApiKlic(): string
    {
        $klic = 'ptp_' . Str::random(48);
        $this->api_klic_hash = self::otisk($klic);
        $this->save();

        return $klic;
    }

    public static function otisk(string $klic): string
    {
        return hash('sha256', $klic);
    }

    /** Najde aktivního partnera podle API klíče, nebo null. */
    public static function podleApiKlice(string $klic): ?self
    {
        if ($klic === '') {
            return null;
        }

        return self::where('api_klic_hash', self::otisk($klic))
            ->where('aktivni', true)
            ->first();
    }
}
