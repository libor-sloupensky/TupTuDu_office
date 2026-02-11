@extends('layouts.app')

@section('title', 'Doklady')

@section('styles')
<style>
    .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
    .page-header h2 { margin: 0; }
    .btn-upload { background: #3498db; color: white; text-decoration: none; padding: 0.5rem 1.2rem; border-radius: 6px; font-size: 0.9rem; }
    .btn-upload:hover { background: #2980b9; }
    .doklady-table { width: 100%; border-collapse: collapse; }
    .doklady-table th { text-align: left; padding: 0.6rem 0.75rem; background: #f0f4f8; border-bottom: 2px solid #d0d8e0; font-size: 0.8rem; color: #555; font-weight: 600; }
    .doklady-table td { padding: 0.6rem 0.75rem; border-bottom: 1px solid #e8ecf0; font-size: 0.9rem; }
    .doklady-table tr:hover { background: #f8fafb; }
    .doklady-table a { color: #3498db; text-decoration: none; }
    .doklady-table a:hover { text-decoration: underline; }
    .stav-dokonceno { color: #27ae60; font-weight: 600; }
    .stav-chyba { color: #e74c3c; font-weight: 600; }
    .stav-zpracovava { color: #f39c12; font-weight: 600; }
    .amount { text-align: right; font-weight: 600; }
    .adresat-ok { color: #27ae60; }
    .adresat-fail { color: #e74c3c; }
    .adresat-na { color: #95a5a6; }
    .empty-state { text-align: center; padding: 3rem; color: #999; }
    .warning-msg { background: #fff3cd; color: #856404; padding: 0.75rem 1rem; border-radius: 6px; margin-bottom: 1rem; }
</style>
@endsection

@section('content')
<div class="card">
    <div class="page-header">
        <h2>Doklady</h2>
        <a href="{{ route('invoices.create') }}" class="btn-upload">Nahrát doklad</a>
    </div>

    @if (!$firma)
        <div class="warning-msg">Nejdříve vyplňte <a href="{{ route('firma.nastaveni') }}">nastavení firmy</a>.</div>
    @endif

    @if ($doklady->isEmpty())
        <div class="empty-state">
            <p>Zatím žádné doklady.</p>
            <a href="{{ route('invoices.create') }}">Nahrát první doklad</a>
        </div>
    @else
        <table class="doklady-table">
            <thead>
                <tr>
                    <th>Datum</th>
                    <th>Číslo</th>
                    <th>Dodavatel</th>
                    <th style="text-align: right">Částka</th>
                    <th>Adresát</th>
                    <th>Stav</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($doklady as $d)
                <tr>
                    <td>{{ $d->datum_vystaveni ? $d->datum_vystaveni->format('d.m.Y') : '-' }}</td>
                    <td><a href="{{ route('doklady.show', $d) }}">{{ $d->cislo_dokladu ?: $d->nazev_souboru }}</a></td>
                    <td>{{ $d->dodavatel_nazev ?: '-' }}</td>
                    <td class="amount">
                        @if ($d->castka_celkem)
                            {{ number_format((float)$d->castka_celkem, 2, ',', ' ') }} {{ $d->mena }}
                        @else
                            -
                        @endif
                    </td>
                    <td>
                        @if (!$d->adresni)
                            <span class="adresat-na" title="Neadresní doklad">-</span>
                        @elseif ($d->overeno_adresat)
                            <span class="adresat-ok" title="Adresováno na naši firmu">OK</span>
                        @else
                            <span class="adresat-fail" title="Jiný adresát">Jiný</span>
                        @endif
                    </td>
                    <td>
                        @if ($d->stav === 'dokonceno')
                            <span class="stav-dokonceno">Hotovo</span>
                        @elseif ($d->stav === 'chyba')
                            <span class="stav-chyba">Chyba</span>
                        @else
                            <span class="stav-zpracovava">{{ $d->stav }}</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
@endsection
