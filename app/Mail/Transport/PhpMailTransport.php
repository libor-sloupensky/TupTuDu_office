<?php

namespace App\Mail\Transport;

use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

/**
 * Odesílání pošty přes PHP funkci mail().
 *
 * Český hosting blokuje odchozí SMTP na všech portech a zároveň zakazuje
 * proc_open, takže nefunguje ani vestavěný `sendmail` transport Symfony,
 * který si sendmail spouští jako proces. Jediná zbývající cesta ven je
 * mail(), kterou hosting obsluhuje vlastním handlerem (sendmail_path=php_mail).
 *
 * Zprávu neskládáme znovu — vezmeme hotový MIME výstup Symfony a rozdělíme
 * ho na hlavičky a tělo, takže přílohy i kódování zůstanou nedotčené.
 *
 * Omezení: mail() dostane příjemce jako jeden seznam, takže se nerozlišuje
 * Bcc. Aplikace posílá všechny zprávy jednomu adresátovi, takže to nevadí.
 */
class PhpMailTransport extends AbstractTransport
{
    protected function doSend(SentMessage $message): void
    {
        $raw = $message->toString();

        $oddelovac = str_contains($raw, "\r\n\r\n") ? "\r\n\r\n" : "\n\n";
        [$hlavicky, $telo] = explode($oddelovac, $raw, 2);

        $rozdelene = $this->rozdelHlavicky($hlavicky);

        $prijemci = array_map(
            fn ($adresa) => $adresa->toString(),
            $message->getEnvelope()->getRecipients()
        );

        $komu = $rozdelene['to'] ?: implode(', ', $prijemci);

        if ($komu === '') {
            throw new TransportException('Zpráva nemá příjemce.');
        }

        $odesilatel = $message->getEnvelope()->getSender()->getAddress();

        // PHP jinak přidá hlavičku X-PHP-Originating-Script s uid a cestou ke
        // skriptu. Spamové filtry ji berou jako signál strojově generované pošty
        // a zbytečně tím zvyšují skóre. Direktiva bývá PHP_INI_PERDIR, takže se
        // to nemusí povést — pak je potřeba ji vypnout v nastavení hostingu.
        @ini_set('mail.add_x_header', '0');

        // Pátý parametr nastavuje obálkového odesílatele kvůli SPF. Některé
        // hostingy ho zakazují, proto se při neúspěchu zkusí i bez něj.
        $odeslano = @mail($komu, $rozdelene['subject'], $telo, $rozdelene['zbytek'], '-f' . $odesilatel);

        if (!$odeslano) {
            $odeslano = @mail($komu, $rozdelene['subject'], $telo, $rozdelene['zbytek']);
        }

        if (!$odeslano) {
            throw new TransportException('Funkce mail() odmítla zprávu odeslat.');
        }
    }

    /**
     * Oddělí hlavičky To a Subject — ty se předávají mail() zvlášť a v seznamu
     * dalších hlaviček by se zdvojily. Respektuje zalomení dlouhých hlaviček
     * do pokračovacích řádků (začínají mezerou nebo tabulátorem).
     */
    private function rozdelHlavicky(string $hlavicky): array
    {
        $to = '';
        $subject = '';
        $zbytek = [];
        $aktualni = null;

        foreach (preg_split('/\r?\n/', $hlavicky) as $radek) {
            $pokracovani = $radek !== '' && ($radek[0] === ' ' || $radek[0] === "\t");

            if (!$pokracovani) {
                $nazev = strtolower(strtok($radek, ':'));
                $aktualni = in_array($nazev, ['to', 'subject'], true) ? $nazev : 'zbytek';
            }

            $hodnota = $pokracovani ? $radek : trim((string) strstr($radek, ':'), ": \t");

            match ($aktualni) {
                'to' => $to .= ($to === '' ? '' : ' ') . trim($hodnota),
                'subject' => $subject .= ($subject === '' ? '' : ' ') . trim($hodnota),
                default => $zbytek[] = $radek,
            };
        }

        return [
            'to' => $to,
            'subject' => $subject,
            'zbytek' => implode("\r\n", $zbytek),
        ];
    }

    public function __toString(): string
    {
        return 'php_mail://default';
    }
}
