@extends('layouts.app')
@section('title', 'Klienti')

@section('styles')
<style>
    .form-group { margin-bottom: 1rem; }
    .form-group label { display: block; font-weight: 600; margin-bottom: 0.3rem; font-size: 0.9rem; }
    .form-group input { width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px; font-size: 0.95rem; }
    .form-group input:focus { outline: none; border-color: #3498db; box-shadow: 0 0 0 2px rgba(52,152,219,0.15); }
    .btn { padding: 0.5rem 1rem; border: none; border-radius: 4px; cursor: pointer; font-size: 0.85rem; font-weight: 600; }
    .btn-primary { background: #3498db; color: white; }
    .btn-primary:hover { background: #2980b9; }
    .btn-danger { background: #e74c3c; color: white; }
    .btn-danger:hover { background: #c0392b; }
    .btn-sm { padding: 0.3rem 0.7rem; font-size: 0.8rem; }
    .client-table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
    .client-table th, .client-table td { padding: 0.6rem 0.8rem; text-align: left; border-bottom: 1px solid #eee; font-size: 0.9rem; }
    .client-table th { background: #f8f9fa; font-weight: 600; color: #555; }
    .badge { display: inline-block; padding: 0.2rem 0.6rem; border-radius: 12px; font-size: 0.75rem; font-weight: 600; }
    .badge-success { background: #d4edda; color: #155724; }
    .badge-warning { background: #fff3cd; color: #856404; }
    .badge-danger { background: #f8d7da; color: #721c24; }
    .flash { background: #e8f8f0; border: 1px solid #27ae60; padding: 0.8rem; border-radius: 4px; margin-bottom: 1rem; color: #27ae60; }
    .error-msg { color: #e74c3c; font-size: 0.85rem; }
    .ares-row { display: flex; gap: 0.5rem; align-items: flex-end; }
    .ares-row .form-group { flex: 1; margin-bottom: 0; }
    .ares-status { font-size: 0.85rem; margin-top: 0.3rem; }
</style>
@endsection

@section('content')
<div class="card">
    <h2 style="margin-bottom: 1.5rem;">Správa klientů</h2>

    @if (session('flash'))
        <div class="flash">{{ session('flash') }}</div>
    @endif

    @if ($errors->any())
        @foreach ($errors->all() as $error)
            <div class="error-msg" style="margin-bottom: 0.5rem;">{{ $error }}</div>
        @endforeach
    @endif

    <form method="POST" action="{{ route('klienti.store') }}" style="margin-bottom: 1.5rem; padding: 1rem; background: #f8f9fa; border-radius: 6px;">
        @csrf
        <h3 style="margin-bottom: 0.8rem; font-size: 1rem;">Přidat klienta</h3>
        <div class="ares-row">
            <div class="form-group">
                <label for="klient_ico">IČO klienta</label>
                <input type="text" name="klient_ico" id="klient_ico" value="{{ old('klient_ico') }}" maxlength="8" pattern="\d{8}" required placeholder="12345678">
            </div>
            <button type="submit" class="btn btn-primary" style="margin-bottom: 0; white-space: nowrap;">Přidat</button>
        </div>
        <div id="aresStatus" class="ares-status"></div>
    </form>

    @if ($vazby->isEmpty())
        <p style="color: #666;">Zatím nemáte žádné klienty.</p>
    @else
        <table class="client-table">
            <thead>
                <tr>
                    <th>IČO</th>
                    <th>Název</th>
                    <th>Stav</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($vazby as $vazba)
                    <tr>
                        <td>{{ $vazba->klient_ico }}</td>
                        <td>{{ $vazba->klientFirma?->nazev ?? '—' }}</td>
                        <td>
                            @if ($vazba->stav === 'schvaleno')
                                <span class="badge badge-success">Schváleno</span>
                            @elseif ($vazba->stav === 'ceka_na_firmu')
                                <span class="badge badge-warning">Čeká na schválení</span>
                            @elseif ($vazba->stav === 'zamitnuto')
                                <span class="badge badge-danger">Zamítnuto</span>
                            @endif
                        </td>
                        <td>
                            <form method="POST" action="{{ route('klienti.destroy', $vazba->klient_ico) }}" onsubmit="return confirm('Opravdu odebrat klienta?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm">Odebrat</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
@endsection

@section('scripts')
<script>
var aresTimer = null;
document.getElementById('klient_ico').addEventListener('input', function() {
    clearTimeout(aresTimer);
    var ico = this.value.trim();
    var st = document.getElementById('aresStatus');
    if (ico.length < 8) { st.textContent = ''; return; }
    if (!/^\d{8}$/.test(ico)) return;
    st.textContent = 'Hledám...'; st.style.color = '#666';
    aresTimer = setTimeout(function() {
        fetch('/api/ares/' + ico)
            .then(function(r){ return r.json(); })
            .then(function(data){
                if (data.error) { st.textContent = data.error; st.style.color = '#e74c3c'; return; }
                st.textContent = data.nazev || ico; st.style.color = '#27ae60';
            })
            .catch(function(){ st.textContent = 'Chyba při komunikaci s ARES.'; st.style.color = '#e74c3c'; });
    }, 300);
});
</script>
@endsection
