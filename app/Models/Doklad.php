<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Doklad extends Model
{
    protected $table = 'fak_doklady';

    protected $fillable = [
        'firma_ico', 'dodavatel_ico', 'nazev_souboru', 'cesta_souboru', 'cesta_originalu', 'hash_souboru',
        'dodavatel_nazev', 'odberatel_ico', 'odberatel_nazev', 'cislo_dokladu',
        'variabilni_symbol', 'cislo_uctu', 'iban', 'zpusob_platby', 'reverse_charge',
        'datum_vystaveni', 'datum_prijeti', 'duzp', 'datum_splatnosti',
        'castka_celkem', 'castka_zaklad', 'mena', 'castka_dph', 'kategorie', 'poznamka',
        'adresni', 'overeno_adresat', 'raw_text', 'raw_ai_odpoved',
        'stav', 'druh', 'chybova_zprava', 'zdroj', 'nahral', 'duplicita_id',
        'typ_dokladu', 'kvalita', 'kvalita_poznamka', 'poradi_v_souboru',
        'google_drive_file_id', 'google_drive_ucetni_file_id', 'google_drive_nahrano_at',
    ];

    protected $casts = [
        'datum_vystaveni' => 'date',
        'datum_prijeti' => 'date',
        'duzp' => 'date',
        'datum_splatnosti' => 'date',
        'castka_celkem' => 'decimal:2',
        'castka_zaklad' => 'decimal:2',
        'castka_dph' => 'decimal:2',
        'adresni' => 'boolean',
        'overeno_adresat' => 'boolean',
        'reverse_charge' => 'boolean',
    ];

    /** Krátká pole, kde se hledá i uprostřed slova. */
    private const SLOUPCE_HLEDANI = [
        'cislo_dokladu', 'dodavatel_nazev', 'nazev_souboru',
        'dodavatel_ico', 'nahral', 'odberatel_nazev',
    ];

    /** Nejkratší slovo, které se dostane do fulltextového indexu (InnoDB výchozí). */
    private const MIN_DELKA_SLOVA = 3;

    /**
     * Hledání v dokladu.
     *
     * Krátká strukturovaná pole se procházejí přes LIKE — jsou malá a hledá se
     * v nich i uprostřed slova, což je u čísel dokladů potřeba. Přepis dokladu
     * (`raw_text`) je naopak dlouhý a `LIKE '%…%'` na něm neumí použít žádný
     * index, takže by se s rostoucím počtem dokladů četla celá tabulka. Na něj
     * se proto jde fulltextovým indexem přes MATCH … AGAINST.
     *
     * Poddotaz tu není pro parádu: kdyby MATCH stálo přímo v OR vedle LIKE,
     * optimalizátor by index zahodil a bylo by to k ničemu.
     *
     * MATCH hledá od začátku slova, ne uprostřed — „servis" tedy nenajde
     * „pneuservis". U výrazů kratších než tři znaky, které se do indexu vůbec
     * nedostanou, se proto i na přepis použije LIKE.
     */
    public function scopeHledej(Builder $dotaz, string $vyraz, bool $iUprostred = false): Builder
    {
        $vyraz = trim($vyraz);

        if ($vyraz === '') {
            return $dotaz;
        }

        return $dotaz->where(function (Builder $sub) use ($vyraz, $iUprostred) {
            foreach (self::SLOUPCE_HLEDANI as $sloupec) {
                $sub->orWhere($sloupec, 'like', '%' . $vyraz . '%');
            }

            $fulltext = $iUprostred ? null : self::vyrazProFulltext($vyraz);

            if ($fulltext === null) {
                $sub->orWhere('raw_text', 'like', '%' . $vyraz . '%');

                return;
            }

            $sub->orWhereIn('id', function ($poddotaz) use ($fulltext) {
                $poddotaz->select('id')
                    ->from('fak_doklady')
                    ->whereRaw('MATCH(raw_text) AGAINST (? IN BOOLEAN MODE)', [$fulltext]);
            });
        });
    }

    /**
     * Převede hledaný výraz do zápisu pro MATCH … AGAINST v boolean režimu.
     *
     * Všechna slova musí být v dokladu přítomná (`+`) a stačí shoda od začátku
     * slova (`*`). Vrací null, když ve výrazu nezůstane nic dost dlouhého —
     * volající pak sáhne po LIKE.
     */
    public static function vyrazProFulltext(string $vyraz): ?string
    {
        // Znaky, které mají v boolean režimu vlastní význam, by jinak dotaz
        // rozhodily nebo změnily smysl.
        $ocisteny = preg_replace('/[+\-><()~*"@]+/u', ' ', $vyraz);

        $slova = [];
        foreach (preg_split('/\s+/u', (string) $ocisteny, -1, PREG_SPLIT_NO_EMPTY) as $slovo) {
            if (mb_strlen($slovo) >= self::MIN_DELKA_SLOVA) {
                $slova[] = '+' . $slovo . '*';
            }
        }

        return $slova ? implode(' ', $slova) : null;
    }

    /** Záznam se uložil, ale nikdy se nevytěžil. */
    public function jeNevytezeny(): bool
    {
        return $this->stav === 'ulozeno';
    }

    /**
     * Jde u záznamu spustit vytěžení?
     *
     * Platí pro dokument (ten se nevytěžuje automaticky) i pro doklad, který
     * zůstal nevytěžený — třeba proto, že firmě došly kredity. Chybný záznam
     * jde zkusit znovu; hotový doklad se přetěžovat nemá.
     */
    public function lzeVytezit(): bool
    {
        return $this->cesta_souboru !== null
            && in_array($this->stav, ['ulozeno', 'chyba'], true);
    }

    public function firma(): BelongsTo
    {
        return $this->belongsTo(Firma::class, 'firma_ico', 'ico');
    }

    public function dodavatel(): BelongsTo
    {
        return $this->belongsTo(Dodavatel::class, 'dodavatel_ico', 'ico');
    }

    public function duplicitaOriginal(): BelongsTo
    {
        return $this->belongsTo(Doklad::class, 'duplicita_id');
    }

    public function duplicity(): HasMany
    {
        return $this->hasMany(Doklad::class, 'duplicita_id');
    }

    public function polozky(): HasMany
    {
        return $this->hasMany(Polozka::class, 'doklad_id')->orderBy('poradi');
    }
}
