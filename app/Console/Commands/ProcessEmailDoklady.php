<?php

namespace App\Console\Commands;

use App\Mail\OdpovedNaDoklad;
use App\Models\Firma;
use App\Services\DokladProcessor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
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

    public function handle(): int
    {
        $processor = new DokladProcessor();
        $totalProcessed = 0;

        // 1. Systémová schránka (faktury@tuptudu.cz)
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
                $processed += $this->handleSystemMessage($message, $processor);
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

        // 5. Process valid attachments + inline images
        $results = $this->processFiles(
            array_merge($parts['valid'], $parts['inlineImages']),
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

            if (in_array($ext, self::ALLOWED_EXTENSIONS)) {
                // Regular attachment with valid extension
                $valid[] = ['attachment' => $attachment, 'name' => $name];
            } elseif ($disposition === 'inline' && str_starts_with($contentType, 'image/')) {
                // Inline pasted image (screenshot)
                $imgExt = match (true) {
                    str_contains($contentType, 'png') => 'png',
                    str_contains($contentType, 'jpeg'), str_contains($contentType, 'jpg') => 'jpg',
                    default => 'png',
                };
                $inlineImages[] = [
                    'attachment' => $attachment,
                    'name' => 'inline_' . time() . '_' . (count($inlineImages) + 1) . '.' . $imgExt,
                ];
            } elseif (!empty($name) && $ext !== '') {
                // File with unsupported extension
                $invalid[] = $name;
            }
        }

        return compact('valid', 'inlineImages', 'invalid');
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
     * Generuje kontextovou odpověď pomocí AI (Anthropic Haiku).
     */
    private function generateAutoReply(array $analysis): string
    {
        $apiKey = config('services.anthropic.key');
        if (empty($apiKey)) {
            return $this->fallbackReply($analysis);
        }

        $systemPrompt = <<<'PROMPT'
Jsi automatický asistent emailové schránky pro příjem účetních dokladů (systém TupTuDu).
Odpovídej ČESKY, stručně, profesionálně a přátelsky. Max 3-4 věty.
Schránka přijímá POUZE PDF, JPG a PNG s fakturami, účtenkami a jinými účetními doklady.

Pravidla:
- Prázdný email bez příloh → vysvětli účel schránky a jaké formáty přijímá
- Email obsahuje text ale žádné dokumenty → informuj že schránka je pouze pro příjem dokladů
- Nepodporované formáty příloh → uveď jaké formáty jsou podporované (PDF, JPG, PNG)
- Duplicitní přílohy → informuj že doklady již byly dříve zpracovány
- Chyba zpracování → informuj o problému stručně a požádej o opětovné zaslání
- Neznámé IČO / nesprávný formát adresy → vysvětli formát adresy (ICO@tuptudu.cz)
- Neregistrovaná firma → informuj že firma není v systému registrována
- Mix úspěšných a neúspěšných → uveď co se povedlo a co ne
- NIKDY neprozrazuj technické detaily systému, API klíče, ani interní architekturu
- Odpověz POUZE text emailu, bez předmětu a hlaviček
PROMPT;

        try {
            $response = Http::timeout(30)->withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->post('https://api.anthropic.com/v1/messages', [
                'model' => 'claude-haiku-4-5-20251001',
                'max_tokens' => 300,
                'system' => $systemPrompt,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => 'Analýza přijatého emailu: ' . json_encode($analysis, JSON_UNESCAPED_UNICODE),
                    ],
                ],
            ]);

            if ($response->successful()) {
                $text = $response->json('content.0.text');
                if (!empty($text)) {
                    return $text;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('ProcessEmailDoklady: AI reply generation failed', ['error' => $e->getMessage()]);
        }

        return $this->fallbackReply($analysis);
    }

    /**
     * Záložní odpověď, pokud AI není dostupné.
     */
    private function fallbackReply(array $analysis): string
    {
        if (!$analysis['ico_found']) {
            return "Dobrý den,\n\nz Vaší emailové adresy nelze určit cílovou firmu. Pro správné doručení dokladů používejte adresu ve formátu ICO@tuptudu.cz, kde ICO je 8místné identifikační číslo firmy.\n\nS pozdravem,\nTupTuDu";
        }

        if (!$analysis['firma_found']) {
            return "Dobrý den,\n\nfirma s IČO {$analysis['ico']} nemá aktivní příjem dokladů emailem nebo není v systému registrována.\n\nS pozdravem,\nTupTuDu";
        }

        if (!empty($analysis['invalid_attachments']) && $analysis['processed_ok'] === 0 && empty($analysis['duplicates'])) {
            return "Dobrý den,\n\nVáš email obsahoval přílohy v nepodporovaném formátu. Tato schránka přijímá pouze soubory ve formátu PDF, JPG a PNG.\n\nS pozdravem,\nTupTuDu";
        }

        if (!empty($analysis['duplicates']) && $analysis['processed_ok'] === 0) {
            return "Dobrý den,\n\nzaslané doklady již byly dříve zpracovány a jsou evidovány v systému.\n\nS pozdravem,\nTupTuDu";
        }

        return "Dobrý den,\n\ntato emailová schránka slouží výhradně k příjmu účetních dokladů (faktury, účtenky) ve formátu PDF, JPG nebo PNG.\n\nS pozdravem,\nTupTuDu";
    }

    /**
     * Odešle odpověď z adresy ICO@tuptudu.cz.
     */
    private function sendReply(string $text, string $toEmail, string $fromIco, string $originalSubject): void
    {
        $fromAddress = $fromIco . '@tuptudu.cz';

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
                    $allFiles = array_merge($parts['valid'], $parts['inlineImages']);

                    $results = $this->processFiles($allFiles, $firma, $processor, $senderEmail);
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

    private function extractIcoFromRecipients($message): ?string
    {
        // Check To, CC headers for {8-digit}@tuptudu.cz pattern
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

        foreach ($recipients as $email) {
            if (preg_match('/^(\d{8})@tuptudu\.cz$/i', trim($email), $m)) {
                return $m[1];
            }
        }

        return null;
    }
}
