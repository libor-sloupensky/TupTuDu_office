<?php

namespace App\Services;

use App\Models\Doklad;
use App\Models\Firma;
use Google\Client as GoogleClient;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GoogleDriveService
{
    private array $folderCache = [];

    public function uploadDoklad(Doklad $doklad, Firma $firma): ?string
    {
        try {
            $driveService = $this->getDriveService($firma);
            if (!$driveService) {
                return null;
            }

            $rootFolderId = $firma->google_folder_id;
            if (!$rootFolderId) {
                return null;
            }

            // Build path from template
            $builder = new DrivePathBuilder();
            $template = $firma->google_drive_sablona ?? DrivePathBuilder::DEFAULT_TEMPLATE;
            $path = $builder->build($template, $doklad);

            // Ensure folder structure: root → template folders
            $parentFolderId = $rootFolderId;
            foreach ($path['folders'] as $folderName) {
                if ($folderName !== '') {
                    $parentFolderId = $this->ensureFolderWithClient($driveService, $parentFolderId, $folderName);
                }
            }

            // Download file from S3
            $s3Path = $doklad->cesta_souboru;
            if (!$s3Path || !Storage::disk('s3')->exists($s3Path)) {
                Log::warning('Google Drive sync: S3 file not found', ['doklad_id' => $doklad->id, 'path' => $s3Path]);
                return null;
            }

            $fileContent = Storage::disk('s3')->get($s3Path);
            $ext = strtolower(pathinfo($s3Path, PATHINFO_EXTENSION));
            $mimeType = $this->getMimeType($ext);

            // Embed metadata for PDF/JPEG
            $fileContent = $this->embedMetadata($fileContent, $doklad, $ext);

            // Upload to Drive
            $fileName = $path['filename'] . '.' . $ext;
            $fileMetadata = new DriveFile([
                'name' => $fileName,
                'parents' => [$parentFolderId],
            ]);

            $file = $driveService->files->create($fileMetadata, [
                'data' => $fileContent,
                'mimeType' => $mimeType,
                'uploadType' => 'multipart',
                'fields' => 'id',
            ]);

            return $file->id;

        } catch (\Throwable $e) {
            Log::error('Google Drive upload failed', [
                'doklad_id' => $doklad->id,
                'firma_ico' => $firma->ico,
                'error' => $e->getMessage(),
            ]);
            $this->handleAuthError($e, $firma);
            return null;
        }
    }

    public function ensureFolderWithClient($driveService, string $parentId, string $name): string
    {
        // If $driveService is a Drive instance, use it directly
        if (!$driveService instanceof Drive) {
            return $name;
        }

        $cacheKey = $parentId . '/' . $name;
        if (isset($this->folderCache[$cacheKey])) {
            return $this->folderCache[$cacheKey];
        }

        // Search for existing folder
        $query = sprintf(
            "name='%s' and '%s' in parents and mimeType='application/vnd.google-apps.folder' and trashed=false",
            addcslashes($name, "'"),
            addcslashes($parentId, "'")
        );

        $results = $driveService->files->listFiles([
            'q' => $query,
            'fields' => 'files(id)',
            'pageSize' => 1,
        ]);

        if (count($results->getFiles()) > 0) {
            $folderId = $results->getFiles()[0]->getId();
        } else {
            $folderMetadata = new DriveFile([
                'name' => $name,
                'mimeType' => 'application/vnd.google-apps.folder',
                'parents' => [$parentId],
            ]);
            $folder = $driveService->files->create($folderMetadata, ['fields' => 'id']);
            $folderId = $folder->id;
        }

        $this->folderCache[$cacheKey] = $folderId;
        return $folderId;
    }

    private function getDriveService(Firma $firma): ?Drive
    {
        if (!$firma->google_drive_aktivni || !$firma->google_refresh_token) {
            return null;
        }

        try {
            $client = new GoogleClient();
            $client->setClientId(config('services.google.client_id'));
            $client->setClientSecret(config('services.google.client_secret'));
            $client->addScope(Drive::DRIVE_FILE);

            $refreshToken = decrypt($firma->google_refresh_token);
            $client->fetchAccessTokenWithRefreshToken($refreshToken);

            if ($client->isAccessTokenExpired()) {
                Log::error('Google Drive: token expired after refresh', ['firma_ico' => $firma->ico]);
                $this->deactivate($firma);
                return null;
            }

            return new Drive($client);

        } catch (\Throwable $e) {
            Log::error('Google Drive: auth failed', ['firma_ico' => $firma->ico, 'error' => $e->getMessage()]);
            $this->deactivate($firma);
            return null;
        }
    }

    private function embedMetadata(string $content, Doklad $doklad, string $ext): string
    {
        if ($ext === 'pdf') {
            return $this->embedPdfMetadata($content, $doklad);
        }

        if (in_array($ext, ['jpg', 'jpeg'])) {
            return $this->embedJpegMetadata($content, $doklad);
        }

        return $content;
    }

    private function embedPdfMetadata(string $content, Doklad $doklad): string
    {
        try {
            $tmpFile = tempnam(sys_get_temp_dir(), 'gdrive_pdf_');
            file_put_contents($tmpFile, $content);

            $pdf = new \setasign\Fpdi\Fpdi();
            $pageCount = $pdf->setSourceFile($tmpFile);

            for ($i = 1; $i <= $pageCount; $i++) {
                $tplId = $pdf->importPage($i);
                $size = $pdf->getTemplateSize($tplId);
                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($tplId);
            }

            $pdf->SetTitle($doklad->cislo_dokladu ?? 'Doklad ' . $doklad->id);
            $pdf->SetAuthor($doklad->dodavatel_nazev ?? '');
            $pdf->SetSubject(implode(', ', array_filter([
                $doklad->datum_vystaveni ? 'Datum: ' . $doklad->datum_vystaveni->format('d.m.Y') : null,
                $doklad->castka_celkem ? 'Částka: ' . $doklad->castka_celkem . ' ' . ($doklad->mena ?? 'CZK') : null,
            ])));
            $pdf->SetKeywords(implode(', ', array_filter([
                $doklad->dodavatel_ico ? 'ICO:' . $doklad->dodavatel_ico : null,
                $doklad->variabilni_symbol ? 'VS:' . $doklad->variabilni_symbol : null,
                $doklad->kategorie ?? null,
            ])));

            $result = $pdf->Output('S');
            @unlink($tmpFile);
            return $result;

        } catch (\Throwable $e) {
            Log::warning('PDF metadata embedding failed, uploading without metadata', [
                'doklad_id' => $doklad->id,
                'error' => $e->getMessage(),
            ]);
            return $content;
        }
    }

    private function embedJpegMetadata(string $content, Doklad $doklad): string
    {
        try {
            $tmpFile = tempnam(sys_get_temp_dir(), 'gdrive_jpg_');
            file_put_contents($tmpFile, $content);

            // Build IPTC data
            $iptcData = [];

            // 2#005 = Object Name (Title)
            if ($doklad->cislo_dokladu) {
                $iptcData['2#005'] = $doklad->cislo_dokladu;
            }

            // 2#080 = By-line (Author)
            if ($doklad->dodavatel_nazev) {
                $iptcData['2#080'] = $doklad->dodavatel_nazev;
            }

            // 2#120 = Caption
            $caption = implode(', ', array_filter([
                $doklad->datum_vystaveni ? 'Datum: ' . $doklad->datum_vystaveni->format('d.m.Y') : null,
                $doklad->castka_celkem ? 'Částka: ' . $doklad->castka_celkem . ' ' . ($doklad->mena ?? 'CZK') : null,
                $doklad->dodavatel_ico ? 'IČO: ' . $doklad->dodavatel_ico : null,
            ]));
            if ($caption) {
                $iptcData['2#120'] = $caption;
            }

            // 2#025 = Keywords
            $keywords = array_filter([
                $doklad->variabilni_symbol ? 'VS:' . $doklad->variabilni_symbol : null,
                $doklad->kategorie ?? null,
            ]);

            if (empty($iptcData) && empty($keywords)) {
                @unlink($tmpFile);
                return $content;
            }

            // Build IPTC binary
            $iptcBinary = '';
            foreach ($iptcData as $tag => $value) {
                $iptcBinary .= $this->makeIptcTag($tag, $value);
            }
            foreach ($keywords as $kw) {
                $iptcBinary .= $this->makeIptcTag('2#025', $kw);
            }

            $result = iptcembed($iptcBinary, $tmpFile);
            @unlink($tmpFile);

            if ($result === false) {
                return $content;
            }

            return $result;

        } catch (\Throwable $e) {
            Log::warning('JPEG metadata embedding failed', [
                'doklad_id' => $doklad->id,
                'error' => $e->getMessage(),
            ]);
            return $content;
        }
    }

    private function makeIptcTag(string $tag, string $value): string
    {
        // Tag format: "2#005" -> record=2, dataset=5
        $parts = explode('#', $tag);
        $record = (int) $parts[0];
        $dataset = (int) $parts[1];
        $value = mb_convert_encoding($value, 'UTF-8');
        $length = strlen($value);

        return chr(0x1C) . chr($record) . chr($dataset)
            . chr(($length >> 8) & 0xFF) . chr($length & 0xFF)
            . $value;
    }

    private function getMimeType(string $ext): string
    {
        return match ($ext) {
            'pdf' => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'application/octet-stream',
        };
    }

    private function handleAuthError(\Throwable $e, Firma $firma): void
    {
        $msg = $e->getMessage();
        if (str_contains($msg, 'invalid_grant') || str_contains($msg, 'Token has been revoked')) {
            $this->deactivate($firma);
        }
    }

    private function deactivate(Firma $firma): void
    {
        $firma->update([
            'google_drive_aktivni' => false,
            'google_refresh_token' => null,
            'google_folder_id' => null,
        ]);
        Log::info('Google Drive deactivated for firma', ['ico' => $firma->ico]);
    }
}
