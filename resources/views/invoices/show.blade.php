@extends('layouts.app')

@section('title', 'Detail dokladu')

@section('styles')
<style>
    .detail-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
    .detail-header h2 { margin: 0; }
    .back-link { color: #3498db; text-decoration: none; }
    .back-link:hover { text-decoration: underline; }

    .invoice-table { width: 100%; border-collapse: collapse; margin-bottom: 1.5rem; }
    .invoice-table th { text-align: left; padding: 0.6rem 1rem; background: #f0f4f8; border-bottom: 2px solid #d0d8e0; font-weight: 600; width: 180px; color: #555; font-size: 0.85rem; }
    .invoice-table td { padding: 0.6rem 1rem; border-bottom: 1px solid #e8ecf0; font-size: 0.95rem; }
    .invoice-table tr:hover { background: #f8fafb; }
    .amount { font-weight: 600; color: #2c3e50; }
    .category-badge { display: inline-block; padding: 0.2rem 0.6rem; border-radius: 12px; background: #e8f5e9; color: #2e7d32; font-size: 0.8rem; }
    .stav-dokonceno { color: #27ae60; font-weight: 600; }
    .stav-chyba { color: #e74c3c; font-weight: 600; }
    .stav-zpracovava { color: #f39c12; font-weight: 600; }
    .error-box { background: #ffeaea; color: #c0392b; padding: 0.75rem 1rem; border-radius: 6px; margin-bottom: 1rem; }

    .section-toggle { cursor: pointer; color: #3498db; font-size: 0.9rem; margin-bottom: 0.5rem; display: inline-block; }
    .extracted-text { background: #fafafa; border: 1px solid #e0e0e0; border-radius: 6px; padding: 1.5rem; white-space: pre-wrap; font-family: 'Courier New', monospace; font-size: 0.9rem; line-height: 1.6; max-height: 500px; overflow-y: auto; margin-bottom: 1.5rem; display: none; }
    .raw-json { display: none; background: #2c3e50; color: #ecf0f1; padding: 1rem; border-radius: 6px; font-family: monospace; font-size: 0.8rem; max-height: 400px; overflow: auto; white-space: pre-wrap; }
</style>
@endsection

@section('content')
<div class="card">
    <div class="detail-header">
        <h2>{{ $doklad->cislo_dokladu ?: $doklad->nazev_souboru }}</h2>
        <a href="{{ route('doklady.index') }}" class="back-link">Zpět na seznam</a>
    </div>

    @if ($doklad->stav === 'chyba' && $doklad->chybova_zprava)
        <div class="error-box">{{ $doklad->chybova_zprava }}</div>
    @endif

    <table class="invoice-table">
        <tr>
            <th>Soubor</th>
            <td>{{ $doklad->nazev_souboru }}</td>
        </tr>
        <tr>
            <th>Stav</th>
            <td>
                @if ($doklad->stav === 'dokonceno')
                    <span class="stav-dokonceno">Dokončeno</span>
                @elseif ($doklad->stav === 'chyba')
                    <span class="stav-chyba">Chyba</span>
                @else
                    <span class="stav-zpracovava">{{ ucfirst($doklad->stav) }}</span>
                @endif
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
        <tr>
            <th>Adresát</th>
            <td>
                @if (!$doklad->adresni)
                    <span style="color: #95a5a6;">Neadresní doklad</span>
                @elseif ($doklad->overeno_adresat)
                    <span style="color: #27ae60; font-weight: 600;">Adresováno na naši firmu</span>
                @else
                    <span style="color: #e74c3c; font-weight: 600;">Jiný adresát</span>
                @endif
            </td>
        </tr>
        <tr>
            <th>Nahráno</th>
            <td>{{ $doklad->created_at->format('d.m.Y H:i') }}</td>
        </tr>
    </table>

    @if ($doklad->raw_text)
    <span class="section-toggle" onclick="var el = document.getElementById('extractedText'); el.style.display = el.style.display === 'none' ? 'block' : 'none';">
        Zobrazit/skrýt rozpoznaný text
    </span>
    <div class="extracted-text" id="extractedText">{{ $doklad->raw_text }}</div>
    @endif

    @if ($doklad->raw_ai_odpoved)
    <span class="section-toggle" onclick="var el = document.getElementById('rawJson'); el.style.display = el.style.display === 'none' ? 'block' : 'none';">
        Zobrazit/skrýt AI odpověď
    </span>
    <div class="raw-json" id="rawJson">{{ json_encode(json_decode($doklad->raw_ai_odpoved), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</div>
    @endif
</div>
@endsection
