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
    </div>
</div>
</body>
</html>
