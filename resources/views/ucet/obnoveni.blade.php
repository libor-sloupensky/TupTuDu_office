@extends('layouts.app')

@section('title', 'Účet je uzavřený')

@section('styles')
<style>
    .uzavreno { background: white; border: 1px solid #f5c6cb; border-radius: 8px; padding: 1.5rem; }
    .uzavreno h2 { margin-top: 0; color: #c0392b; display: flex; align-items: center; gap: 0.5rem; }
    .datum { background: #fdf3f2; border: 1px solid #f5c6cb; border-radius: 6px; padding: 0.7rem 1rem; font-weight: 600; color: #c0392b; margin: 1rem 0; }
    .btn-obnovit { background: #27ae60; color: white; border: none; padding: 0.6rem 1.4rem; border-radius: 6px; cursor: pointer; font-size: 0.95rem; }
    .btn-obnovit:hover { background: #229954; }
    .poznamka { font-size: 0.85rem; color: #7f8c8d; margin-top: 1.25rem; }
</style>
@endsection

@section('content')
<div class="card">
    <div class="uzavreno">
        <h2><x-ikona name="triangle-alert" :size="20" /> Účet je uzavřený</h2>

        <p>
            Požádal jste o smazání účtu. Do aplikace se zatím nedostanete, ale ještě není
            nic ztracené — do uvedeného data jde všechno vrátit zpátky.
        </p>

        <div class="datum">
            Nenávratné smazání: {{ $user->smazani_k->timezone('Europe/Prague')->format('j. n. Y v H:i') }}
        </div>

        <form method="POST" action="{{ route('ucet.obnovit') }}">
            @csrf
            <button type="submit" class="btn-obnovit">Obnovit účet</button>
        </form>

        <p class="poznamka">
            Po uvedeném datu se účet i vaše doklady smažou a vrátit je už nepůjde.
            Chcete-li smazat okamžitě, bez čekání, napište nám na
            <a href="mailto:info@tuptudu.cz">info@tuptudu.cz</a>.
        </p>
    </div>
</div>
@endsection
