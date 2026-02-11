@extends('layouts.app')

@section('title', 'Výsledek zpracování')

@section('styles')
<style>
    .result-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
    .result-header h2 { margin: 0; }
    .back-link { color: #3498db; text-decoration: none; }
    .back-link:hover { text-decoration: underline; }
    .filename { background: #eaf2f8; padding: 0.5rem 1rem; border-radius: 6px; margin-bottom: 1.5rem; font-size: 0.9rem; }

    .invoice-table { width: 100%; border-collapse: collapse; margin-bottom: 1.5rem; }
    .invoice-table th { text-align: left; padding: 0.6rem 1rem; background: #f0f4f8; border-bottom: 2px solid #d0d8e0; font-weight: 600; width: 180px; color: #555; font-size: 0.85rem; }
    .invoice-table td { padding: 0.6rem 1rem; border-bottom: 1px solid #e8ecf0; font-size: 0.95rem; }
    .invoice-table tr:hover { background: #f8fafb; }
    .amount { font-weight: 600; color: #2c3e50; }
    .category-badge { display: inline-block; padding: 0.2rem 0.6rem; border-radius: 12px; background: #e8f5e9; color: #2e7d32; font-size: 0.8rem; }
    .error-msg { background: #ffeaea; color: #c0392b; padding: 0.75rem 1rem; border-radius: 6px; margin-bottom: 1.5rem; }

    .section-toggle { cursor: pointer; color: #3498db; font-size: 0.9rem; margin-bottom: 0.5rem; display: inline-block; }
    .extracted-text { background: #fafafa; border: 1px solid #e0e0e0; border-radius: 6px; padding: 1.5rem; white-space: pre-wrap; font-family: 'Courier New', monospace; font-size: 0.9rem; line-height: 1.6; max-height: 500px; overflow-y: auto; margin-bottom: 1.5rem; display: none; }
    .raw-json { display: none; background: #2c3e50; color: #ecf0f1; padding: 1rem; border-radius: 6px; font-family: monospace; font-size: 0.8rem; max-height: 400px; overflow: auto; white-space: pre-wrap; }
</style>
@endsection

@section('content')
<div class="card">
    <div class="result-header">
        <h2>Zpracovaná faktura</h2>
        <a href="{{ route('invoices.create') }}" class="back-link">Nahrát další doklad</a>
    </div>

    <div class="filename">Soubor: <strong>{{ $filename }}</strong></div>

    @if (!empty($invoiceData['_error']))
        <div class="error-msg">{{ $invoiceData['_error'] }}</div>
    @else
        <h3 style="margin-bottom: 0.75rem;">Extrahovaná data:</h3>
        <table class="invoice-table">
            <tr>
                <th>Dodavatel</th>
                <td>{{ $invoiceData['dodavatel'] ?? '-' }}</td>
            </tr>
            <tr>
                <th>IČO</th>
                <td>{{ $invoiceData['ico'] ?? '-' }}</td>
            </tr>
            <tr>
                <th>DIČ</th>
                <td>{{ $invoiceData['dic'] ?? '-' }}</td>
            </tr>
            <tr>
                <th>Číslo faktury</th>
                <td>{{ $invoiceData['cislo_faktury'] ?? '-' }}</td>
            </tr>
            <tr>
                <th>Datum vystavení</th>
                <td>{{ $invoiceData['datum_vystaveni'] ?? '-' }}</td>
            </tr>
            <tr>
                <th>Datum splatnosti</th>
                <td>{{ $invoiceData['datum_splatnosti'] ?? '-' }}</td>
            </tr>
            <tr>
                <th>Celková částka</th>
                <td class="amount">
                    @if ($invoiceData['castka_celkem'] ?? null)
                        {{ number_format((float)$invoiceData['castka_celkem'], 2, ',', ' ') }}
                        {{ $invoiceData['mena'] ?? 'CZK' }}
                    @else
                        -
                    @endif
                </td>
            </tr>
            <tr>
                <th>DPH</th>
                <td>
                    @if ($invoiceData['castka_dph'] ?? null)
                        {{ number_format((float)$invoiceData['castka_dph'], 2, ',', ' ') }}
                        {{ $invoiceData['mena'] ?? 'CZK' }}
                    @else
                        -
                    @endif
                </td>
            </tr>
            <tr>
                <th>Kategorie</th>
                <td>
                    @if ($invoiceData['kategorie'] ?? null)
                        <span class="category-badge">{{ $invoiceData['kategorie'] }}</span>
                    @else
                        -
                    @endif
                </td>
            </tr>
        </table>
    @endif

    <span class="section-toggle" onclick="var el = document.getElementById('extractedText'); el.style.display = el.style.display === 'none' ? 'block' : 'none';">
        Zobrazit/skrýt rozpoznaný text
    </span>
    <div class="extracted-text" id="extractedText">{{ $text ?: 'Žádný text nebyl rozpoznán.' }}</div>

    <span class="section-toggle" onclick="var el = document.getElementById('rawJson'); el.style.display = el.style.display === 'none' ? 'block' : 'none';">
        Zobrazit/skrýt surový JSON z Textract
    </span>
    <div class="raw-json" id="rawJson">{{ $rawJson }}</div>
</div>
@endsection
