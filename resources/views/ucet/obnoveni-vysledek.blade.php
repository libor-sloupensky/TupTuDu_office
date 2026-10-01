<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Obnovení účtu — TupTuDu</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
               background: #f5f7fa; margin: 0; padding: 3rem 1.25rem; color: #2c3e50; }
        .karta { max-width: 460px; margin: 0 auto; background: white; border-radius: 10px;
                 padding: 2rem; box-shadow: 0 2px 10px rgba(0,0,0,0.07); text-align: center; }
        h1 { font-size: 1.25rem; margin-top: 0; }
        .povedlo { color: #27ae60; }
        .nepovedlo { color: #c0392b; }
        a.tlacitko { display: inline-block; margin-top: 1.25rem; background: #3498db; color: white;
                     padding: 0.6rem 1.4rem; border-radius: 6px; text-decoration: none; }
    </style>
</head>
<body>
    <div class="karta">
        <h1 class="{{ $povedlo ? 'povedlo' : 'nepovedlo' }}">
            {{ $povedlo ? 'Účet je obnovený' : 'Odkaz neplatí' }}
        </h1>

        <p>{{ $zprava }}</p>

        <a class="tlacitko" href="{{ route('login') }}">Přejít na přihlášení</a>
    </div>
</body>
</html>
