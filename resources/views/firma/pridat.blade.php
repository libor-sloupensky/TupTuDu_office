@extends('layouts.app')
@section('title', 'Přidat firmu')

@section('styles')
<style>
    .form-group { margin-bottom: 1rem; }
    .form-group label { display: block; font-weight: 600; margin-bottom: 0.3rem; font-size: 0.9rem; }
    .form-group input, .form-group select { width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px; font-size: 0.95rem; }
    .form-group input:focus { outline: none; border-color: #3498db; box-shadow: 0 0 0 2px rgba(52,152,219,0.15); }
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    .btn { padding: 0.6rem 1.5rem; border: none; border-radius: 4px; cursor: pointer; font-size: 0.95rem; font-weight: 600; }
    .btn-primary { background: #3498db; color: white; }
    .btn-primary:hover { background: #2980b9; }
    .btn-secondary { background: #95a5a6; color: white; }
    .btn-secondary:hover { background: #7f8c8d; }
    .btn-sm { padding: 0.4rem 1rem; font-size: 0.85rem; }
    .ares-row { display: flex; gap: 0.5rem; align-items: flex-end; }
    .ares-row .form-group { flex: 1; }
    .ares-status { font-size: 0.85rem; margin-top: 0.3rem; }
    .error-msg { color: #e74c3c; font-size: 0.85rem; }
</style>
@endsection

@section('content')
<div class="card" style="max-width: 550px; margin: 0 auto;">
    <h2 style="margin-bottom: 1.5rem;">Přidat firmu</h2>

    @if ($errors->any())
        <div style="background: #fef0f0; border: 1px solid #e74c3c; padding: 0.8rem; border-radius: 4px; margin-bottom: 1rem;">
            @foreach ($errors->all() as $error)
                <div class="error-msg">{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('firma.ulozitNovou') }}">
        @csrf

        <div class="ares-row">
            <div class="form-group">
                <label for="ico">IČO *</label>
                <input type="text" name="ico" id="ico" value="{{ old('ico') }}" maxlength="8" pattern="\d{8}" required>
            </div>
            <button type="button" class="btn btn-secondary btn-sm" onclick="lookupAres()" style="margin-bottom: 1rem; white-space: nowrap;">Vyhledat v ARES</button>
        </div>
        <div id="aresStatus" class="ares-status"></div>

        <div id="firmaFields" style="opacity: 0.5; pointer-events: none;">
            <div class="form-group">
                <label for="nazev">Název firmy</label>
                <input type="text" name="nazev" id="nazev" value="{{ old('nazev') }}" readonly>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="dic">DIČ</label>
                    <input type="text" name="dic" id="dic" value="{{ old('dic') }}" readonly>
                </div>
                <div class="form-group">
                    <label for="psc">PSČ</label>
                    <input type="text" name="psc" id="psc" value="{{ old('psc') }}" readonly>
                </div>
            </div>

            <div class="form-group">
                <label for="ulice">Ulice</label>
                <input type="text" name="ulice" id="ulice" value="{{ old('ulice') }}" readonly>
            </div>

            <div class="form-group">
                <label for="mesto">Město</label>
                <input type="text" name="mesto" id="mesto" value="{{ old('mesto') }}" readonly>
            </div>
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 0.5rem;">Přidat firmu</button>
    </form>
</div>
@endsection

@section('scripts')
<script>
function lookupAres() {
    var ico = document.getElementById('ico').value.trim();
    if (!/^\d{8}$/.test(ico)) { alert('IČO musí být přesně 8 číslic.'); return; }
    var st = document.getElementById('aresStatus');
    st.textContent = 'Hledám...'; st.style.color = '#666';
    fetch('/api/ares/' + ico)
        .then(function(r){ return r.json(); })
        .then(function(data){
            if (data.error) { st.textContent = data.error; st.style.color = '#e74c3c'; return; }
            document.getElementById('nazev').value = data.nazev || '';
            document.getElementById('dic').value = data.dic || '';
            document.getElementById('ulice').value = data.ulice || '';
            document.getElementById('mesto').value = data.mesto || '';
            document.getElementById('psc').value = data.psc || '';
            document.getElementById('firmaFields').style.opacity = '1';
            st.textContent = 'Nalezeno: ' + (data.nazev || ico); st.style.color = '#27ae60';
        })
        .catch(function(){ st.textContent = 'Chyba při komunikaci s ARES.'; st.style.color = '#e74c3c'; });
}
</script>
@endsection
