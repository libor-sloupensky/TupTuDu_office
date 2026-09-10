@extends('layouts.app')

@section('title', 'Můj účet')

@section('styles')
<style>
    .karta { background: white; border: 1px solid #e0e6ec; border-radius: 8px; padding: 1.25rem; margin-bottom: 1.25rem; }
    .karta h3 { margin: 0 0 0.25rem; display: flex; align-items: center; gap: 0.5rem; font-size: 1rem; }
    .karta .popis { font-size: 0.85rem; color: #7f8c8d; margin: 0 0 1rem; }
    .radek { display: flex; justify-content: space-between; padding: 0.45rem 0; border-bottom: 1px solid #f0f3f6; font-size: 0.9rem; }
    .radek:last-child { border-bottom: none; }
    .radek .popisek { color: #7f8c8d; }
    .adresa { background: #f0f7ff; border: 1px solid #bee3f8; border-radius: 6px; padding: 0.6rem 1rem; font-weight: 600; color: #2b6cb0; word-break: break-all; }
    .prepinac { display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.75rem; }
    .stav-ulozeni { font-size: 0.82rem; margin: 0.5rem 0 0; }
</style>
@endsection

@section('content')
<div class="card">
    <h2>Můj účet</h2>

    <div class="karta">
        <h3><x-ikona name="user" :size="18" /> Přihlašovací údaje</h3>
        <div class="radek"><span class="popisek">Jméno</span><span>{{ $user->cele_jmeno }}</span></div>
        <div class="radek"><span class="popisek">E-mail</span><span>{{ $user->email }}</span></div>
    </div>

    <div class="karta">
        <h3><x-ikona name="folder" :size="18" /> Osobní doklady</h3>
        <p class="popis">
            Místo na vlastní soukromé doklady, oddělené od firemních. Vidíte je jen vy —
            účetní ani nikdo jiný se k nim nedostane.
        </p>

        <div class="prepinac">
            <label class="toggle-switch">
                <input type="checkbox" id="prepinacOsobni" {{ $osobni->osobni_aktivni ? 'checked' : '' }}>
                <span class="toggle-slider"></span>
            </label>
            <span style="font-weight: 600;">Používat osobní doklady</span>
        </div>

        <p style="font-size: 0.82rem; color: #888; margin: 0 0 1rem;">
            Vypnutím se osobní doklady přestanou nabízet ve výběru. <strong>Nic se nemaže</strong> —
            po zapnutí jsou zase všechny na svém místě.
        </p>

        <div class="radek" style="border: none; padding-bottom: 0.3rem;">
            <span class="popisek">Adresa pro zasílání dokladů</span>
        </div>
        <div class="adresa">{{ $osobni->email_doklady }}</div>
        <p style="font-size: 0.8rem; color: #888; margin-top: 0.4rem;">
            Co pošlete na tuhle adresu, přistane mezi vašimi osobními doklady.
        </p>

        <p id="stavUlozeni" class="stav-ulozeni"></p>
    </div>
</div>

<script>
document.getElementById('prepinacOsobni').addEventListener('change', function () {
    const stav = document.getElementById('stavUlozeni');
    const zapnuto = this.checked;

    fetch('{{ route('ucet.prepnoutOsobni') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
        },
        body: JSON.stringify({ zapnuto: zapnuto ? 1 : 0 }),
    })
    .then(r => r.json())
    .then(data => {
        if (!data.ok) throw new Error();
        stav.textContent = zapnuto ? 'Zapnuto.' : 'Vypnuto — doklady zůstávají uložené.';
        stav.style.color = '#27ae60';
    })
    .catch(() => {
        this.checked = !zapnuto;
        stav.textContent = 'Uložení se nepodařilo.';
        stav.style.color = '#c0392b';
    });
});
</script>
@endsection
