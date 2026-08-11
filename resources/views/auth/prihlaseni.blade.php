@extends('layouts.app')
@section('title', 'Přihlášení')

@section('styles')
<style>
    .form-group { margin-bottom: 1rem; }
    .form-group label { display: block; font-weight: 600; margin-bottom: 0.3rem; font-size: 0.9rem; }
    .form-group input { width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px; font-size: 0.95rem; }
    .form-group input:focus { outline: none; border-color: #3498db; box-shadow: 0 0 0 2px rgba(52,152,219,0.15); }
    .btn-primary { background: #3498db; color: white; padding: 0.6rem 1.5rem; border: none; border-radius: 4px; cursor: pointer; font-size: 0.95rem; font-weight: 600; width: 100%; }
    .btn-primary:hover { background: #2980b9; }
    .remember { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem; }
    .remember input { width: auto; }
    .link { color: #3498db; text-decoration: none; }
    .link:hover { text-decoration: underline; }
    .error-msg { color: #e74c3c; font-size: 0.85rem; margin-top: 0.2rem; }
    .flash { background: #e8f8f0; border: 1px solid #27ae60; padding: 0.8rem; border-radius: 4px; margin-bottom: 1rem; color: #27ae60; }
    .oddelovac { display: flex; align-items: center; gap: 0.75rem; margin: 1.25rem 0; }
    .oddelovac::before, .oddelovac::after { content: ''; flex: 1; height: 1px; background: #e5e5e5; }
    .oddelovac span { font-size: 0.75rem; color: #999; text-transform: uppercase; letter-spacing: 0.05em; }
    .google-btn { display: flex; align-items: center; justify-content: center; gap: 0.6rem; width: 100%; padding: 0.55rem; font-size: 0.95rem; font-weight: 600; background: white; color: #444; border: 1px solid #ddd; border-radius: 4px; text-decoration: none; box-sizing: border-box; }
    .google-btn:hover { background: #f7f9fa; }
</style>
@endsection

@section('content')
<div class="card" style="max-width: 420px; margin: 0 auto;">
    <h2 style="margin-bottom: 1.5rem;">Přihlášení</h2>

    @if (session('flash'))
        <div class="flash">{{ session('flash') }}</div>
    @endif

    @if ($errors->any())
        <div style="background: #fef0f0; border: 1px solid #e74c3c; padding: 0.8rem; border-radius: 4px; margin-bottom: 1rem;">
            @foreach ($errors->all() as $error)
                <div class="error-msg">{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" name="email" id="email" value="{{ old('email') }}" required autofocus>
        </div>

        <div class="form-group">
            <label for="password">Heslo</label>
            <input type="password" name="password" id="password" required>
        </div>

        <div class="remember">
            <input type="checkbox" name="remember" id="remember">
            <label for="remember" style="font-weight: normal; font-size: 0.9rem;">Zapamatovat si mě</label>
        </div>

        <button type="submit" class="btn-primary">Přihlásit se</button>
    </form>

    <div class="oddelovac"><span>nebo</span></div>

    <a href="{{ route('google.login') }}" class="google-btn">
        <svg width="18" height="18" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">
            <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
            <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
            <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
            <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
        </svg>
        Přihlásit přes Google
    </a>

    <p style="text-align: center; margin-top: 1rem;">
        <a href="{{ route('password.request') }}" class="link">Zapomenuté heslo?</a>
    </p>
    <p style="text-align: center; margin-top: 0.5rem; color: #666;">
        Nemáte účet? <a href="{{ route('register') }}" class="link">Zaregistrujte se</a>
    </p>
</div>
@endsection
