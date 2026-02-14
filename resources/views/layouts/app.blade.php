<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TupTuDu - @yield('title', 'Zpracování faktur')</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f5f5; color: #333; min-height: 100vh; }
        .navbar { background: #2c3e50; color: white; padding: 0.8rem 2rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem; }
        .navbar h1 { font-size: 1.3rem; font-weight: 600; }
        .navbar a { color: #ecf0f1; text-decoration: none; margin-left: 1.5rem; font-size: 0.9rem; }
        .navbar a:hover { color: #3498db; }
        .nav-links { display: flex; align-items: center; }
        .nav-user { display: flex; align-items: center; gap: 0.5rem; }
        .nav-user span { color: #95a5a6; font-size: 0.85rem; }
        .firma-switcher { position: relative; display: inline-block; margin-left: 1rem; }
        .firma-switcher-btn { background: #34495e; border: 1px solid #4a6278; color: #ecf0f1; padding: 0.3rem 0.6rem; border-radius: 4px; cursor: pointer; font-size: 0.8rem; max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .firma-switcher-btn:hover { background: #3d566e; }
        .firma-dropdown { display: none; position: absolute; top: 100%; right: 0; background: white; border-radius: 4px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); min-width: 220px; z-index: 100; margin-top: 0.3rem; }
        .firma-dropdown.open { display: block; }
        .firma-dropdown form { margin: 0; }
        .firma-dropdown button { display: block; width: 100%; text-align: left; padding: 0.6rem 1rem; border: none; background: none; cursor: pointer; font-size: 0.85rem; color: #333; }
        .firma-dropdown button:hover { background: #f0f7ff; }
        .firma-dropdown button.active { background: #e8f4fd; font-weight: 600; }
        .firma-dropdown .add-link { display: block; padding: 0.6rem 1rem; font-size: 0.85rem; color: #3498db; text-decoration: none; border-top: 1px solid #eee; margin-left: 0; }
        .firma-dropdown .add-link:hover { background: #f0f7ff; }
        .btn-logout { background: none; border: none; color: #e74c3c; cursor: pointer; font-size: 0.85rem; margin-left: 1rem; }
        .btn-logout:hover { text-decoration: underline; }
        .container { max-width: 900px; margin: 2rem auto; padding: 0 1rem; }
        .card { background: white; border-radius: 8px; padding: 2rem; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .version-info { position: fixed; bottom: 10px; right: 10px; text-align: right; font-family: monospace; font-size: 12px; color: #aaa; }
    </style>
    @yield('styles')
</head>
<body>
    <nav class="navbar">
        <h1>TupTuDu</h1>
        <div class="nav-links">
            @auth
                @if (auth()->user()->firmy()->count() > 0)
                    <a href="{{ route('doklady.index') }}">Doklady</a>
                    <a href="{{ route('firma.nastaveni') }}">Nastavení</a>
                    @if (auth()->user()->maRoli('ucetni'))
                        <a href="{{ route('klienti.index') }}">Klienti</a>
                    @endif
                    @if (auth()->user()->maRoli('firma') || auth()->user()->maRoli('dodavatel'))
                        <a href="{{ route('vazby.index') }}">Účetní</a>
                    @endif
                @endif

                @php
                    $userFirmy = auth()->user()->firmy;
                    $aktivniIco = session('aktivni_firma_ico');
                @endphp

                @if ($userFirmy->count() > 0)
                    <div class="firma-switcher">
                        <button type="button" class="firma-switcher-btn" onclick="document.getElementById('firmaDropdown').classList.toggle('open')">
                            {{ $userFirmy->firstWhere('ico', $aktivniIco)?->nazev ?? $userFirmy->first()?->nazev ?? 'Firma' }}
                        </button>
                        <div class="firma-dropdown" id="firmaDropdown">
                            @foreach ($userFirmy as $f)
                                <form method="POST" action="{{ route('firma.prepnout', $f->ico) }}">
                                    @csrf
                                    <button type="submit" class="{{ $f->ico === $aktivniIco ? 'active' : '' }}">
                                        {{ $f->nazev }} ({{ $f->ico }})
                                    </button>
                                </form>
                            @endforeach
                            <a href="{{ route('firma.pridat') }}" class="add-link">+ Přidat firmu</a>
                        </div>
                    </div>
                @endif

                <div class="nav-user">
                    <span>{{ auth()->user()->cele_jmeno }}</span>
                    <form method="POST" action="{{ route('logout') }}" style="margin: 0;">
                        @csrf
                        <button type="submit" class="btn-logout">Odhlásit</button>
                    </form>
                </div>
            @else
                <a href="{{ route('login') }}">Přihlášení</a>
                <a href="{{ route('register') }}">Registrace</a>
            @endauth
        </div>
    </nav>

    <div class="container">
        @yield('content')
    </div>

    <div class="version-info">
        V002<br>
        @php
            $lpPath = $_SERVER['DOCUMENT_ROOT'] . '/last_push.txt';
            $lastPush = file_exists($lpPath) ? trim(file_get_contents($lpPath)) : null;
        @endphp
        Poslední push: {{ $lastPush ?: 'N/A' }}
    </div>

    <script>
    document.addEventListener('click', function(e) {
        var dd = document.getElementById('firmaDropdown');
        if (dd && !e.target.closest('.firma-switcher')) {
            dd.classList.remove('open');
        }
    });
    </script>
    @yield('scripts')
</body>
</html>
