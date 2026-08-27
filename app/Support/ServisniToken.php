<?php

namespace App\Support;

/**
 * Ověření tokenu servisních rout (cron, spuštění migrací z deploye).
 *
 * Hodnota je v .env (SERVISNI_TOKEN), ne v kódu — repozitář je veřejný.
 * Když token není nastavený, servisní routy se tváří jako neexistující,
 * ať se nedají volat prázdným parametrem.
 *
 * Dřív to byla obyčejná funkce přímo v `routes/web.php`. To fungovalo jen do
 * chvíle, než se routy načetly podruhé v témže procesu (testy) — PHP pak spadl
 * na „Cannot redeclare function".
 */
class ServisniToken
{
    public static function plati(string $token): bool
    {
        $ocekavany = (string) config('services.servisni_token');

        return $ocekavany !== '' && hash_equals($ocekavany, $token);
    }
}
