<?php

namespace App\Console\Commands;

use App\Models\Doklad;
use App\Models\Firma;
use App\Models\UcetniVazba;
use App\Services\GoogleDriveService;
use Illuminate\Console\Command;

class SyncGoogleDrive extends Command
{
    protected $signature = 'doklady:sync-drive';
    protected $description = 'Synchronizace dokončených dokladů na Google Drive';

    public function handle(): int
    {
        $doklady = Doklad::where('stav', 'dokonceno')
            ->whereNull('google_drive_nahrano_at')
            ->whereNotNull('cesta_souboru')
            ->orderBy('id')
            ->limit(50)
            ->get();

        if ($doklady->isEmpty()) {
            $this->info('Žádné doklady k synchronizaci.');
            return 0;
        }

        $service = new GoogleDriveService();
        $uploaded = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($doklady as $doklad) {
            $firma = Firma::find($doklad->firma_ico);
            if (!$firma) {
                $skipped++;
                continue;
            }

            $firmaFileId = null;
            $ucetniFileId = null;
            $anyUpload = false;

            // Upload to firma's Drive
            if ($firma->google_drive_aktivni) {
                $firmaFileId = $service->uploadDoklad($doklad, $firma);
                if ($firmaFileId) {
                    $anyUpload = true;
                }
            }

            // Upload to accountant's Drive
            $vazba = UcetniVazba::where('klient_ico', $firma->ico)
                ->where('stav', 'schvaleno')
                ->first();

            if ($vazba) {
                $ucetniFirma = Firma::find($vazba->ucetni_ico);
                if ($ucetniFirma && $ucetniFirma->google_drive_aktivni) {
                    $ucetniFileId = $service->uploadDoklad($doklad, $ucetniFirma);
                    if ($ucetniFileId) {
                        $anyUpload = true;
                    }
                }
            }

            // Mark as uploaded if at least one Drive upload succeeded,
            // or if neither firma nor ucetni has Drive active (nothing to do)
            $firmaActive = $firma->google_drive_aktivni;
            $ucetniActive = isset($ucetniFirma) && $ucetniFirma->google_drive_aktivni;

            if ($anyUpload || (!$firmaActive && !$ucetniActive)) {
                $doklad->update([
                    'google_drive_file_id' => $firmaFileId,
                    'google_drive_ucetni_file_id' => $ucetniFileId,
                    'google_drive_nahrano_at' => now(),
                ]);
                $uploaded++;
            } else {
                $errors++;
            }
        }

        $this->info("Synchronizace dokončena: {$uploaded} nahráno, {$skipped} přeskočeno, {$errors} chyb.");
        return 0;
    }
}
