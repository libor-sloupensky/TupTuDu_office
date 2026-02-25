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
        .firma-switcher-btn { background: #34495e; border: 1px solid #4a6278; color: #ecf0f1; padding: 0.3rem 0.6rem; border-radius: 4px; cursor: pointer; font-size: 0.8rem; max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display: flex; align-items: center; gap: 0.3rem; }
        .firma-switcher-btn:hover { background: #3d566e; }
        .firma-switcher-btn.klient-view { border-color: #e67e22; }
        .firma-switcher-btn .arrow { font-size: 0.6rem; }
        .firma-dropdown { display: none; position: absolute; top: 100%; right: 0; background: white; border-radius: 4px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); min-width: 220px; z-index: 100; margin-top: 0.3rem; }
        .firma-dropdown.open { display: block; }
        .firma-dropdown form { margin: 0; }
        .firma-dropdown button { display: block; width: 100%; text-align: left; padding: 0.6rem 1rem; border: none; background: none; cursor: pointer; font-size: 0.85rem; color: #333; }
        .firma-dropdown button:hover { background: #f0f7ff; }
        .firma-dropdown button.active { background: #e8f4fd; font-weight: 600; }
        .firma-dropdown .separator { padding: 0.4rem 1rem; font-size: 0.75rem; color: #999; text-transform: uppercase; letter-spacing: 0.5px; border-top: 1px solid #eee; }
        .firma-dropdown button.klient-item { padding-left: 1.5rem; color: #666; }
        .firma-dropdown .add-link { display: block; padding: 0.6rem 1rem; font-size: 0.85rem; color: #3498db; text-decoration: none; border-top: 1px solid #eee; margin-left: 0; }
        .firma-dropdown .add-link:hover { background: #f0f7ff; }
        .btn-logout { background: none; border: none; color: #e74c3c; cursor: pointer; font-size: 0.85rem; margin-left: 1rem; }
        .btn-logout:hover { text-decoration: underline; }
        .container { max-width: 1400px; margin: 2rem auto; padding: 0 1rem; width: fit-content; min-width: min(900px, 100%); }
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
                @php
                    $__user = auth()->user();
                    $__prohlizimKlienta = $__user->prohlizimKlienta();
                @endphp
                @if ($__user->firmy()->count() > 0)
                    <a href="{{ route('doklady.index') }}">Doklady</a>
                    @if (!$__prohlizimKlienta)
                        <a href="{{ route('firma.nastaveni') }}">Nastavení</a>
                    @endif
                @endif

                @php
                    $userFirmy = $__user->firmy;
                    $aktivniIco = session('aktivni_firma_ico');

                    // Klientské firmy (pro účetní)
                    $klientFirmy = collect();
                    $ucetniIcos = $__user->firmy()->wherePivot('role', 'ucetni')->pluck('ico')->toArray();
                    if (!empty($ucetniIcos)) {
                        $klientIcos = \App\Models\UcetniVazba::whereIn('ucetni_ico', $ucetniIcos)
                            ->where('stav', 'schvaleno')
                            ->pluck('klient_ico')
                            ->toArray();
                        if (!empty($klientIcos)) {
                            $klientFirmy = \App\Models\Firma::whereIn('ico', $klientIcos)->get();
                        }
                    }

                    // Název aktivní firmy (vlastní nebo klient)
                    $aktivniFirmaNazev = $userFirmy->firstWhere('ico', $aktivniIco)?->nazev
                        ?? $klientFirmy->firstWhere('ico', $aktivniIco)?->nazev
                        ?? $userFirmy->first()?->nazev
                        ?? 'Firma';
                    $maVicFirem = $userFirmy->count() + $klientFirmy->count() > 1;
                @endphp

                @if ($userFirmy->count() > 0)
                    <div class="firma-switcher">
                        <button type="button" class="firma-switcher-btn{{ $__prohlizimKlienta ? ' klient-view' : '' }}" onclick="document.getElementById('firmaDropdown').classList.toggle('open')">
                            @if ($maVicFirem)<span class="arrow">&#9662;</span>@endif
                            {{ $aktivniFirmaNazev }}
                        </button>
                        @if ($maVicFirem)
                        <div class="firma-dropdown" id="firmaDropdown">
                            @foreach ($userFirmy as $f)
                                <form method="POST" action="{{ route('firma.prepnout', $f->ico) }}">
                                    @csrf
                                    <button type="submit" class="{{ $f->ico === $aktivniIco ? 'active' : '' }}">
                                        {{ $f->nazev }}
                                    </button>
                                </form>
                            @endforeach
                            @if ($klientFirmy->count() > 0)
                                <div class="separator">Klienti</div>
                                @foreach ($klientFirmy as $kf)
                                    <form method="POST" action="{{ route('firma.prepnout', $kf->ico) }}">
                                        @csrf
                                        <button type="submit" class="klient-item{{ $kf->ico === $aktivniIco ? ' active' : '' }}">
                                            {{ $kf->nazev }}
                                        </button>
                                    </form>
                                @endforeach
                            @endif
                        </div>
                        @endif
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

    <footer style="text-align:center; padding:2rem 1rem 1rem; font-size:0.8rem; color:#aaa;">
        <a href="{{ route('privacy') }}" style="color:#999; text-decoration:none;">Zásady ochrany osobních údajů</a>
    </footer>

    <div class="version-info">
        V003<br>
        @php
            $lpPath = $_SERVER['DOCUMENT_ROOT'] . '/last_push.txt';
            $lastPush = file_exists($lpPath) ? trim(file_get_contents($lpPath)) : null;
        @endphp
        {{ $lastPush ?: '' }}
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
