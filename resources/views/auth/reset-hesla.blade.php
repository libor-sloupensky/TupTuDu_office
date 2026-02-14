@extends('layouts.app')
@section('title', 'Nové heslo')

@section('styles')
<style>
    .form-group { margin-bottom: 1rem; }
    .form-group label { display: block; font-weight: 600; margin-bottom: 0.3rem; font-size: 0.9rem; }
    .form-group input { width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px; font-size: 0.95rem; }
    .form-group input:focus { outline: none; border-color: #3498db; box-shadow: 0 0 0 2px rgba(52,152,219,0.15); }
    .btn-primary { background: #3498db; color: white; padding: 0.6rem 1.5rem; border: none; border-radius: 4px; cursor: pointer; font-size: 0.95rem; font-weight: 600; width: 100%; }
    .btn-primary:hover { background: #2980b9; }
    .error-msg { color: #e74c3c; font-size: 0.85rem; }
</style>
@endsection

@section('content')
<div class="card" style="max-width: 420px; margin: 0 auto;">
    <h2 style="margin-bottom: 1.5rem;">Nastavení nového hesla</h2>

    @if ($errors->any())
        <div style="background: #fef0f0; border: 1px solid #e74c3c; padding: 0.8rem; border-radius: 4px; margin-bottom: 1rem;">
            @foreach ($errors->all() as $error)
                <div class="error-msg">{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <input type="hidden" name="email" value="{{ $email }}">

        <div class="form-group">
            <label for="password">Nové heslo (min. 8 znaků)</label>
            <input type="password" name="password" id="password" required minlength="8" autofocus>
        </div>

        <div class="form-group">
            <label for="password_confirmation">Potvrzení hesla</label>
            <input type="password" name="password_confirmation" id="password_confirmation" required>
        </div>

        <button type="submit" class="btn-primary">Nastavit heslo</button>
    </form>
</div>
@endsection
