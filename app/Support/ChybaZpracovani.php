<?php

namespace App\Support;

/**
 * Převádí technické chyby zpracování na větu, které rozumí uživatel.
 *
 * Text se ukládá k dokladu a zobrazuje v seznamu, takže nesmí obsahovat
 * odpověď API ani hlášku knihovny. Podrobnosti zůstávají v logu — tam je
 * hledá ten, kdo chybu řeší, a tomu je syrový text k užitku.
 *
 * Věty říkají i to, co s tím uživatel zmůže. Když je příčina na naší straně
 * (vyčerpaný kredit, neplatný přístup), nemá smysl mu radit „zkuste znovu" —
 * má vědět, že doklad je v bezpečí a co se bude dít.
 */
final class ChybaZpracovani
{
    public static function popis(\Throwable|string $chyba): string
    {
        $text = $chyba instanceof \Throwable ? $chyba->getMessage() : $chyba;
        $maly = mb_strtolower($text);

        // Vyčerpaný kredit u Anthropicu. Uživatel s tím nic nesvede, ale má
        // vědět, že o doklad nepřišel.
        if (str_contains($maly, 'credit balance is too low') || str_contains($maly, 'billing')) {
            return 'Rozpoznávání dokladů je dočasně pozastavené — službě došel kredit. '
                . 'Doklad je uložený a jakmile bude kredit doplněný, stačí zpracování spustit znovu.';
        }

        if (str_contains($maly, 'authentication_error') || str_contains($maly, 'invalid x-api-key')
            || str_contains($maly, 'http 401') || str_contains($maly, 'http 403')) {
            return 'Přístup ke službě pro rozpoznávání není platný. Doklad je uložený; '
                . 'dejte nám prosím vědět, ať to spravíme.';
        }

        if (str_contains($maly, 'rate_limit') || str_contains($maly, 'overloaded')
            || str_contains($maly, 'http 429') || str_contains($maly, 'http 529')) {
            return 'Služba pro rozpoznávání je právě přetížená. Zkuste zpracování spustit znovu za chvíli.';
        }

        if (str_contains($maly, 'timeout') || str_contains($maly, 'timed out')) {
            return 'Zpracování trvalo příliš dlouho. Zkuste ho prosím spustit znovu.';
        }

        if (str_contains($maly, 's3') || str_contains($maly, 'storage')) {
            return 'Soubor se nepodařilo uložit do úložiště. Zkuste to prosím znovu.';
        }

        if (str_contains($maly, 'parsovat') || str_contains($maly, 'json')) {
            return 'Odpověď služby se nepodařilo přečíst. Zkuste zpracování spustit znovu.';
        }

        if (str_contains($maly, 'textract')) {
            return 'Text se z dokladu nepodařilo přečíst. Zkuste kvalitnější sken.';
        }

        // Obecná chyba volání API — konkrétní důvod je v logu.
        if (str_contains($maly, 'claude vision api') || str_contains($maly, 'api chyba')) {
            return 'Služba pro rozpoznávání dokladů je dočasně nedostupná. Zkuste to prosím za chvíli znovu.';
        }

        return 'Doklad se nepodařilo zpracovat. Zkuste to prosím znovu.';
    }
}
