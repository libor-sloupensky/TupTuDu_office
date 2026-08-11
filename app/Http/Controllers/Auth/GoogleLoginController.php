<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Pozvani;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

/**
 * Přihlášení přes Google účet (Socialite).
 *
 * POZOR: nesouvisí s GoogleDriveController — ten řeší OAuth pro Drive sync
 * (scope drive.file, offline access, uložený refresh token). Tady jde jen
 * o identitu (openid/email/profile) a vlastní redirect URI /auth/google/callback.
 *
 * Nové účty NEZAKLÁDÁME — registrace zůstává jen přes IČO / pozvánku.
 * Neznámý Google e-mail → uživatel je odmítnut s hláškou.
 */
class GoogleLoginController extends Controller
{
    /** Deep link schéma mobilní aplikace (= appId v capacitor.config.json) */
    private const APP_SCHEME = 'cz.tuptudu.office';

    public function redirect(Request $request)
    {
        // Rozlišení, kam se uživatel po přihlášení vrátí:
        // - capacitor=1 → Custom Tab v mobilní appce (token bridge, viz callback)
        // - mobile=1    → /mobile/* otevřené v běžném prohlížeči
        if ($request->query('capacitor') === '1') {
            Cookie::queue('oauth_capacitor', '1', 10);
        } elseif ($request->query('mobile') === '1') {
            Cookie::queue('oauth_mobile', '1', 10);
        }

        return Socialite::driver('google')
            ->stateless()
            ->redirectUrl($this->callbackUrl($request))
            ->redirect();
    }

    public function callback(Request $request)
    {
        // Callback bez kódu = uživatel přihlášení zrušil nebo URL otevřel bot.
        // Není to chyba aplikace → zpět na login, ať nepadá 500.
        if ($request->has('error') || ! $request->has('code')) {
            return $this->zpetNaLogin($request, 'Přihlášení přes Google bylo zrušeno.');
        }

        try {
            $googleUser = Socialite::driver('google')
                ->stateless()
                ->redirectUrl($this->callbackUrl($request))
                ->user();
        } catch (\Throwable $e) {
            report($e);

            return $this->zpetNaLogin($request, 'Přihlášení přes Google se nezdařilo. Zkuste to prosím znovu.');
        }

        $googleId = $googleUser->getId();
        $email = $googleUser->getEmail();

        $user = User::where('google_id', $googleId)->first();

        if (! $user && $email) {
            // První přihlášení Googlem u stávajícího účtu — spárujeme podle e-mailu.
            $user = User::where('email', $email)->first();
            if ($user) {
                $user->update(['google_id' => $googleId]);
            }
        }

        if (! $user) {
            return $this->zpetNaLogin(
                $request,
                'K tomuto Google účtu nemáme v TupTuDu žádný účet. Zaregistrujte se nejprve pomocí IČO nebo pozvánky.'
            );
        }

        // Google e-mail je ověřený Googlem → považujeme účet za ověřený.
        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        Auth::login($user, true);
        $request->session()->regenerate();
        $this->nastavAktivniFirmu($user);

        // Capacitor flow: session z Custom Tabu (Chrome) se nesdílí s WebView appky.
        // Vygenerujeme one-time token, appka jej zachytí přes deep link a otevře
        // /mobile/auth-bridge/{token}, kde teprve vznikne session ve WebView.
        if ($request->cookie('oauth_capacitor') === '1') {
            Cookie::queue(Cookie::forget('oauth_capacitor'));

            $token = Str::random(48);
            Cache::put('mobile_auth_token:' . $token, $user->id, 60); // platnost 60 s

            return $this->deepLinkStranka(self::APP_SCHEME . '://auth/done?token=' . urlencode($token));
        }

        if ($request->cookie('oauth_mobile') === '1') {
            Cookie::queue(Cookie::forget('oauth_mobile'));

            return redirect()->route('mobile.skenovat');
        }

        return redirect()->intended(route('doklady.index'));
    }

    /**
     * Bridge — appka sem přijde z WebView s tokenem z deep linku.
     * Token je jednorázový a max 60 s starý; po ověření založí session ve WebView.
     */
    public function mobileAuthBridge(Request $request, string $token)
    {
        $userId = Cache::pull('mobile_auth_token:' . $token); // pull = get + forget

        if (! $userId) {
            return redirect()->route('mobile.prihlaseni')
                ->withErrors(['email' => 'Přihlášení vypršelo, zkuste to prosím znovu.']);
        }

        Auth::loginUsingId($userId, true);
        $request->session()->regenerate();
        $this->nastavAktivniFirmu(Auth::user());

        return redirect()->route('mobile.skenovat');
    }

    /**
     * Redirect URI se odvozuje z aktuálního hostu — musí být whitelistnutá
     * v Google Cloud Console (https://office.tuptudu.cz/auth/google/callback).
     */
    private function callbackUrl(Request $request): string
    {
        return $request->getSchemeAndHttpHost() . '/auth/google/callback';
    }

    private function nastavAktivniFirmu(User $user): void
    {
        // Google e-mail je ověřený, takže tu můžou dojet i čekající pozvánky
        Pozvani::prijmoutCekajiciPro($user);

        if (session('aktivni_firma_ico')) {
            return;
        }

        $prvniFirma = $user->firmy()->first();
        if ($prvniFirma) {
            session(['aktivni_firma_ico' => $prvniFirma->ico]);
        }
    }

    private function zpetNaLogin(Request $request, string $zprava)
    {
        $mobil = $request->cookie('oauth_capacitor') === '1' || $request->cookie('oauth_mobile') === '1';
        Cookie::queue(Cookie::forget('oauth_capacitor'));
        Cookie::queue(Cookie::forget('oauth_mobile'));

        return redirect()->route($mobil ? 'mobile.prihlaseni' : 'login')
            ->withErrors(['email' => $zprava]);
    }

    /**
     * Custom Tab neumí sám zavřít — vrátíme stránku, která skočí na deep link
     * mobilní aplikace. Fallback tlačítko pro případ, že se redirect nespustí.
     */
    private function deepLinkStranka(string $deepLink)
    {
        $deepLinkAttr = e($deepLink);
        $deepLinkJs = json_encode($deepLink, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

        $html = <<<HTML
        <!DOCTYPE html>
        <html lang="cs">
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Přihlášení dokončeno</title>
            <style>
                body { font-family: -apple-system, system-ui, Segoe UI, Roboto, sans-serif; text-align: center; padding: 3rem 1.5rem; color: #2c3e50; }
                a { display: inline-block; margin-top: 1.5rem; padding: 0.85rem 1.5rem; background: #3498db; color: #fff; text-decoration: none; border-radius: 8px; font-weight: 600; }
            </style>
        </head>
        <body>
            <h2>Přihlášení proběhlo úspěšně</h2>
            <p>Vracíme vás zpět do aplikace…</p>
            <a href="{$deepLinkAttr}">Otevřít aplikaci TupTuDu</a>
            <script>window.location.replace({$deepLinkJs});</script>
        </body>
        </html>
        HTML;

        return response($html, 200, ['Content-Type' => 'text/html; charset=utf-8']);
    }
}
