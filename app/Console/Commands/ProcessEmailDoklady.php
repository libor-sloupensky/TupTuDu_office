<?php

namespace App\Console\Commands;

use App\Models\Firma;
use App\Services\DokladProcessor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Webklex\IMAP\ClientManager;

class ProcessEmailDoklady extends Command
{
    protected $signature = 'doklady:process-email
                            {--ico= : Zpracovat jen konkrétní firmu podle IČO}';

    protected $description = 'Stáhne a zpracuje doklady z emailových schránek firem';

    private const ALLOWED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png'];

    public function handle(): int
    {
        $query = Firma::whereNotNull('email_doklady')
            ->whereNotNull('email_doklady_heslo')
            ->where('email_doklady_heslo', '!=', '');

        if ($ico = $this->option('ico')) {
            $query->where('ico', $ico);
        }

        $firmy = $query->get();

        if ($firmy->isEmpty()) {
            $this->warn('Žádné firmy s nastaveným emailem pro doklady.');
            return self::SUCCESS;
        }

        $processor = new DokladProcessor();
        $totalProcessed = 0;

        foreach ($firmy as $firma) {
            $this->info("Zpracovávám schránku: {$firma->email_doklady}");

            try {
                $processed = $this->processMailbox($firma, $processor);
                $totalProcessed += $processed;
                $this->info("  Zpracováno {$processed} příloh.");
            } catch (\Exception $e) {
                $this->error("  Chyba: {$e->getMessage()}");
                Log::error("ProcessEmailDoklady: chyba pro {$firma->ico}", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        $this->info("Celkem zpracováno: {$totalProcessed} příloh.");
        return self::SUCCESS;
    }

    private function processMailbox(Firma $firma, DokladProcessor $processor): int
    {
        $cm = new ClientManager();
        $client = $cm->make([
            'host' => config('services.imap.host'),
            'port' => config('services.imap.port'),
            'encryption' => config('services.imap.encryption'),
            'validate_cert' => true,
            'username' => $firma->email_doklady,
            'password' => $firma->email_doklady_heslo,
            'protocol' => 'imap',
        ]);

        $client->connect();
        $folder = $client->getFolder('INBOX');

        $messages = $folder->query()->unseen()->get();
        $processed = 0;

        foreach ($messages as $message) {
            $attachments = $message->getAttachments();

            foreach ($attachments as $attachment) {
                $extension = strtolower(pathinfo($attachment->getName(), PATHINFO_EXTENSION));

                if (!in_array($extension, self::ALLOWED_EXTENSIONS)) {
                    continue;
                }

                $originalName = $attachment->getName();
                $content = $attachment->getContent();

                // Uložit do temp souboru pro OCR
                $tempPath = tempnam(sys_get_temp_dir(), 'doklad_');
                file_put_contents($tempPath, $content);

                $fileHash = hash_file('sha256', $tempPath);

                // Kontrola duplicit
                if ($processor->isDuplicate($fileHash, $firma->ico)) {
                    $this->line("    Přeskakuji duplicitu: {$originalName}");
                    unlink($tempPath);
                    continue;
                }

                try {
                    $doklad = $processor->process(
                        $tempPath,
                        $originalName,
                        $firma,
                        $fileHash,
                        'email'
                    );

                    $this->line("    Zpracován: {$originalName} -> doklad #{$doklad->id} ({$doklad->stav})");
                    $processed++;
                } catch (\Exception $e) {
                    $this->error("    Chyba při zpracování {$originalName}: {$e->getMessage()}");
                    Log::error("ProcessEmailDoklady: chyba přílohy", [
                        'firma_ico' => $firma->ico,
                        'attachment' => $originalName,
                        'error' => $e->getMessage(),
                    ]);
                } finally {
                    @unlink($tempPath);
                }
            }

            // Označit email jako přečtený
            $message->setFlag('Seen');
        }

        $client->disconnect();
        return $processed;
    }
}
