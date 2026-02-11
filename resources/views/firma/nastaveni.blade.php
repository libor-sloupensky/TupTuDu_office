@extends('layouts.app')

@section('title', 'Nastavení firmy')

@section('styles')
<style>
    .form-group { margin-bottom: 1rem; }
    .form-group label { display: block; font-weight: 600; margin-bottom: 0.3rem; font-size: 0.9rem; color: #555; }
    .form-group input { width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #ddd; border-radius: 6px; font-size: 0.95rem; }
    .form-group input:focus { outline: none; border-color: #3498db; box-shadow: 0 0 0 2px rgba(52,152,219,0.2); }
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    .btn-save { background: #2ecc71; color: white; border: none; padding: 0.7rem 2rem; border-radius: 6px; font-size: 1rem; cursor: pointer; }
    .btn-save:hover { background: #27ae60; }
    .success-msg { background: #d4edda; color: #155724; padding: 0.75rem 1rem; border-radius: 6px; margin-bottom: 1rem; }
    .error-msg { color: #c0392b; font-size: 0.85rem; margin-top: 0.25rem; }
</style>
@endsection

@section('content')
<div class="card">
    <h2 style="margin-bottom: 1.5rem;">Nastavení firmy</h2>

    @if (session('success'))
        <div class="success-msg">{{ session('success') }}</div>
    @endif

    <form method="POST" action="{{ route('firma.ulozit') }}">
        @csrf

        <div class="form-row">
            <div class="form-group">
                <label for="ico">IČO *</label>
                <input type="text" id="ico" name="ico" value="{{ old('ico', $firma->ico ?? '') }}" required {{ $firma ? 'readonly' : '' }}>
                @error('ico') <div class="error-msg">{{ $message }}</div> @enderror
            </div>
            <div class="form-group">
                <label for="dic">DIČ</label>
                <input type="text" id="dic" name="dic" value="{{ old('dic', $firma->dic ?? '') }}">
            </div>
        </div>

        <div class="form-group">
            <label for="nazev">Název firmy *</label>
            <input type="text" id="nazev" name="nazev" value="{{ old('nazev', $firma->nazev ?? '') }}" required>
            @error('nazev') <div class="error-msg">{{ $message }}</div> @enderror
        </div>

        <div class="form-group">
            <label for="ulice">Ulice</label>
            <input type="text" id="ulice" name="ulice" value="{{ old('ulice', $firma->ulice ?? '') }}">
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="mesto">Město</label>
                <input type="text" id="mesto" name="mesto" value="{{ old('mesto', $firma->mesto ?? '') }}">
            </div>
            <div class="form-group">
                <label for="psc">PSČ</label>
                <input type="text" id="psc" name="psc" value="{{ old('psc', $firma->psc ?? '') }}">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="email">E-mail</label>
                <input type="email" id="email" name="email" value="{{ old('email', $firma->email ?? '') }}">
            </div>
            <div class="form-group">
                <label for="telefon">Telefon</label>
                <input type="text" id="telefon" name="telefon" value="{{ old('telefon', $firma->telefon ?? '') }}">
            </div>
        </div>

        <button type="submit" class="btn-save">Uložit nastavení</button>
    </form>
</div>
@endsection
