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
    .karta.nebezpeci { border-color: #f5c6cb; }
    .karta.nebezpeci h3 { color: #c0392b; }
    .seznam-dopadu { font-size: 0.88rem; padding-left: 1.2rem; margin: 0 0 1rem; }
    .seznam-dopadu li { margin-bottom: 0.35rem; }
    .varovani { color: #c0392b; font-size: 0.85rem; }
    .popisek-potvrzeni { display: block; font-size: 0.85rem; margin-bottom: 0.4rem; }
    .radek-potvrzeni { display: flex; gap: 0.5rem; flex-wrap: wrap; }
    .radek-potvrzeni input { flex: 1; min-width: 220px; padding: 0.45rem 0.7rem; border: 1px solid #d0d8e0; border-radius: 6px; font-size: 0.9rem; }
    .btn-smazat-ucet { background: #c0392b; color: white; border: none; padding: 0.45rem 1rem; border-radius: 6px; cursor: pointer; font-size: 0.9rem; }
    .btn-smazat-ucet:disabled { background: #d8c0bd; cursor: not-allowed; }

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
    <div class="karta nebezpeci">
        <h3><x-ikona name="triangle-alert" :size="18" /> Smazání účtu</h3>
        <p class="popis">
            Účet se hned uzavře a po {{ \App\Services\SmazaniUctu::DNI_LHUTY }} dnech se tohle
            všechno nenávratně smaže. Do té doby jde smazání vzít zpět — odkaz vám přijde
            e-mailem. Než se k tomu odhodláte, přečtěte si, co zmizí.
        </p>

        <ul class="seznam-dopadu">
            <li>Váš účet — jméno, e-mail, telefon i heslo.</li>
            @if ($prehledSmazani['osobnich_dokladu'] > 0)
                <li><strong>Osobní doklady ({{ $prehledSmazani['osobnich_dokladu'] }})</strong> včetně nahraných souborů.</li>
            @else
                <li>Osobní doklady (zatím žádné nemáte).</li>
            @endif

            @foreach ($prehledSmazani['firmy_zmizi'] as $firma)
                <li>
                    <strong>{{ $firma['nazev'] }}</strong> — jste jediným uživatelem, takže firma zmizí
                    i s doklady ({{ $firma['dokladu'] }}).
                    @if ($firma['ucetni'])
                        <br><span class="varovani">Přístup ztratí i účetní firma {{ $firma['ucetni'] }}.</span>
                    @endif
                </li>
            @endforeach
        </ul>

        @if ($prehledSmazani['firmy_zustanou'])
            <p class="popis" style="margin-bottom: 0.75rem;">
                Zůstane: {{ implode(', ', $prehledSmazani['firmy_zustanou']) }} — jsou v nich i další
                lidé, takže z nich jen odejdete.
            </p>
        @endif

        <label for="potvrzeniSmazani" class="popisek-potvrzeni">
            Pro potvrzení opište svůj e-mail <strong>{{ $user->email }}</strong>:
        </label>
        <div class="radek-potvrzeni">
            <input type="text" id="potvrzeniSmazani" autocomplete="off" placeholder="{{ $user->email }}">
            <button type="button" id="btnSmazatUcet" class="btn-smazat-ucet" disabled>Smazat účet</button>
        </div>
        <p id="stavSmazani" class="stav-ulozeni"></p>
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

// Tlačítko se odemkne, teprve když e-mail sedí — i uzavření účtu je nepříjemné omylem.
const poleSmazani = document.getElementById('potvrzeniSmazani');
const btnSmazani = document.getElementById('btnSmazatUcet');
const mujEmail = @json($user->email);

poleSmazani.addEventListener('input', function () {
    btnSmazani.disabled = this.value.trim().toLowerCase() !== mujEmail.toLowerCase();
});

btnSmazani.addEventListener('click', function () {
    if (!confirm('Opravdu smazat účet? Účet se uzavře a po {{ \App\Services\SmazaniUctu::DNI_LHUTY }} dnech se smaže nenávratně.')) return;

    const stav = document.getElementById('stavSmazani');
    btnSmazani.disabled = true;
    stav.textContent = 'Zavírám účet…';
    stav.style.color = '#7f8c8d';

    fetch('{{ route('ucet.smazat') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
        },
        body: JSON.stringify({ potvrzeni: poleSmazani.value }),
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            window.location.href = data.presmerovat;
            return;
        }
        stav.textContent = data.error || 'Smazání se nepodařilo.';
        stav.style.color = '#c0392b';
        btnSmazani.disabled = false;
    })
    .catch(() => {
        stav.textContent = 'Smazání se nepodařilo — zkuste to prosím znovu.';
        stav.style.color = '#c0392b';
        btnSmazani.disabled = false;
    });
});
</script>
@endsection
