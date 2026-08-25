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
        // Vybírají se jen doklady, které má kam nahrát — tedy firmy s aktivním
        // Drivem a klienti účetních s aktivním Drivem. Doklady ostatních firem
        // se nechávají nedotčené, ať se dají zálohovat, až si Drive připojí.
        $sDrivem = Firma::where('google_drive_aktivni', true)->pluck('ico');

        $klientiUcetnich = UcetniVazba::where('stav', 'schvaleno')
            ->whereIn('ucetni_ico', $sDrivem)
            ->pluck('klient_ico');

        $doklady = Doklad::where('stav', 'dokonceno')
            ->whereNull('google_drive_nahrano_at')
            ->whereNotNull('cesta_souboru')
            ->where(fn ($q) => $q->whereIn('firma_ico', $sDrivem)
                ->orWhereIn('firma_ico', $klientiUcetnich))
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

            // Razítko jen když se doklad opravdu někam nahrál. Dřív se stavěl
            // i ve chvíli, kdy žádný Drive nebyl aktivní — a protože se aplikace
            // po vypršení tokenu sama odpojí, odrazítkovala takhle celou frontu
            // jako hotovou, aniž by se cokoli nahrálo, a už se k tomu nevrátila.
            if ($anyUpload) {
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
