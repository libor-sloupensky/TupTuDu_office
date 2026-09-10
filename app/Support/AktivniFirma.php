<?php

namespace App\Support;

use Illuminate\Support\Facades\Cookie;

/**
 * Firma, kterou má uživatel právě přepnutou (IČO).
 *
 * Nežije v session, ale ve vlastní cookie. Session je jeden blob, který si každý
 * požadavek na začátku načte a na konci celý zapíše zpátky — když tedy během
 * pomalého požadavku (nahrávání dokladu s vytěžením trvá i desítky sekund)
 * uživatel přepne firmu, odpověď toho pomalého požadavku přepíše session svým
 * starším snímkem a firma se tiše vrátí zpátky. Databázový driver to zúžil na
 * požadavky rozběhnuté v okamžiku přepnutí, ale neodstranil. Projevovalo se
 * to tak, že v seznamu dokladů jedné firmy vyskočil doklad firmy druhé.
 *
 * Vlastní cookie nastavuje jedině přepnutí (a přihlášení), takže ji žádný jiný
 * požadavek nemůže přepsat. Laravel ji šifruje a podepisuje, uživatel si ji
 * nepodvrhne — a oprávnění k firmě se stejně vždy ověřuje na místě použití.
 *
 * Session se stále plní jako záloha (starší testy a přechod z cookie na
 * databázi), přednost má ale cookie.
 */
final class AktivniFirma
{
    public const COOKIE = 'aktivni_firma_ico';
    private const SESSION = 'aktivni_firma_ico';

    /** Hodnota nastavená v tomto požadavku — cookie z fronty ještě čitelná není. */
    private static ?\WeakMap $vPozadavku = null;

    public static function ico(): ?string
    {
        $request = app()->bound('request') ? app('request') : null;

        if ($request !== null && self::$vPozadavku !== null && isset(self::$vPozadavku[$request])) {
            return self::$vPozadavku[$request];
        }

        $zCookie = $request?->cookie(self::COOKIE);
        // Osmimístné IČO, nebo kód osobního prostoru. Oprávnění se stejně
        // ověřuje až v místě použití, tohle je jen kontrola tvaru.
        if (is_string($zCookie)
            && (preg_match('/^\d{8}$/', $zCookie) || OsobniProstor::jeKod($zCookie))) {
            return $zCookie;
        }

        $zeSession = session(self::SESSION);

        return is_string($zeSession) && $zeSession !== '' ? $zeSession : null;
    }

    public static function nastav(string $ico): void
    {
        session([self::SESSION => $ico]);
        Cookie::queue(Cookie::make(self::COOKIE, $ico, 60 * 24 * 365));

        if (app()->bound('request')) {
            self::$vPozadavku ??= new \WeakMap();
            self::$vPozadavku[app('request')] = $ico;
        }
    }

    /** Při odhlášení — ať se firma nepřenese dalšímu, kdo se přihlásí ve stejném prohlížeči. */
    public static function zapomen(): void
    {
        session()->forget(self::SESSION);
        Cookie::queue(Cookie::forget(self::COOKIE));

        if (app()->bound('request') && self::$vPozadavku !== null) {
            unset(self::$vPozadavku[app('request')]);
        }
    }
}
