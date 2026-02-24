@extends('layouts.app')
@section('title', 'Ověření emailu')

@section('styles')
<style>
    .btn-primary { background: #3498db; color: white; padding: 0.6rem 1.5rem; border: none; border-radius: 4px; cursor: pointer; font-size: 0.95rem; font-weight: 600; }
    .btn-primary:hover { background: #2980b9; }
    .flash { background: #e8f8f0; border: 1px solid #27ae60; padding: 0.8rem; border-radius: 4px; margin-bottom: 1rem; color: #27ae60; }
    .flash-error { background: #fdecea; border: 1px solid #e74c3c; padding: 0.8rem; border-radius: 4px; margin-bottom: 1rem; color: #c0392b; }
</style>
@endsection

@section('content')
<div class="card" style="max-width: 500px; margin: 0 auto; text-align: center;">
    <h2 style="margin-bottom: 1rem;">Ověřte svůj email</h2>

    @if (session('flash_error'))
        <div class="flash-error">{{ session('flash_error') }}</div>
    @endif

    @if (session('flash'))
        <div class="flash">{{ session('flash') }}</div>
    @endif

    <p style="color: #666; margin-bottom: 1.5rem;">
        Na adresu <strong>{{ auth()->user()->email }}</strong> jsme odeslali ověřovací odkaz.
        Klikněte na něj pro aktivaci účtu.
    </p>

    <p style="color: #666; margin-bottom: 1.5rem;">
        Pokud jste email neobdrželi, můžete si jej nechat poslat znovu:
    </p>

    <form method="POST" action="{{ route('verification.resend') }}">
        @csrf
        <button type="submit" class="btn-primary">Znovu odeslat ověřovací email</button>
    </form>
</div>
@endsection
