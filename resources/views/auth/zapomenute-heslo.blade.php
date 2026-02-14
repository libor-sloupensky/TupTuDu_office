@extends('layouts.app')
@section('title', 'Zapomenuté heslo')

@section('styles')
<style>
    .form-group { margin-bottom: 1rem; }
    .form-group label { display: block; font-weight: 600; margin-bottom: 0.3rem; font-size: 0.9rem; }
    .form-group input { width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px; font-size: 0.95rem; }
    .form-group input:focus { outline: none; border-color: #3498db; box-shadow: 0 0 0 2px rgba(52,152,219,0.15); }
    .btn-primary { background: #3498db; color: white; padding: 0.6rem 1.5rem; border: none; border-radius: 4px; cursor: pointer; font-size: 0.95rem; font-weight: 600; width: 100%; }
    .btn-primary:hover { background: #2980b9; }
    .link { color: #3498db; text-decoration: none; }
    .link:hover { text-decoration: underline; }
    .flash { background: #e8f8f0; border: 1px solid #27ae60; padding: 0.8rem; border-radius: 4px; margin-bottom: 1rem; color: #27ae60; }
    .error-msg { color: #e74c3c; font-size: 0.85rem; }
</style>
@endsection

@section('content')
<div class="card" style="max-width: 420px; margin: 0 auto;">
    <h2 style="margin-bottom: 0.5rem;">Zapomenuté heslo</h2>
    <p style="color: #666; margin-bottom: 1.5rem;">Zadejte svůj email a my vám pošleme odkaz pro obnovení hesla.</p>

    @if (session('flash'))
        <div class="flash">{{ session('flash') }}</div>
    @endif

    @if ($errors->any())
        @foreach ($errors->all() as $error)
            <div class="error-msg" style="margin-bottom: 0.5rem;">{{ $error }}</div>
        @endforeach
    @endif

    <form method="POST" action="{{ route('password.email') }}">
        @csrf
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" name="email" id="email" value="{{ old('email') }}" required autofocus>
        </div>
        <button type="submit" class="btn-primary">Odeslat odkaz</button>
    </form>

    <p style="text-align: center; margin-top: 1rem;">
        <a href="{{ route('login') }}" class="link">Zpět na přihlášení</a>
    </p>
</div>
@endsection
