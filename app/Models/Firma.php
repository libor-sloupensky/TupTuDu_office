<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Firma extends Model
{
    protected $table = 'sys_firmy';
    protected $primaryKey = 'ico';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'ico', 'nazev', 'dic', 'ulice', 'mesto', 'psc',
        'email', 'email_doklady', 'email_doklady_heslo', 'telefon', 'je_ucetni',
        'email_system_aktivni', 'email_vlastni_aktivni',
        'email_vlastni', 'email_vlastni_host', 'email_vlastni_port',
        'email_vlastni_sifrovani', 'email_vlastni_uzivatel', 'email_vlastni_heslo',
        'google_drive_aktivni', 'google_refresh_token', 'google_folder_id', 'google_drive_sablona',
    ];

    protected $casts = [
        'je_ucetni' => 'boolean',
        'email_system_aktivni' => 'boolean',
        'email_vlastni_aktivni' => 'boolean',
        'email_vlastni_port' => 'integer',
        'google_drive_aktivni' => 'boolean',
    ];

    public function doklady(): HasMany
    {
        return $this->hasMany(Doklad::class, 'firma_ico', 'ico');
    }

    public function klienti()
    {
        return $this->belongsToMany(Firma::class, 'sys_ucetni_vazby', 'ucetni_ico', 'klient_ico')
            ->wherePivot('stav', 'schvaleno');
    }

    public function ucetni()
    {
        return $this->belongsToMany(Firma::class, 'sys_ucetni_vazby', 'klient_ico', 'ucetni_ico')
            ->wherePivot('stav', 'schvaleno');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'sys_user_firma', 'firma_ico', 'user_id')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function kategorie(): HasMany
    {
        return $this->hasMany(Kategorie::class, 'firma_ico', 'ico');
    }

    public static function seedDefaultKategorie(string $ico): void
    {
        $defaults = [
            ['Pohonné hmoty', 'benzín, nafta, CNG, LPG, AdBlue', 1],
            ['Stravování', 'potraviny, restaurace, občerstvení', 2],
            ['Telekomunikace', 'telefon, internet, hosting', 3],
            ['Energie', 'elektřina, plyn, voda, teplo', 4],
            ['Doprava', 'jízdenky, parkování, mýtné, taxi, poštovné, ubytování', 5],
            ['Kancelářské potřeby', 'tonery, papír, drobný materiál', 6],
            ['Software', 'předplatné, cloudové služby, licence', 7],
            ['Opravy a údržba', 'servis, náhradní díly, revize', 8],
            ['Služby', 'poskytování služeb, účetnictví', 9],
            ['Reklama', 'inzerce, propagace, marketing', 10],
            ['Školení', 'kurzy, semináře, konference', 11],
            ['Pojištění', 'vozidla, majetek, odpovědnost', 12],
            ['Nájem', 'pronájem prostor, leasing', 13],
            ['Dokumenty', 'smlouvy, objednávky, upomínky, protokoly', 14],
            ['Ostatní', 'pokuty, penále', 15],
        ];

        foreach ($defaults as [$nazev, $popis, $poradi]) {
            Kategorie::create([
                'firma_ico' => $ico,
                'nazev' => $nazev,
                'popis' => $popis,
                'poradi' => $poradi,
            ]);
        }
    }
}
