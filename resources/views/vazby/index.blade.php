@extends('layouts.app')
@section('title', 'Účetní')

@section('styles')
<style>
    .btn { padding: 0.5rem 1rem; border: none; border-radius: 4px; cursor: pointer; font-size: 0.85rem; font-weight: 600; }
    .btn-success { background: #27ae60; color: white; }
    .btn-success:hover { background: #219a52; }
    .btn-danger { background: #e74c3c; color: white; }
    .btn-danger:hover { background: #c0392b; }
    .btn-sm { padding: 0.3rem 0.7rem; font-size: 0.8rem; }
    .vazba-table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
    .vazba-table th, .vazba-table td { padding: 0.6rem 0.8rem; text-align: left; border-bottom: 1px solid #eee; font-size: 0.9rem; }
    .vazba-table th { background: #f8f9fa; font-weight: 600; color: #555; }
    .badge { display: inline-block; padding: 0.2rem 0.6rem; border-radius: 12px; font-size: 0.75rem; font-weight: 600; }
    .badge-success { background: #d4edda; color: #155724; }
    .badge-warning { background: #fff3cd; color: #856404; }
    .badge-danger { background: #f8d7da; color: #721c24; }
    .flash { background: #e8f8f0; border: 1px solid #27ae60; padding: 0.8rem; border-radius: 4px; margin-bottom: 1rem; color: #27ae60; }
    .actions { display: flex; gap: 0.5rem; }
</style>
@endsection

@section('content')
<div class="card">
    <h2 style="margin-bottom: 1.5rem;">Správa účetních vazeb</h2>

    @if (session('flash'))
        <div class="flash">{{ session('flash') }}</div>
    @endif

    @if ($vazby->isEmpty())
        <p style="color: #666;">Žádná účetní firma vás zatím nepřidala jako klienta.</p>
    @else
        <table class="vazba-table">
            <thead>
                <tr>
                    <th>Účetní firma</th>
                    <th>IČO</th>
                    <th>Stav</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($vazby as $vazba)
                    <tr>
                        <td>{{ $vazba->ucetniFirma?->nazev ?? '—' }}</td>
                        <td>{{ $vazba->ucetni_ico }}</td>
                        <td>
                            @if ($vazba->stav === 'schvaleno')
                                <span class="badge badge-success">Schváleno</span>
                            @elseif ($vazba->stav === 'ceka_na_firmu')
                                <span class="badge badge-warning">Čeká na vaše schválení</span>
                            @elseif ($vazba->stav === 'zamitnuto')
                                <span class="badge badge-danger">Zamítnuto</span>
                            @endif
                        </td>
                        <td>
                            @if ($vazba->stav === 'ceka_na_firmu')
                                <div class="actions">
                                    <form method="POST" action="{{ route('vazby.approve', $vazba->id) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-success btn-sm">Schválit</button>
                                    </form>
                                    <form method="POST" action="{{ route('vazby.reject', $vazba->id) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-danger btn-sm">Zamítnout</button>
                                    </form>
                                </div>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
@endsection
