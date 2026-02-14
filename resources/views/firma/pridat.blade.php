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
    .role-options { display: grid; grid-template-columns: 1fr; gap: 0.5rem; margin-bottom: 1rem; }
    .role-option { display: flex; align-items: center; padding: 0.8rem; border: 2px solid #eee; border-radius: 6px; cursor: pointer; }
    .role-option:hover { border-color: #3498db; }
    .role-option input[type=radio] { margin-right: 0.8rem; }
    .role-option.selected { border-color: #3498db; background: #f0f8ff; }
    .role-label strong { display: block; }
    .role-label small { color: #666; }
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

        <div class="form-group">
            <label>Typ účtu *</label>
            <div class="role-options">
                <label class="role-option">
                    <input type="radio" name="role" value="ucetni" {{ old('role') === 'ucetni' ? 'checked' : '' }}>
                    <span class="role-label"><strong>Účetní firma</strong><small>Vedu účetnictví pro jiné firmy</small></span>
                </label>
                <label class="role-option">
                    <input type="radio" name="role" value="firma" {{ old('role', 'firma') === 'firma' ? 'checked' : '' }}>
                    <span class="role-label"><strong>Vlastní účetnictví</strong><small>Sám si vedu účetnictví</small></span>
                </label>
                <label class="role-option">
                    <input type="radio" name="role" value="dodavatel" {{ old('role') === 'dodavatel' ? 'checked' : '' }}>
                    <span class="role-label"><strong>Dodavatel dokladů</strong><small>Pouze nahrávám doklady</small></span>
                </label>
            </div>
        </div>

        <div class="ares-row">
            <div class="form-group">
                <label for="ico">IČO *</label>
                <input type="text" name="ico" id="ico" value="{{ old('ico') }}" maxlength="8" pattern="\d{8}" required>
            </div>
            <button type="button" class="btn btn-secondary btn-sm" onclick="lookupAres()" style="margin-bottom: 1rem; white-space: nowrap;">Vyhledat v ARES</button>
        </div>
        <div id="aresStatus" class="ares-status"></div>

        <div class="form-group">
            <label for="nazev">Název firmy *</label>
            <input type="text" name="nazev" id="nazev" value="{{ old('nazev') }}" required>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="dic">DIČ</label>
                <input type="text" name="dic" id="dic" value="{{ old('dic') }}">
            </div>
            <div class="form-group">
                <label for="psc">PSČ</label>
                <input type="text" name="psc" id="psc" value="{{ old('psc') }}">
            </div>
        </div>

        <div class="form-group">
            <label for="ulice">Ulice</label>
            <input type="text" name="ulice" id="ulice" value="{{ old('ulice') }}">
        </div>

        <div class="form-group">
            <label for="mesto">Město</label>
            <input type="text" name="mesto" id="mesto" value="{{ old('mesto') }}">
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 0.5rem;">Přidat firmu</button>
    </form>
</div>
@endsection

@section('scripts')
<script>
document.querySelectorAll('.role-option input[type=radio]').forEach(function(r) {
    r.addEventListener('change', function() {
        document.querySelectorAll('.role-option').forEach(function(o){ o.classList.remove('selected'); });
        if (r.checked) r.closest('.role-option').classList.add('selected');
    });
    if (r.checked) r.closest('.role-option').classList.add('selected');
});

function lookupAres() {
    var ico = document.getElementById('ico').value.trim();
    if (!/^\d{8}$/.test(ico)) { alert('IČO musí být přesně 8 číslic.'); return; }
    var st = document.getElementById('aresStatus');
    st.textContent = 'Hledám...'; st.style.color = '#666';
    fetch('/api/ares/' + ico)
        .then(function(r){ return r.json(); })
        .then(function(data){
            if (data.error) { st.textContent = data.error; st.style.color = '#e74c3c'; return; }
            if (data.nazev) document.getElementById('nazev').value = data.nazev;
            if (data.dic) document.getElementById('dic').value = data.dic;
            if (data.ulice) document.getElementById('ulice').value = data.ulice;
            if (data.mesto) document.getElementById('mesto').value = data.mesto;
            if (data.psc) document.getElementById('psc').value = data.psc;
            st.textContent = 'Nalezeno: ' + (data.nazev || ico); st.style.color = '#27ae60';
        })
        .catch(function(){ st.textContent = 'Chyba při komunikaci s ARES.'; st.style.color = '#e74c3c'; });
}
</script>
@endsection
