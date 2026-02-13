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
    .flash-msg { background: #d4edda; color: #155724; padding: 0.75rem 1rem; border-radius: 6px; margin-bottom: 1rem; }
    .month-downloads { margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid #e0e0e0; }
    .month-downloads h3 { font-size: 0.95rem; color: #555; margin-bottom: 0.75rem; }
    .month-list { display: flex; flex-wrap: wrap; gap: 0.5rem; }
    .month-link { display: inline-block; padding: 0.35rem 0.75rem; background: #eaf2f8; border-radius: 6px; color: #2c3e50; text-decoration: none; font-size: 0.85rem; }
    .month-link:hover { background: #d4e6f1; }
    .badge-dup { display: inline-block; padding: 0.1rem 0.4rem; border-radius: 4px; background: #fff3cd; color: #856404; font-size: 0.7rem; font-weight: 600; margin-left: 0.3rem; vertical-align: middle; }
    .btn-del-sm { background: none; border: none; color: #bdc3c7; cursor: pointer; font-size: 0.85rem; padding: 0.2rem 0.4rem; line-height: 1; }
    .btn-del-sm:hover { color: #e74c3c; }
    .btn-preview { color: #95a5a6; text-decoration: none; margin-left: 0.4rem; font-size: 0.85rem; vertical-align: middle; }
    .btn-preview:hover { color: #3498db; text-decoration: none; }

    .preview-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 1000; justify-content: center; align-items: center; }
    .preview-overlay.active { display: flex; }
    .preview-container { position: relative; width: 90vw; height: 90vh; max-width: 1000px; background: white; border-radius: 8px; overflow: hidden; display: flex; flex-direction: column; }
    .preview-container #previewContent { flex: 1; min-height: 0; display: flex; align-items: center; justify-content: center; overflow: auto; }
    .preview-container iframe { width: 100%; height: 100%; border: none; }
    .preview-container img { max-width: 100%; max-height: 100%; object-fit: contain; }
    .preview-close { position: absolute; top: 8px; right: 12px; background: rgba(0,0,0,0.5); color: white; border: none; font-size: 1.5rem; width: 36px; height: 36px; border-radius: 50%; cursor: pointer; z-index: 1001; line-height: 1; }
    .preview-close:hover { background: rgba(0,0,0,0.8); }
</style>
@endsection

@section('content')
<div class="card">
    <div class="page-header">
        <h2>Doklady</h2>
        <a href="{{ route('invoices.create') }}" class="btn-upload">Nahrát doklad</a>
    </div>

    @if (session('flash'))
        <div class="flash-msg">{{ session('flash') }}</div>
    @endif

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
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($doklady as $d)
                <tr>
                    <td>{{ $d->datum_vystaveni ? $d->datum_vystaveni->format('d.m.Y') : '-' }}</td>
                    <td>
                        <a href="{{ route('doklady.show', $d) }}">{{ $d->cislo_dokladu ?: $d->nazev_souboru }}</a>
                        @if ($d->cesta_souboru)
                            <a href="#" class="btn-preview" title="Náhled" onclick="openPreview('{{ route('doklady.preview', $d) }}', '{{ strtolower(pathinfo($d->nazev_souboru, PATHINFO_EXTENSION)) }}'); return false;">&#128065;</a>
                        @endif
                        @if ($d->duplicita_id)<span class="badge-dup" title="Možná duplicita">DUP</span>@endif
                    </td>
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
                    <td>
                        <form action="{{ route('doklady.destroy', $d) }}" method="POST" style="display:inline" onsubmit="return confirm('Smazat doklad {{ $d->cislo_dokladu ?: $d->nazev_souboru }}?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-del-sm" title="Smazat">&times;</button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>

        @php
            $mesice = $doklady
                ->filter(fn($d) => $d->datum_vystaveni)
                ->map(fn($d) => $d->datum_vystaveni->format('Y-m'))
                ->unique()
                ->sort()
                ->reverse();
        @endphp

        @if ($mesice->isNotEmpty())
        <div class="month-downloads">
            <h3>Stáhnout doklady za měsíc (ZIP)</h3>
            <div class="month-list">
                @foreach ($mesice as $m)
                    <a href="{{ route('doklady.downloadMonth', $m) }}" class="month-link">{{ \Carbon\Carbon::parse($m . '-01')->translatedFormat('F Y') }}</a>
                @endforeach
            </div>
        </div>
        @endif
    @endif
</div>

<div class="preview-overlay" id="previewOverlay">
    <div class="preview-container">
        <button class="preview-close" onclick="closePreview()">&times;</button>
        <div id="previewContent"></div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    function openPreview(url, ext) {
        const content = document.getElementById('previewContent');
        const overlay = document.getElementById('previewOverlay');

        if (ext === 'pdf') {
            content.innerHTML = '<iframe src="' + url + '"></iframe>';
        } else {
            content.innerHTML = '<img src="' + url + '" alt="Náhled dokladu">';
        }

        overlay.classList.add('active');
    }

    function closePreview() {
        const overlay = document.getElementById('previewOverlay');
        const content = document.getElementById('previewContent');
        overlay.classList.remove('active');
        content.innerHTML = '';
    }

    document.getElementById('previewOverlay').addEventListener('click', function(e) {
        if (e.target === this) closePreview();
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closePreview();
    });
</script>
@endsection
