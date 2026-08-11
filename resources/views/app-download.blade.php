<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#3498db">
    <title>TupTuDu Doklady — mobilní aplikace</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: -apple-system, system-ui, Segoe UI, Roboto, sans-serif; background: #f4f6f8; color: #2c3e50; }
        .wrap { max-width: 480px; margin: 0 auto; padding: 2rem 1.25rem; }
        .card { background: white; border-radius: 12px; padding: 1.75rem; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        h1 { margin: 0 0 0.4rem; font-size: 1.5rem; }
        .sub { color: #7f8c8d; font-size: 0.9rem; margin: 0 0 1.5rem; }
        .btn { display: flex; align-items: center; justify-content: center; gap: 0.6rem; width: 100%; padding: 1.1rem; font-size: 1.05rem; font-weight: 600; background: #3498db; color: white; border-radius: 10px; text-decoration: none; box-shadow: 0 4px 14px rgba(52,152,219,0.3); }
        .btn:active { background: #2980b9; }
        .meta { text-align: center; color: #95a5a6; font-size: 0.8rem; margin-top: 0.75rem; }
        h2 { font-size: 1rem; margin: 2rem 0 0.75rem; }
        ol { margin: 0; padding-left: 1.25rem; color: #555; font-size: 0.9rem; line-height: 1.7; }
        .note { background: #fffbea; border: 1px solid #f0c36d; color: #856404; padding: 0.85rem 1rem; border-radius: 8px; font-size: 0.85rem; margin-top: 1.5rem; }
        .zpet { display: block; text-align: center; margin-top: 1.5rem; color: #7f8c8d; font-size: 0.9rem; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <h1>TupTuDu Doklady</h1>
        <p class="sub">Skenování dokladů přímo z mobilu</p>

        <a href="{{ url('app/tuptudu-doklady.apk') }}" class="btn" download>
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                <polyline points="7 10 12 15 17 10"/>
                <line x1="12" y1="15" x2="12" y2="3"/>
            </svg>
            Stáhnout aplikaci
        </a>
        <p class="meta">Android · {{ $velikost }} · verze {{ $verze }}</p>

        <h2>Instalace</h2>
        <ol>
            <li>Klikněte na tlačítko výše — stáhne se soubor APK</li>
            <li>Otevřete stažený soubor (lišta oznámení nebo Stažené soubory)</li>
            <li>Android se zeptá na povolení instalace z tohoto zdroje — povolte</li>
            <li>Nainstalujte a přihlaste se stejnými údaji jako na webu</li>
        </ol>

        <div class="note">
            Aplikace není v Google Play, proto Android při instalaci varuje.
            Je to očekávané — soubor stahujete přímo z office.tuptudu.cz.
        </div>
    </div>

    <a href="{{ route('doklady.index') }}" class="zpet">Zpět do systému</a>
</div>
</body>
</html>
