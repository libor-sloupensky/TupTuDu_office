<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, user-scalable=no">
    <meta name="theme-color" content="#3498db">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>TupTuDu Doklady — Přihlášení</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: -apple-system, system-ui, Segoe UI, Roboto, sans-serif; background: #f4f6f8; color: #2c3e50; -webkit-tap-highlight-color: transparent; }
        .wrap { min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 1.5rem; padding-bottom: env(safe-area-inset-bottom, 1.5rem); padding-top: env(safe-area-inset-top, 1.5rem); }
        .card { background: white; border-radius: 12px; padding: 1.75rem; box-shadow: 0 4px 20px rgba(0,0,0,0.08); width: 100%; max-width: 420px; }
        h1 { margin: 0 0 0.4rem; font-size: 1.6rem; text-align: center; }
        .sub { text-align: center; color: #7f8c8d; font-size: 0.9rem; margin-bottom: 1.6rem; }
        label { display: block; font-size: 0.85rem; color: #555; margin-bottom: 0.3rem; }
        input[type=email], input[type=password] { width: 100%; padding: 0.85rem; font-size: 1rem; border: 1px solid #d0d8e0; border-radius: 8px; margin-bottom: 1rem; -webkit-appearance: none; }
        input:focus { outline: none; border-color: #3498db; }
        button { width: 100%; padding: 0.95rem; font-size: 1rem; font-weight: 600; background: #3498db; color: white; border: none; border-radius: 8px; cursor: pointer; }
        button:active { background: #2980b9; }
        .err { background: #fef0f0; border: 1px solid #e74c3c; color: #c0392b; padding: 0.6rem 0.8rem; border-radius: 6px; font-size: 0.85rem; margin-bottom: 1rem; }
        .oddelovac { display: flex; align-items: center; gap: 0.75rem; margin: 1.25rem 0; }
        .oddelovac::before, .oddelovac::after { content: ''; flex: 1; height: 1px; background: #e0e6ec; }
        .oddelovac span { font-size: 0.75rem; color: #95a5a6; text-transform: uppercase; letter-spacing: 0.05em; }
        .google-btn { display: flex; align-items: center; justify-content: center; gap: 0.6rem; width: 100%; padding: 0.85rem; font-size: 1rem; font-weight: 600; background: white; color: #444; border: 1px solid #d0d8e0; border-radius: 8px; text-decoration: none; }
        .google-btn:active { background: #f4f6f8; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <h1>TupTuDu Doklady</h1>
        <p class="sub">Přihlaste se pro skenování</p>

        @if ($errors->any())
            <div class="err">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('mobile.login') }}">
            @csrf
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}" autocomplete="email" inputmode="email" required>

            <label for="password">Heslo</label>
            <input type="password" id="password" name="password" autocomplete="current-password" required>

            <button type="submit">Přihlásit se</button>
        </form>

        <div class="oddelovac"><span>nebo</span></div>

        <a href="{{ route('google.login') }}?mobile=1" id="googleBtn" class="google-btn">
            <svg width="20" height="20" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">
                <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
                <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
                <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
                <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
            </svg>
            Přihlásit přes Google
        </a>
    </div>
</div>

<script>
// Google OAuth nesmí běžet v embedded WebView — Google to blokuje chybou
// 'disallowed_useragent'. V nativní appce proto login otevřeme v Custom Tabu
// (@capacitor/browser). Backend po dokončení vrátí deep link
// cz.tuptudu.office://auth/done?token=XXX, appka jej zachytí (@capacitor/app)
// a otevře /mobile/auth-bridge/{token}, kde teprve vznikne session ve WebView.
(function () {
    if (!window.Capacitor || !window.Capacitor.isNativePlatform || !window.Capacitor.isNativePlatform()) {
        return; // Běžný prohlížeč — standardní redirect flow funguje
    }

    var Browser = window.Capacitor.Plugins.Browser;
    var App = window.Capacitor.Plugins.App;
    if (!Browser || !App) {
        console.warn('Chybí plugin @capacitor/browser nebo @capacitor/app — Google login nebude fungovat.');
        return;
    }

    var btn = document.getElementById('googleBtn');
    if (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            var url = new URL('{{ route('google.login') }}', window.location.origin);
            url.searchParams.set('capacitor', '1');
            Browser.open({ url: url.toString() });
        });
    }

    App.addListener('appUrlOpen', function (data) {
        if (!data || !data.url) return;

        var u;
        try { u = new URL(data.url); } catch (err) { return; }
        if (u.protocol !== 'cz.tuptudu.office:' || u.hostname !== 'auth' || u.pathname !== '/done') return;

        var token = u.searchParams.get('token');
        if (!token) return;

        Browser.close().catch(function () {});
        window.location.href = '/mobile/auth-bridge/' + encodeURIComponent(token);
    });
})();
</script>
</body>
</html>
