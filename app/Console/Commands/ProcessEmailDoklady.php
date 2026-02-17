<?php

namespace App\Console\Commands;

use App\Models\Firma;
use App\Services\DokladProcessor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
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
                // Extract ICO from "To" address
                $ico = $this->extractIcoFromRecipients($message);

                if (!$ico) {
                    $this->line("  Přeskakuji — nelze určit IČO z příjemce.");
                    $message->setFlag('Seen');
                    continue;
                }

                // Filter by --ico option
                if ($this->option('ico') && $this->option('ico') !== $ico) {
                    continue;
                }

                $firma = Firma::where('ico', $ico)->where('email_system_aktivni', true)->first();

                if (!$firma) {
                    $this->line("  Přeskakuji IČO {$ico} — firma nemá aktivní systémový email.");
                    $message->setFlag('Seen');
                    continue;
                }

                $processed += $this->processAttachments($message, $firma, $processor);
                $message->setFlag('Seen');
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
                    $processed += $this->processAttachments($message, $firma, $processor);
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

    private function processAttachments($message, Firma $firma, DokladProcessor $processor): int
    {
        $attachments = $message->getAttachments();
        $processed = 0;

        foreach ($attachments as $attachment) {
            $extension = strtolower(pathinfo($attachment->getName(), PATHINFO_EXTENSION));

            if (!in_array($extension, self::ALLOWED_EXTENSIONS)) {
                continue;
            }

            $originalName = $attachment->getName();
            $content = $attachment->getContent();

            $tempPath = tempnam(sys_get_temp_dir(), 'doklad_');
            file_put_contents($tempPath, $content);

            $fileHash = hash_file('sha256', $tempPath);

            if ($processor->isDuplicate($fileHash, $firma->ico)) {
                $this->line("    Přeskakuji duplicitu: {$originalName}");
                unlink($tempPath);
                continue;
            }

            // Extract sender email
            $senderEmail = null;
            try {
                $from = $message->getFrom();
                if ($from) {
                    foreach ($from as $addr) {
                        $senderEmail = is_string($addr) ? $addr : ($addr->mail ?? (string) $addr);
                        break;
                    }
                }
            } catch (\Throwable $e) {}

            try {
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
                $processed++;
            } catch (\Exception $e) {
                $this->error("    Chyba při zpracování {$originalName}: {$e->getMessage()}");
                Log::error("ProcessEmailDoklady: attachment error", [
                    'firma_ico' => $firma->ico,
                    'attachment' => $originalName,
                    'error' => $e->getMessage(),
                ]);
            } finally {
                @unlink($tempPath);
            }
        }

        return $processed;
    }
}
