@extends('layouts.app')

@section('title', 'Výsledek zpracování')

@section('styles')
<style>
    .result-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
    .result-header h2 { margin: 0; }
    .back-link { color: #3498db; text-decoration: none; }
    .back-link:hover { text-decoration: underline; }
    .filename { background: #eaf2f8; padding: 0.5rem 1rem; border-radius: 6px; margin-bottom: 1.5rem; font-size: 0.9rem; }
    .extracted-text { background: #fafafa; border: 1px solid #e0e0e0; border-radius: 6px; padding: 1.5rem; white-space: pre-wrap; font-family: 'Courier New', monospace; font-size: 0.9rem; line-height: 1.6; max-height: 500px; overflow-y: auto; margin-bottom: 1.5rem; }
    .raw-toggle { cursor: pointer; color: #3498db; font-size: 0.9rem; margin-bottom: 0.5rem; display: inline-block; }
    .raw-json { display: none; background: #2c3e50; color: #ecf0f1; padding: 1rem; border-radius: 6px; font-family: monospace; font-size: 0.8rem; max-height: 400px; overflow: auto; white-space: pre-wrap; }
</style>
@endsection

@section('content')
<div class="card">
    <div class="result-header">
        <h2>Extrahovaný text</h2>
        <a href="{{ route('invoices.create') }}" class="back-link">Nahrát další doklad</a>
    </div>

    <div class="filename">Soubor: <strong>{{ $filename }}</strong></div>

    <h3 style="margin-bottom: 0.75rem;">Rozpoznaný text:</h3>
    <div class="extracted-text">{{ $text ?: 'Žádný text nebyl rozpoznán.' }}</div>

    <span class="raw-toggle" onclick="document.getElementById('rawJson').style.display = document.getElementById('rawJson').style.display === 'none' ? 'block' : 'none';">
        Zobrazit/skrýt surový JSON z Textract
    </span>
    <div class="raw-json" id="rawJson">{{ $rawJson }}</div>
</div>
@endsection
