@extends('layouts.app')

@section('title', 'Detail dokladu')

@section('styles')
<style>
    .detail-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
    .detail-header h2 { margin: 0; }
    .header-actions { display: flex; gap: 0.75rem; align-items: center; }
    .back-link { color: #3498db; text-decoration: none; }
    .back-link:hover { text-decoration: underline; }
    .btn-download { background: #3498db; color: white; text-decoration: none; padding: 0.4rem 1rem; border-radius: 6px; font-size: 0.85rem; }
    .btn-download:hover { background: #2980b9; }
    .btn-delete { background: #e74c3c; color: white; border: none; padding: 0.4rem 1rem; border-radius: 6px; font-size: 0.85rem; cursor: pointer; }
    .btn-delete:hover { background: #c0392b; }
    .duplicate-warning { background: #fff3cd; border: 1px solid #ffc107; color: #856404; padding: 0.75rem 1rem; border-radius: 6px; margin-bottom: 1rem; }
    .duplicate-warning a { color: #533f03; font-weight: 600; }

    .invoice-table { width: 100%; border-collapse: collapse; margin-bottom: 1.5rem; }
    .invoice-table th { text-align: left; padding: 0.6rem 1rem; background: #f0f4f8; border-bottom: 2px solid #d0d8e0; font-weight: 600; width: 180px; color: #555; font-size: 0.85rem; }
    .invoice-table td { padding: 0.6rem 1rem; border-bottom: 1px solid #e8ecf0; font-size: 0.95rem; }
    .invoice-table tr:hover { background: #f8fafb; }
    .amount { font-weight: 600; color: #2c3e50; }
    .category-badge { display: inline-block; padding: 0.2rem 0.6rem; border-radius: 12px; background: #e8f5e9; color: #2e7d32; font-size: 0.8rem; }
    .stav-dokonceno { color: #27ae60; font-weight: 600; }
    .stav-chyba { color: #e74c3c; font-weight: 600; }
    .stav-nekvalitni { color: #d4a017; font-weight: 600; }
    .stav-zpracovava { color: #f39c12; font-weight: 600; }
    .error-box { background: #ffeaea; color: #c0392b; padding: 0.75rem 1rem; border-radius: 6px; margin-bottom: 1rem; }

    .metadata-section { margin-top: 1.5rem; }
    .metadata-section h3 { font-size: 1rem; color: #2c3e50; margin-bottom: 0.75rem; border-bottom: 1px solid #e0e0e0; padding-bottom: 0.5rem; }
    .extracted-text { background: #fafafa; border: 1px solid #e0e0e0; border-radius: 6px; padding: 1rem; white-space: pre-wrap; font-family: 'Courier New', monospace; font-size: 0.85rem; line-height: 1.6; max-height: 500px; overflow-y: auto; margin-bottom: 1.5rem; }
    .ai-data-table { width: 100%; border-collapse: collapse; margin-bottom: 1.5rem; }
    .ai-data-table th { text-align: left; padding: 0.4rem 0.75rem; background: #f8f9fa; font-weight: 600; width: 160px; color: #666; font-size: 0.8rem; border-bottom: 1px solid #e8ecf0; }
    .ai-data-table td { padding: 0.4rem 0.75rem; font-size: 0.85rem; border-bottom: 1px solid #e8ecf0; font-family: monospace; }
    .section-toggle { cursor: pointer; color: #3498db; font-size: 0.9rem; margin-bottom: 0.5rem; display: inline-block; }
    .raw-json { background: #2c3e50; color: #ecf0f1; padding: 1rem; border-radius: 6px; font-family: monospace; font-size: 0.8rem; max-height: 400px; overflow: auto; white-space: pre-wrap; display: none; }
    .btn-preview-show { background: #8e44ad; color: white; text-decoration: none; padding: 0.4rem 1rem; border-radius: 6px; font-size: 0.85rem; }
    .btn-preview-show:hover { background: #7d3c98; color: white; }
    .preview-embed { margin-bottom: 1.5rem; border: 1px solid #e0e0e0; border-radius: 8px; overflow: auto; background: #f8f8f8; max-height: 80vh; }
    .preview-embed iframe { width: 100%; height: 700px; border: none; }
    .preview-embed img { width: 100%; height: auto; display: block; }
</style>
@endsection

@section('content')
<div class="card">
    <div class="detail-header">
        <h2>{{ $doklad->cislo_dokladu ?: $doklad->nazev_souboru }}</h2>
        <div class="header-actions">
            @if ($doklad->cesta_souboru)
                <a href="{{ route('doklady.preview', $doklad) }}" class="btn-preview-show" target="_blank">Náhled</a>
                <a href="{{ route('doklady.download', $doklad) }}" class="btn-download">Stáhnout</a>
            @endif
            <form action="{{ route('doklady.destroy', $doklad) }}" method="POST" style="display: inline;" onsubmit="return confirm('Opravdu smazat tento doklad? Soubor bude odstraněn i z cloudu.')">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn-delete">Smazat</button>
            </form>
            <a href="{{ route('doklady.index') }}" class="back-link">Zpět na seznam</a>
        </div>
    </div>

    @if ($doklad->duplicita_id)
        <div class="duplicate-warning">
            Možná duplicita &mdash; podobný doklad již existuje:
            <a href="{{ route('doklady.show', $doklad->duplicita_id) }}">
                {{ $doklad->duplicitaOriginal?->cislo_dokladu ?: '#' . $doklad->duplicita_id }}
            </a>
        </div>
    @endif

    @if ($doklad->duplicity->isNotEmpty())
        <div class="duplicate-warning">
            Tento doklad má {{ $doklad->duplicity->count() }} {{ $doklad->duplicity->count() === 1 ? 'možnou duplicitu' : 'možné duplicity' }}:
            @foreach ($doklad->duplicity as $dup)
                <a href="{{ route('doklady.show', $dup) }}">{{ $dup->cislo_dokladu ?: $dup->nazev_souboru }}</a>{{ !$loop->last ? ', ' : '' }}
            @endforeach
        </div>
    @endif

    @if ($doklad->stav === 'chyba' && $doklad->chybova_zprava)
        <div class="error-box">{{ $doklad->chybova_zprava }}</div>
    @endif

    @if ($doklad->kvalita === 'nizka' || $doklad->kvalita === 'necitelna')
        <div class="duplicate-warning" style="{{ $doklad->kvalita === 'necitelna' ? 'background: #f8d7da; border-color: #f5c6cb; color: #721c24;' : '' }}">
            @if ($doklad->kvalita === 'necitelna')
                Doklad je nečitelný &mdash; klíčové údaje nebylo možné rozpoznat.
            @else
                Upozornění: nízká kvalita dokladu.
            @endif
            @if ($doklad->kvalita_poznamka)
                {{ $doklad->kvalita_poznamka }}
            @endif
        </div>
    @endif

    @if ($doklad->adresni && !$doklad->overeno_adresat)
        <div style="background:#ffeaea;border:1px solid #f5c6cb;color:#721c24;padding:0.75rem 1rem;border-radius:6px;margin-bottom:1rem;font-weight:600;">
            Tento doklad je adresován jinému odběrateli
            ({{ $doklad->odberatel_nazev ?? '' }}{{ $doklad->odberatel_nazev && $doklad->odberatel_ico ? ', ' : '' }}{{ $doklad->odberatel_ico ? 'IČO: ' . $doklad->odberatel_ico : '' }})
            a nepatří do účetnictví Vaší firmy.
        </div>
    @endif

    <table class="invoice-table">
        <tr>
            <th>Soubor</th>
            <td>{{ $doklad->nazev_souboru }}</td>
        </tr>
        <tr>
            <th>Stav</th>
            <td>
                @if ($doklad->stav === 'dokonceno' && $doklad->adresni && !$doklad->overeno_adresat)
                    <span class="stav-chyba">Jiný odběratel</span>
                @elseif ($doklad->stav === 'dokonceno')
                    <span class="stav-dokonceno">Dokončeno</span>
                @elseif ($doklad->stav === 'nekvalitni')
                    @if ($doklad->kvalita === 'necitelna' || ($doklad->kvalita_poznamka && str_contains($doklad->kvalita_poznamka, 'Více dokladů')))
                        <span class="stav-chyba">Nekvalitní</span>
                    @else
                        <span class="stav-nekvalitni">Nekvalitní</span>
                    @endif
                @elseif ($doklad->stav === 'chyba')
                    <span class="stav-chyba">Chyba</span>
                @else
                    <span class="stav-zpracovava">{{ ucfirst($doklad->stav) }}</span>
                @endif
            </td>
        </tr>
        <tr>
            <th>Typ dokladu</th>
            <td>
                @php
                    $typLabels = ['faktura'=>'Faktura', 'uctenka'=>'Účtenka', 'pokladni_doklad'=>'Pokladní doklad', 'dobropis'=>'Dobropis', 'zalohova_faktura'=>'Zálohová faktura', 'pokuta'=>'Pokuta', 'jine'=>'Jiné'];
                @endphp
                {{ $typLabels[$doklad->typ_dokladu] ?? ucfirst($doklad->typ_dokladu ?? '-') }}
            </td>
        </tr>
        <tr>
            <th>Dodavatel</th>
            <td>{{ $doklad->dodavatel_nazev ?: '-' }}</td>
        </tr>
        <tr>
            <th>IČO dodavatele</th>
            <td>{{ $doklad->dodavatel_ico ?: '-' }}</td>
        </tr>
        <tr>
            <th>Číslo dokladu</th>
            <td>{{ $doklad->cislo_dokladu ?: '-' }}</td>
        </tr>
        <tr>
            <th>Datum vystavení</th>
            <td>{{ $doklad->datum_vystaveni ? $doklad->datum_vystaveni->format('d.m.Y') : '-' }}</td>
        </tr>
        <tr>
            <th>Datum splatnosti</th>
            <td>{{ $doklad->datum_splatnosti ? $doklad->datum_splatnosti->format('d.m.Y') : '-' }}</td>
        </tr>
        <tr>
            <th>Celková částka</th>
            <td class="amount">
                @if ($doklad->castka_celkem)
                    {{ number_format((float)$doklad->castka_celkem, 2, ',', ' ') }} {{ $doklad->mena }}
                @else
                    -
                @endif
            </td>
        </tr>
        <tr>
            <th>DPH</th>
            <td>
                @if ($doklad->castka_dph)
                    {{ number_format((float)$doklad->castka_dph, 2, ',', ' ') }} {{ $doklad->mena }}
                @else
                    -
                @endif
            </td>
        </tr>
        <tr>
            <th>Kategorie</th>
            <td>
                @if ($doklad->kategorie)
                    <span class="category-badge">{{ $doklad->kategorie }}</span>
                @else
                    -
                @endif
            </td>
        </tr>
        @if ($doklad->odberatel_nazev || $doklad->odberatel_ico)
        <tr>
            <th>Odběratel</th>
            <td>
                {{ $doklad->odberatel_nazev ?: '-' }}
                @if ($doklad->odberatel_ico)
                    <span style="color:#888; margin-left:0.5rem;">IČO: {{ $doklad->odberatel_ico }}</span>
                @endif
            </td>
        </tr>
        @endif
        <tr>
            <th>Adresát</th>
            <td>
                @if (!$doklad->adresni)
                    <span style="color: #27ae60; font-weight: 600;">&#10003; Neadresní doklad</span>
                @elseif ($doklad->overeno_adresat)
                    <span style="color: #27ae60; font-weight: 600;">&#10003; Adresováno na naši firmu</span>
                @else
                    <span style="color: #e74c3c; font-weight: 600;">&#9888; Jiný adresát</span>
                @endif
            </td>
        </tr>
        <tr>
            <th>Zdroj</th>
            <td>{{ $doklad->zdroj === 'email' ? 'Email' : 'Ruční nahrání' }}</td>
        </tr>
        <tr>
            <th>Nahráno</th>
            <td>{{ $doklad->created_at->format('d.m.Y H:i') }}</td>
        </tr>
    </table>

    @if ($doklad->cesta_souboru)
    <div class="metadata-section">
        <h3>Náhled dokladu</h3>
        @php $ext = strtolower(pathinfo($doklad->nazev_souboru, PATHINFO_EXTENSION)); @endphp
        <div class="preview-embed">
            @if ($ext === 'pdf')
                <iframe src="{{ route('doklady.preview', $doklad) }}"></iframe>
            @else
                <img src="{{ route('doklady.preview', $doklad) }}" alt="{{ $doklad->nazev_souboru }}">
            @endif
        </div>
    </div>
    @endif

    @if ($doklad->raw_ai_odpoved)
    <div class="metadata-section">
        <h3>AI extrahovaná data</h3>
        @php $aiData = json_decode($doklad->raw_ai_odpoved, true) ?? []; @endphp
        <table class="ai-data-table">
            @foreach ($aiData as $key => $value)
            <tr>
                <th>{{ $key }}</th>
                <td>{{ is_null($value) ? '-' : $value }}</td>
            </tr>
            @endforeach
        </table>

        <span class="section-toggle" onclick="var el = document.getElementById('rawJson'); el.style.display = el.style.display === 'none' ? 'block' : 'none';">
            Zobrazit/skrýt surový JSON
        </span>
        <div class="raw-json" id="rawJson">{{ json_encode($aiData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</div>
    </div>
    @endif

    @if ($doklad->raw_text)
    <div class="metadata-section">
        <h3>Rozpoznaný text (OCR)</h3>
        <div class="extracted-text">{{ $doklad->raw_text }}</div>
    </div>
    @endif
</div>
@endsection
