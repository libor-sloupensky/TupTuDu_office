<?php

namespace App\Http\Controllers;

use App\Models\Firma;
use App\Services\GoogleDriveService;
use Google\Client as GoogleClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GoogleDriveController extends Controller
{
    public function redirect(Request $request)
    {
        $client = $this->buildClient();
        $client->setAccessType('offline');
        $client->setPrompt('consent');

        $authUrl = $client->createAuthUrl();

        return redirect()->away($authUrl);
    }

    public function callback(Request $request)
    {
        if (!$request->has('code')) {
            return redirect()->route('firma.nastaveni')
                ->with('flash_error', 'Google autorizace byla zrušena.');
        }

        $client = $this->buildClient();

        try {
            $token = $client->fetchAccessTokenWithAuthCode($request->input('code'));

            if (isset($token['error'])) {
                Log::error('Google OAuth error', $token);
                return redirect()->route('firma.nastaveni')
                    ->with('flash_error', 'Chyba při propojení s Google: ' . ($token['error_description'] ?? $token['error']));
            }

            $refreshToken = $token['refresh_token'] ?? null;
            if (!$refreshToken) {
                return redirect()->route('firma.nastaveni')
                    ->with('flash_error', 'Google nevrátil refresh token. Zkuste to znovu.');
            }

            $firma = Firma::find(session('aktivni_firma_ico'));
            if (!$firma) {
                return redirect()->route('firma.nastaveni')
                    ->with('flash_error', 'Aktivní firma nebyla nalezena.');
            }

            // Create root folder on Drive
            $client->setAccessToken($token);
            $driveService = new GoogleDriveService();
            $rootFolderId = $driveService->ensureFolderWithClient(
                new \Google\Service\Drive($client),
                'root',
                'office.tuptudu.cz'
            );

            $firma->update([
                'google_drive_aktivni' => true,
                'google_refresh_token' => encrypt($refreshToken),
                'google_folder_id' => $rootFolderId,
            ]);

            return redirect()->route('firma.nastaveni')
                ->with('flash', 'Google Drive byl úspěšně propojen.');

        } catch (\Throwable $e) {
            Log::error('Google OAuth callback failed', ['error' => $e->getMessage()]);
            return redirect()->route('firma.nastaveni')
                ->with('flash_error', 'Chyba při propojení s Google Drive: ' . $e->getMessage());
        }
    }

    public function disconnect(Request $request)
    {
        $firma = Firma::find(session('aktivni_firma_ico'));
        if (!$firma) {
            return redirect()->route('firma.nastaveni');
        }

        // Revoke token
        if ($firma->google_refresh_token) {
            try {
                $client = $this->buildClient();
                $client->revokeToken(decrypt($firma->google_refresh_token));
            } catch (\Throwable $e) {
                Log::warning('Google token revoke failed', ['error' => $e->getMessage()]);
            }
        }

        $firma->update([
            'google_drive_aktivni' => false,
            'google_refresh_token' => null,
            'google_folder_id' => null,
        ]);

        return redirect()->route('firma.nastaveni')
            ->with('flash', 'Google Drive byl odpojen.');
    }

    private function buildClient(): GoogleClient
    {
        $client = new GoogleClient();
        $client->setClientId(config('services.google.client_id'));
        $client->setClientSecret(config('services.google.client_secret'));
        $client->setRedirectUri(config('services.google.redirect_uri'));
        $client->addScope(\Google\Service\Drive::DRIVE_FILE);

        return $client;
    }
}
