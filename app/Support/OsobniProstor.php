<?php

namespace App\Support;

use App\Models\Firma;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Osobní prostor uživatele pro soukromé doklady.
 *
 * Uvnitř je to obyčejný řádek v `sys_firmy`, jen místo IČO má vlastní kód
 * a příznak `je_osobni`. Všechno, co na firmu navazuje — nahrávání, hledání,
 * mobilní aplikace, e-mailový příjem, záloha na Disk — tím pádem funguje beze
 * změny a nemusí o osobním prostoru vůbec vědět.
 *
 * Ven se naopak nedostane: partnerské API i přidávání klientů účetní firmě
 * přijímají jen osmimístné IČO, takže osobní prostor nejde napojit ani nikomu
 * zpřístupnit. Vidí ho jen jeho vlastník.
 */
final class OsobniProstor
{
    /** Předpona kódu. Odlišuje osobní prostor od IČO na první pohled. */
    public const PREDPONA = 'OS';

    /** Kolik znaků následuje po předponě. Celkem tedy deset míst, IČO má osm. */
    private const DELKA = 8;

    /**
     * Znaky bez těch, které si lze splést při čtení nahlas nebo z papíru:
     * chybí 0/O, 1/I/L a 5/S.
     */
    private const ZNAKY = '2346789ABCDEFGHJKMNPQRTUVWXYZ';

    /**
     * Vzor kódu se skládá z téže abecedy jako generátor, ať se nemůžou rozejít.
     * Napsat ho ručně by znamenalo mít stejnou věc na dvou místech.
     */
    public static function vzor(): string
    {
        return '/^' . self::PREDPONA . '[' . preg_quote(self::ZNAKY, '/') . ']{' . self::DELKA . '}$/';
    }

    public static function jeKod(string $identifikator): bool
    {
        return (bool) preg_match(self::vzor(), $identifikator);
    }

    /** Osobní prostor uživatele, i když je zrovna vypnutý. */
    public static function proUzivatele(User $user): ?Firma
    {
        return Firma::where('je_osobni', true)
            ->where('vlastnik_user_id', $user->id)
            ->first();
    }

    /**
     * Vrátí osobní prostor uživatele a v případě potřeby ho založí.
     *
     * Volá se z míst, kde se prostor nabízí (výběr firmy, nastavení účtu).
     * Zakládá se tedy až při prvním použití, ne dávkově pro celou databázi.
     */
    public static function zajisti(User $user): Firma
    {
        $prostor = self::proUzivatele($user);

        if ($prostor) {
            return $prostor;
        }

        $kod = self::vygenerujKod();

        $prostor = Firma::create([
            'ico' => $kod,
            'nazev' => 'Osobní doklady',
            'je_osobni' => true,
            'vlastnik_user_id' => $user->id,
            'osobni_aktivni' => true,
            'email_doklady' => $kod . '@' . config('mail.doklady_domain', 'tuptudu.cz'),
            'email_system_aktivni' => true,
            // Osobní prostor je zdarma: text se vyčte, ale AI se nevolá.
            'uroven_zpracovani' => 'vycteni',
        ]);

        Firma::seedDefaultKategorie($prostor->ico);

        $user->firmy()->attach($prostor->ico, [
            'role' => 'firma',
            'interni_role' => 'superadmin',
        ]);

        return $prostor;
    }

    /** Zapne nebo vypne nabízení osobního prostoru. Doklady zůstávají. */
    public static function prepni(User $user, bool $zapnuto): Firma
    {
        $prostor = self::zajisti($user);
        $prostor->update(['osobni_aktivni' => $zapnuto]);

        return $prostor->fresh();
    }

    /**
     * Kód, který se nemůže potkat s IČO — jiná délka i jiná abeceda.
     *
     * Opakuje se, dokud nenarazí na volný; při dvaceti devíti znacích na osmi
     * místech je shoda krajně nepravděpodobná, ale spoléhat se na to nebudeme.
     */
    public static function vygenerujKod(): string
    {
        do {
            $kod = self::PREDPONA;
            for ($i = 0; $i < self::DELKA; $i++) {
                $kod .= self::ZNAKY[random_int(0, strlen(self::ZNAKY) - 1)];
            }
        } while (Firma::whereKey($kod)->exists());

        return $kod;
    }
}
