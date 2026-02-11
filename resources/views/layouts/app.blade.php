<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TupTuDu - @yield('title', 'Zpracování faktur')</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f5f5; color: #333; min-height: 100vh; }
        .navbar { background: #2c3e50; color: white; padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center; }
        .navbar h1 { font-size: 1.3rem; font-weight: 600; }
        .navbar a { color: #ecf0f1; text-decoration: none; margin-left: 1.5rem; }
        .navbar a:hover { color: #3498db; }
        .container { max-width: 900px; margin: 2rem auto; padding: 0 1rem; }
        .card { background: white; border-radius: 8px; padding: 2rem; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .version-info { position: fixed; bottom: 10px; right: 10px; text-align: right; font-family: monospace; font-size: 12px; color: #aaa; }
    </style>
    @yield('styles')
</head>
<body>
    <nav class="navbar">
        <h1>TupTuDu</h1>
        <div>
            <a href="{{ route('invoices.create') }}">Nahrát doklad</a>
            <a href="{{ route('doklady.index') }}">Doklady</a>
            <a href="{{ route('firma.nastaveni') }}">Nastavení</a>
        </div>
    </nav>

    <div class="container">
        @yield('content')
    </div>

    <div class="version-info">
        V001<br>
        Poslední push: {{ file_exists(public_path('last_push.txt')) ? file_get_contents(public_path('last_push.txt')) : 'N/A' }}
    </div>

    @yield('scripts')
</body>
</html>
