<?php

namespace App\Console\Commands;

use App\Mail\OdpovedNaDoklad;
use App\Models\Firma;
use App\Services\DokladProcessor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Webklex\PHPIMAP\ClientManager;

class ProcessEmailDoklady extends Command
{
    protected $signature = 'doklady:process-email
                            {--ico= : Zpracovat jen konkrétní firmu podle IČO}
                            {--skip-system : Přeskočit systémovou schránku}
                            {--skip-custom : Přeskočit vlastní schránky}';

    protected $description = 'Stáhne a zpracuje doklady z emailových schránek (systémová + vlastní IMAP)';

    private const ALLOWED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png'];

    /** Záchrana pro přílohy bez názvu nebo bez přípony — typ z hlavičky. */
    private const PRIPONY_PODLE_TYPU = [
        'application/pdf' => 'pdf',
        'application/x-pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/jpg' => 'jpg',
        'image/png' => 'png',
    ];

    public function handle(): int
    {
        $processor = new DokladProcessor();
        $totalProcessed = 0;

        // 1. Systémová schránka (doklady@tuptudu.cz — mailový koš pro {IČO}@tuptudu.cz)
        if (!$this->option('skip-system')) {
            $totalProcessed += $this->processSystemMailbox($processor);
        }

        // 2. Vlastní IMAP schránky
        if (!$this->option('skip-custom')) {
            $totalProcessed += $this->processCustomMailboxes($processor);
        }

        $this->info("Celkem zpracováno: {$totalProcessed} příloh.");
        return self::SUCCESS;
    }

    private function processSystemMailbox(DokladProcessor $processor): int
    {
        $host = config('services.imap_system.host');
        $username = config('services.imap_system.username');
        $password = config('services.imap_system.password');

        if (!$host || !$username || !$password) {
            $this->warn('Systémová schránka: chybí konfigurace (IMAP_SYSTEM_*).');
            return 0;
        }

        $this->info("Systémová schránka: {$username}");

        try {
            $cm = new ClientManager();
            $client = $cm->make([
                'host' => $host,
                'port' => config('services.imap_system.port', 993),
                'encryption' => config('services.imap_system.encryption', 'ssl'),
                'validate_cert' => true,
                'username' => $username,
                'password' => $password,
                'protocol' => 'imap',
            ]);

            $client->connect();
            $folder = $client->getFolder('INBOX');
            $messages = $folder->query()->unseen()->get();
            $processed = 0;

            foreach ($messages as $message) {
                try {
                    $processed += $this->handleSystemMessage($message, $processor);
                } catch (\Throwable $e) {
                    $this->error("  Chyba zprávy: {$e->getMessage()}");
                    Log::error('ProcessEmailDoklady: message error', ['error' => $e->getMessage()]);

                    // Pokusit se odeslat chybovou odpověď odesílateli
                    $senderEmail = $this->extractSenderEmail($message);
                    if ($senderEmail) {
                        $ico = $this->extractIcoFromRecipients($message);
                        $subject = '';
                        try { $subject = $message->getSubject()?->toString() ?? ''; } catch (\Throwable $ex) {}
                        $analysis = $this->buildAnalysis(
                            icoFound: !empty($ico),
                            firmaFound: false,
                            ico: $ico,
                            errors: ['Interní chyba při zpracování emailu'],
                        );
                        $this->tryAutoReply($analysis, $senderEmail, $ico ?: 'faktury', $subject);
                    }
                    $message->setFlag('Seen');
                }
            }

            $client->disconnect();
            $this->info("  Systémová schránka: zpracováno {$processed} příloh.");
            return $processed;
        } catch (\Exception $e) {
            $this->error("  Systémová schránka — chyba: {$e->getMessage()}");
            Log::error('ProcessEmailDoklady: system mailbox error', [
                'error' => $e->getMessage(),
            ]);
            return 0;
        }
    }

    /**
     * Zpracuje jednu zprávu ze systémové schránky s auto-reply logikou.
     */
    private function handleSystemMessage($message, DokladProcessor $processor): int
    {
        // Extract sender info
        $senderEmail = $this->extractSenderEmail($message);
        $originalSubject = '';
        try {
            $subject = $message->getSubject();
            $originalSubject = $subject ? $subject->toString() : '';
        } catch (\Throwable $e) {}

        // 1. Extract IČO from recipients
        $ico = $this->extractIcoFromRecipients($message);

        if (!$ico) {
            $this->line("  Přeskakuji — nelze určit IČO z příjemce.");
            // Zpráva se označí jako přečtená a zmizí z dohledu, takže bez záznamu
            // v logu není jak dohledat, proč se doklad nenačetl.
            Log::warning('ProcessEmailDoklady: nelze určit IČO z příjemce', [
                'od' => $senderEmail,
                'predmet' => $originalSubject,
                'prijemci' => $this->popisPrijemcu($message),
            ]);
            if ($senderEmail) {
                $analysis = $this->buildAnalysis(icoFound: false);
                $this->tryAutoReply($analysis, $senderEmail, 'faktury', $originalSubject);
            }
            $message->setFlag('Seen');
            return 0;
        }

        // Filter by --ico option
        if ($this->option('ico') && $this->option('ico') !== $ico) {
            $message->setFlag('Seen');
            return 0;
        }

        // 2. Find firma
        $firma = Firma::where('ico', $ico)->where('email_system_aktivni', true)->first();

        if (!$firma) {
            $this->line("  Přeskakuji IČO {$ico} — firma nemá aktivní systémový email.");
            Log::warning('ProcessEmailDoklady: firma nenalezena nebo nemá aktivní systémový email', [
                'ico' => $ico,
                'od' => $senderEmail,
                'predmet' => $originalSubject,
            ]);
            if ($senderEmail) {
                $analysis = $this->buildAnalysis(icoFound: true, firmaFound: false, ico: $ico);
                $this->tryAutoReply($analysis, $senderEmail, $ico, $originalSubject);
            }
            $message->setFlag('Seen');
            return 0;
        }

        // 3. Collect all message parts
        $parts = $this->collectMessageParts($message);

        // 4. Extract email body
        $bodyText = '';
        try {
            $bodyText = trim($message->getTextBody()?->toString() ?? '');
            if (empty($bodyText)) {
                $htmlBody = $message->getHTMLBody()?->toString() ?? '';
                $bodyText = trim(strip_tags($htmlBody));
            }
        } catch (\Throwable $e) {}

        // 5. Process valid attachments only (inline images = loga, podpisy, vizitky)
        $results = $this->processFiles(
            $parts['valid'],
            $firma,
            $processor,
            $senderEmail
        );

        // 6. Build analysis and decide reply
        $analysis = $this->buildAnalysis(
            icoFound: true,
            firmaFound: true,
            ico: $ico,
            processedOk: $results['processed_ok'],
            errors: $results['errors'],
            duplicates: $results['duplicates'],
            invalidAttachments: $parts['invalid'],
            hasBody: !empty($bodyText),
            bodyText: mb_substr($bodyText, 0, 500),
        );

        // 7. Auto-reply if needed
        if ($this->shouldReply($analysis) && $senderEmail) {
            $this->tryAutoReply($analysis, $senderEmail, $ico, $originalSubject);
        }

        $message->setFlag('Seen');
        return $results['processed_ok'];
    }

    /**
     * Sbírá všechny části zprávy — klasické přílohy, inline obrázky, nepodporované formáty.
     */
    private function collectMessageParts($message): array
    {
        $valid = [];
        $inlineImages = [];
        $invalid = [];

        try {
            $attachments = $message->getAttachments();
        } catch (\Throwable $e) {
            return compact('valid', 'inlineImages', 'invalid');
        }

        foreach ($attachments as $attachment) {
            $disposition = strtolower($attachment->getDisposition() ?? 'attachment');
            $contentType = strtolower($attachment->getContentType() ?? '');
            $name = $attachment->getName() ?? '';
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

            // Inline obrázky přeskočit vždy (loga, podpisy, vložené screenshoty)
            if ($disposition === 'inline' && str_starts_with($contentType, 'image/')) {
                $inlineImages[] = ['attachment' => $attachment, 'name' => $name];
                continue;
            }

            // Přeposlaná zpráva jako příloha — doklad je schovaný uvnitř ní.
            // Poštovní klienti takhle přeposílají běžně, bez rozbalení bychom
            // viděli jedinou přílohu typu .eml a doklad by se ztratil.
            if ($contentType === 'message/rfc822' || $ext === 'eml') {
                $vnorene = $this->prilohyVnorenehoDopisu($attachment);
                $valid = array_merge($valid, $vnorene['valid']);
                $invalid = array_merge($invalid, $vnorene['invalid']);
                continue;
            }

            // Když příloha nemá název nebo je bez přípony, odvodíme typ z hlavičky.
            // Bez toho takové přílohy propadly úplně — ani se nezpracovaly,
            // ani se neobjevily v odpovědi mezi nepodporovanými soubory.
            if (!in_array($ext, self::ALLOWED_EXTENSIONS, true)
                && isset(self::PRIPONY_PODLE_TYPU[$contentType])) {
                $ext = self::PRIPONY_PODLE_TYPU[$contentType];
                // Příponu doplnit i do názvu — podle ní se soubor dál zpracovává
                $name = ($name !== '' ? $name : 'priloha') . '.' . $ext;
            }

            if (in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
                $valid[] = ['attachment' => $attachment, 'name' => $name];
            } else {
                $invalid[] = $name !== '' ? $name : ($contentType ?: 'příloha bez názvu');
            }
        }

        return compact('valid', 'inlineImages', 'invalid');
    }

    /**
     * Rozbalí přeposlaný dopis a vrátí jeho přílohy roztříděné stejně jako
     * u běžné zprávy. Zanořuje se jen o úroveň níž — přeposlání přeposlání
     * je vzácné a hlubší rekurze by při poškozené zprávě mohla zacyklit.
     */
    private function prilohyVnorenehoDopisu($attachment): array
    {
        try {
            $vnitrni = \Webklex\PHPIMAP\Message::fromString($attachment->getContent());
            $casti = $this->collectMessageParts($vnitrni);

            return ['valid' => $casti['valid'], 'invalid' => $casti['invalid']];
        } catch (\Throwable $e) {
            Log::warning('ProcessEmailDoklady: přeposlanou zprávu nelze rozbalit', [
                'chyba' => $e->getMessage(),
            ]);

            return ['valid' => [], 'invalid' => [$attachment->getName() ?: 'přeposlaná zpráva']];
        }
    }

    /**
     * Zpracuje pole příloh přes DokladProcessor.
     */
    private function processFiles(array $files, Firma $firma, DokladProcessor $processor, ?string $senderEmail): array
    {
        $processedOk = 0;
        $errors = [];
        $duplicates = [];

        foreach ($files as $file) {
            $attachment = $file['attachment'];
            $originalName = $file['name'];

            try {
                $content = $attachment->getContent();
                $tempPath = tempnam(sys_get_temp_dir(), 'doklad_');
                file_put_contents($tempPath, $content);

                $fileHash = hash_file('sha256', $tempPath);

                if ($processor->isDuplicate($fileHash, $firma->ico)) {
                    $this->line("    Přeskakuji duplicitu: {$originalName}");
                    $duplicates[] = $originalName;
                    unlink($tempPath);
                    continue;
                }

                $vysledky = $processor->process(
                    $tempPath,
                    $originalName,
                    $firma,
                    $fileHash,
                    'email'
                );

                foreach ($vysledky as $dok) {
                    if ($senderEmail) {
                        $dok->update(['nahral' => $senderEmail]);
                    }
                    $this->line("    Zpracován: {$originalName} -> doklad #{$dok->id} ({$dok->stav})");
                }
                $processedOk++;
            } catch (\Exception $e) {
                $this->error("    Chyba při zpracování {$originalName}: {$e->getMessage()}");
                $errors[] = $originalName . ': ' . $e->getMessage();
                Log::error("ProcessEmailDoklady: attachment error", [
                    'firma_ico' => $firma->ico,
                    'attachment' => $originalName,
                    'error' => $e->getMessage(),
                ]);
            } finally {
                if (isset($tempPath) && file_exists($tempPath)) {
                    @unlink($tempPath);
                }
            }
        }

        return ['processed_ok' => $processedOk, 'errors' => $errors, 'duplicates' => $duplicates];
    }

    /**
     * Sestaví analýzu emailu pro rozhodnutí o auto-reply.
     */
    private function buildAnalysis(
        bool $icoFound = true,
        bool $firmaFound = true,
        ?string $ico = null,
        int $processedOk = 0,
        array $errors = [],
        array $duplicates = [],
        array $invalidAttachments = [],
        bool $hasBody = false,
        string $bodyText = '',
    ): array {
        return [
            'ico_found' => $icoFound,
            'firma_found' => $firmaFound,
            'ico' => $ico,
            'processed_ok' => $processedOk,
            'errors' => $errors,
            'duplicates' => $duplicates,
            'invalid_attachments' => $invalidAttachments,
            'has_body' => $hasBody,
            'body_text' => $bodyText,
        ];
    }

    /**
     * Rozhodne zda odpovědět na email.
     */
    private function shouldReply(array $analysis): bool
    {
        // Always reply if IČO or firma not found (handled before this)
        if (!$analysis['ico_found'] || !$analysis['firma_found']) {
            return true;
        }

        // No reply if everything processed fine and no issues
        if ($analysis['processed_ok'] > 0
            && empty($analysis['errors'])
            && empty($analysis['invalid_attachments'])
            && empty($analysis['duplicates'])) {
            return false;
        }

        // Reply for all error/warning scenarios
        return true;
    }

    /**
     * Pokusí se vygenerovat a odeslat auto-reply.
     */
    private function tryAutoReply(array $analysis, string $toEmail, string $fromIco, string $originalSubject): void
    {
        try {
            $replyText = $this->generateAutoReply($analysis);
            $this->sendReply($replyText, $toEmail, $fromIco, $originalSubject);
            $this->line("    Auto-reply odeslán na {$toEmail}");
        } catch (\Throwable $e) {
            $this->warn("    Auto-reply selhal: {$e->getMessage()}");
            Log::warning('ProcessEmailDoklady: auto-reply failed', [
                'to' => $toEmail,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Generuje standardizovanou odpověď na email podle stavu zpracování.
     * Žádná AI — deterministické šablony.
     */
    private function generateAutoReply(array $analysis): string
    {
        $stav = $this->resolveReplyState($analysis);

        return match ($stav) {
            'ico_not_found' => $this->replyTemplate(
                'Nepodařilo se určit cílovou firmu.',
                'Pro správné doručení dokladů zasílejte na adresu ve formátu:',
                'ICO@' . config('mail.doklady_domain', 'tuptudu.cz'),
                'kde ICO je 8místné identifikační číslo firmy příjemce. Například: 12345678@' . config('mail.doklady_domain', 'tuptudu.cz') . '.'
            ),

            'firma_not_found' => $this->replyTemplate(
                "Firma s IČO {$analysis['ico']} není v systému TupTuDu registrována nebo nemá aktivní příjem dokladů emailem.",
                'Pokud jste zadali správné IČO, požádejte příjemce, aby si v nastavení firmy aktivoval příjem dokladů emailem.'
            ),

            'no_attachments' => $this->replyTemplate(
                'Váš email neobsahoval žádné přílohy s doklady.',
                'Tato schránka slouží výhradně k příjmu účetních dokladů (faktury, účtenky, smlouvy apod.).',
                'Podporované formáty: PDF, JPG, PNG (max 10 MB).',
                'Na textové zprávy bez příloh neodpovídáme — pro komunikaci kontaktujte příjemce přímo.'
            ),

            'invalid_format_only' => $this->replyTemplate(
                'Váš email obsahoval přílohy v nepodporovaném formátu.',
                'Přijaté soubory: ' . implode(', ', $analysis['invalid_attachments']),
                'Podporované formáty jsou pouze: PDF, JPG a PNG.',
                'Převeďte prosím soubory do některého z podporovaných formátů a zašlete znovu.'
            ),

            'all_duplicates' => $this->replyTemplate(
                'Zaslané doklady již byly dříve zpracovány a jsou evidovány v systému.',
                'Opakované zaslání stejného souboru je automaticky ignorováno.',
                'Pokud chcete doklad nahradit, smažte původní záznam v aplikaci a zašlete nový.'
            ),

            'all_errors' => $this->replyTemplate(
                'Při zpracování zaslaných dokladů došlo k chybě.',
                !empty($analysis['errors'])
                    ? 'Podrobnosti: ' . implode('; ', array_map(fn($e) => $this->sanitizeError($e), $analysis['errors']))
                    : null,
                'Zkuste prosím soubory zaslat znovu. Pokud problém přetrvává, kontaktujte příjemce.'
            ),

            'partial_ok' => $this->replyPartialOk($analysis),

            'processing_error' => $this->replyTemplate(
                'Při zpracování Vašeho emailu došlo k neočekávané chybě.',
                'Zkuste prosím doklady zaslat znovu. Pokud problém přetrvává, kontaktujte příjemce.'
            ),

            default => $this->replyTemplate(
                'Tato schránka slouží výhradně k příjmu účetních dokladů.',
                'Podporované formáty: PDF, JPG, PNG (max 10 MB).'
            ),
        };
    }

    /**
     * Vyhodnotí stav zpracování emailu a vrátí klíč pro šablonu.
     */
    private function resolveReplyState(array $analysis): string
    {
        if (!$analysis['ico_found']) {
            return 'ico_not_found';
        }

        if (!$analysis['firma_found']) {
            return 'firma_not_found';
        }

        // Interní chyba (errors obsahují "Interní chyba")
        foreach ($analysis['errors'] as $err) {
            if (str_contains($err, 'Interní chyba')) {
                return 'processing_error';
            }
        }

        $hasValid = $analysis['processed_ok'] > 0;
        $hasErrors = !empty($analysis['errors']);
        $hasDuplicates = !empty($analysis['duplicates']);
        $hasInvalid = !empty($analysis['invalid_attachments']);
        $totalAttachments = $analysis['processed_ok'] + count($analysis['errors']) + count($analysis['duplicates']);

        // Žádné přílohy
        if ($totalAttachments === 0 && !$hasInvalid) {
            return 'no_attachments';
        }

        // Pouze nepodporované formáty
        if ($hasInvalid && !$hasValid && !$hasErrors && !$hasDuplicates) {
            return 'invalid_format_only';
        }

        // Vše duplicitní
        if ($hasDuplicates && !$hasValid && !$hasErrors && !$hasInvalid) {
            return 'all_duplicates';
        }

        // Vše selhalo
        if ($hasErrors && !$hasValid && !$hasDuplicates) {
            return 'all_errors';
        }

        // Mix výsledků
        if ($hasValid && ($hasErrors || $hasDuplicates || $hasInvalid)) {
            return 'partial_ok';
        }

        // Vše OK — sem by se nemělo dostat (shouldReply vrací false)
        return 'unknown';
    }

    /**
     * Sestaví text odpovědi z řádků (null řádky se přeskočí).
     */
    private function replyTemplate(string ...$lines): string
    {
        $body = implode("\n", array_filter($lines, fn($l) => $l !== null));
        return "Dobrý den,\n\n{$body}\n\nS pozdravem,\nTupTuDu";
    }

    /**
     * Odpověď pro částečně úspěšné zpracování.
     */
    private function replyPartialOk(array $analysis): string
    {
        $parts = [];

        $ok = $analysis['processed_ok'];
        $parts[] = "Zpracováno úspěšně: {$ok} " . ($ok === 1 ? 'doklad' : ($ok < 5 ? 'doklady' : 'dokladů')) . '.';

        if (!empty($analysis['duplicates'])) {
            $count = count($analysis['duplicates']);
            $parts[] = "Přeskočeno (duplicita): {$count} — " . implode(', ', $analysis['duplicates']) . '.';
        }

        if (!empty($analysis['errors'])) {
            $count = count($analysis['errors']);
            $parts[] = "Selhalo: {$count} — zkuste tyto soubory zaslat znovu.";
        }

        if (!empty($analysis['invalid_attachments'])) {
            $parts[] = 'Nepodporovaný formát: ' . implode(', ', $analysis['invalid_attachments']) . '. Podporujeme PDF, JPG a PNG.';
        }

        return $this->replyTemplate(...$parts);
    }

    /**
     * Odstraní technické detaily z chybových zpráv pro uživatele.
     */
    private function sanitizeError(string $error): string
    {
        // Odstraň názvy souborů s cestami, stack traces, API klíče
        $error = preg_replace('/\/[^\s:]+/', '', $error);
        $error = preg_replace('/\b[A-Za-z0-9]{20,}\b/', '***', $error);

        // Zkrátit na rozumnou délku
        if (mb_strlen($error) > 120) {
            $error = mb_substr($error, 0, 117) . '...';
        }

        // Pokud po sanitizaci zbyde jen název souboru + něco, zjednoduš
        if (preg_match('/^(.+?):\s*(.+)$/', $error, $m)) {
            return $m[1] . ': nepodařilo se zpracovat';
        }

        return $error ?: 'nepodařilo se zpracovat';
    }

    /**
     * Odešle odpověď z adresy ICO@{mail.doklady_domain}.
     */
    private function sendReply(string $text, string $toEmail, string $fromIco, string $originalSubject): void
    {
        $fromAddress = $fromIco . '@' . config('mail.doklady_domain', 'tuptudu.cz');

        Mail::mailer('doklady')
            ->to($toEmail)
            ->send(
                (new OdpovedNaDoklad($text, $originalSubject))
                    ->from($fromAddress, 'TupTuDu Doklady')
            );
    }

    /**
     * Extrahuje email odesílatele ze zprávy.
     */
    private function extractSenderEmail($message): ?string
    {
        try {
            $from = $message->getFrom();
            if ($from) {
                $raw = $from->toString();
                if (preg_match('/[\w.+-]+@[\w.-]+/', $raw, $m)) {
                    return $m[0];
                }
            }
        } catch (\Throwable $e) {}

        return null;
    }

    private function processCustomMailboxes(DokladProcessor $processor): int
    {
        $query = Firma::where('email_vlastni_aktivni', true)
            ->whereNotNull('email_vlastni_host')
            ->whereNotNull('email_vlastni_heslo');

        if ($ico = $this->option('ico')) {
            $query->where('ico', $ico);
        }

        $firmy = $query->get();

        if ($firmy->isEmpty()) {
            $this->line('Žádné firmy s vlastním IMAP.');
            return 0;
        }

        $totalProcessed = 0;

        foreach ($firmy as $firma) {
            $this->info("Vlastní schránka: {$firma->email_vlastni} ({$firma->ico})");

            try {
                $cm = new ClientManager();
                $client = $cm->make([
                    'host' => $firma->email_vlastni_host,
                    'port' => $firma->email_vlastni_port ?? 993,
                    'encryption' => ($firma->email_vlastni_sifrovani ?? 'ssl') === 'none'
                        ? false
                        : ($firma->email_vlastni_sifrovani ?? 'ssl'),
                    'validate_cert' => true,
                    'username' => $firma->email_vlastni_uzivatel ?: $firma->email_vlastni,
                    'password' => $firma->email_vlastni_heslo,
                    'protocol' => 'imap',
                ]);

                $client->connect();
                $folder = $client->getFolder('INBOX');
                $messages = $folder->query()->unseen()->get();
                $processed = 0;

                foreach ($messages as $message) {
                    $senderEmail = $this->extractSenderEmail($message);
                    $parts = $this->collectMessageParts($message);

                    $results = $this->processFiles($parts['valid'], $firma, $processor, $senderEmail);
                    $processed += $results['processed_ok'];

                    // Custom mailboxes: no auto-reply (firma manages own email)
                    $message->setFlag('Seen');
                }

                $client->disconnect();
                $totalProcessed += $processed;
                $this->info("  Zpracováno {$processed} příloh.");
            } catch (\Exception $e) {
                $this->error("  Chyba: {$e->getMessage()}");
                Log::error("ProcessEmailDoklady: custom mailbox error for {$firma->ico}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $totalProcessed;
    }

    /**
     * Vypíše příjemce zprávy pro log — ať je vidět, proč se IČO nenašlo.
     * Typicky když adresa {IČO}@tuptudu.cz byla ve skryté kopii a v hlavičkách
     * To ani Cc se vůbec neobjeví.
     */
    private function popisPrijemcu($message): string
    {
        $casti = [];

        foreach (['getTo' => 'To', 'getCc' => 'Cc'] as $metoda => $nazev) {
            try {
                $hodnota = trim((string) $message->$metoda());
                $casti[] = $nazev . ': ' . ($hodnota !== '' ? $hodnota : '(prázdné)');
            } catch (\Throwable $e) {
                $casti[] = $nazev . ': (nelze přečíst)';
            }
        }

        return implode(' | ', $casti);
    }

    private function extractIcoFromRecipients($message): ?string
    {
        // Hledá v To/CC adresy {8 číslic}@tuptudu.cz
        // (plus historickou variantu na subdoméně doklady.tuptudu.cz)
        $recipients = [];

        // getTo() vrací Attribute – použij toString() a parsuj emaily regexem
        foreach (['getTo', 'getCc'] as $method) {
            try {
                $header = $message->$method();
                if (!$header) continue;

                $raw = $header->toString();
                // Extrahuj všechny email adresy z headeru
                if (preg_match_all('/[\w.+-]+@[\w.-]+/', $raw, $matches)) {
                    foreach ($matches[0] as $email) {
                        $recipients[] = $email;
                    }
                }
            } catch (\Throwable $e) {}
        }

        // Aktuální doména + historické varianty, které mohou být uložené u firem
        $domains = array_unique(array_filter([
            config('mail.doklady_domain', 'tuptudu.cz'),
            'tuptudu.cz',
            'doklady.tuptudu.cz',
        ]));
        $pattern = '/^(\d{8})@('
            . implode('|', array_map(fn ($d) => preg_quote($d, '/'), $domains))
            . ')$/i';

        foreach ($recipients as $email) {
            if (preg_match($pattern, trim($email), $m)) {
                return $m[1];
            }
        }

        return null;
    }
}
