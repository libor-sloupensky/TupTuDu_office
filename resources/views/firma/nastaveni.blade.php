@extends('layouts.app')

@section('title', 'Nastavení firmy')

@section('styles')
<style>
    .form-group { margin-bottom: 1rem; }
    .form-group label { display: block; font-weight: 600; margin-bottom: 0.3rem; font-size: 0.9rem; color: #555; }
    .form-group input { width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #ddd; border-radius: 6px; font-size: 0.95rem; }
    .form-group input:focus { outline: none; border-color: #3498db; box-shadow: 0 0 0 2px rgba(52,152,219,0.2); }
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    .btn-save { background: #2ecc71; color: white; border: none; padding: 0.7rem 2rem; border-radius: 6px; font-size: 1rem; cursor: pointer; }
    .btn-save:hover { background: #27ae60; }
    .success-msg { background: #d4edda; color: #155724; padding: 0.75rem 1rem; border-radius: 6px; margin-bottom: 1rem; }
    .error-msg { color: #c0392b; font-size: 0.85rem; margin-top: 0.25rem; }
</style>
@endsection

@section('content')
<div class="card">
    <h2 style="margin-bottom: 1.5rem;">Nastavení firmy</h2>

    @if (session('success'))
        <div class="success-msg">{{ session('success') }}</div>
    @endif

    <form method="POST" action="{{ route('firma.ulozit') }}">
        @csrf

        <div class="form-row">
            <div class="form-group">
                <label for="ico">IČO</label>
                <div style="display: flex; gap: 0.5rem; align-items: center;">
                    <input type="text" id="ico" value="{{ $firma->ico ?? '' }}" readonly style="background: #f0f0f0; flex: 1;">
                    <form method="POST" action="{{ route('firma.obnovitAres') }}" style="margin: 0;">
                        @csrf
                        <button type="submit" style="padding: 0.5rem 0.75rem; border: 1px solid #3498db; background: white; color: #3498db; border-radius: 6px; cursor: pointer; font-size: 0.8rem; white-space: nowrap;">ARES</button>
                    </form>
                </div>
            </div>
            <div class="form-group">
                <label for="dic">DIČ</label>
                <input type="text" id="dic" name="dic" value="{{ old('dic', $firma->dic ?? '') }}" readonly style="background: #f0f0f0;">
            </div>
        </div>

        <div class="form-group">
            <label for="nazev">Název firmy</label>
            <input type="text" id="nazev" name="nazev" value="{{ old('nazev', $firma->nazev ?? '') }}" readonly style="background: #f0f0f0;">
        </div>

        <div class="form-group">
            <label for="ulice">Ulice</label>
            <input type="text" id="ulice" name="ulice" value="{{ old('ulice', $firma->ulice ?? '') }}" readonly style="background: #f0f0f0;">
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="mesto">Město</label>
                <input type="text" id="mesto" name="mesto" value="{{ old('mesto', $firma->mesto ?? '') }}" readonly style="background: #f0f0f0;">
            </div>
            <div class="form-group">
                <label for="psc">PSČ</label>
                <input type="text" id="psc" name="psc" value="{{ old('psc', $firma->psc ?? '') }}" readonly style="background: #f0f0f0;">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="email">E-mail</label>
                <input type="email" id="email" name="email" value="{{ old('email', $firma->email ?? '') }}">
            </div>
            <div class="form-group">
                <label for="telefon">Telefon</label>
                <input type="text" id="telefon" name="telefon" value="{{ old('telefon', $firma->telefon ?? '') }}">
            </div>
        </div>

        <button type="submit" class="btn-save">Uložit nastavení</button>
    </form>

    @if ($firma && $firma->email_doklady)
    <div style="margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid #eee;">
        <h3 style="margin-bottom: 1rem;">Email pro zasílání dokladů</h3>
        <p style="margin-bottom: 0.5rem;">Doklady můžete posílat jako přílohy na adresu:</p>
        <div style="background: #f0f7ff; border: 1px solid #bee3f8; border-radius: 6px; padding: 0.75rem 1rem; font-size: 1.1rem; font-weight: 600; color: #2b6cb0;">
            {{ $firma->email_doklady }}
        </div>
        <p style="margin-top: 0.5rem; font-size: 0.85rem; color: #888;">
            Podporované formáty příloh: PDF, JPG, PNG (max 10 MB).
            Doklady budou automaticky zpracovány a zobrazí se v přehledu.
        </p>

        <form method="POST" action="{{ route('firma.ulozit') }}" style="margin-top: 1rem;">
            @csrf
            <input type="hidden" name="nazev" value="{{ $firma->nazev }}">
            <div class="form-group">
                <label for="email_doklady_heslo">IMAP heslo (pro automatické stahování)</label>
                <input type="password" id="email_doklady_heslo" name="email_doklady_heslo"
                       value="{{ old('email_doklady_heslo', $firma->email_doklady_heslo ?? '') }}"
                       placeholder="Heslo k emailové schránce {{ $firma->email_doklady }}">
            </div>
            <button type="submit" class="btn-save" style="background: #3498db;">Uložit heslo</button>
        </form>
    </div>
    @endif

    @if ($firma)
    <div style="margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid #eee;">
        <h3 style="margin-bottom: 0.5rem;">Pravidla zpracování dokladů</h3>
        <p style="font-size: 0.85rem; color: #888; margin-bottom: 1rem;">
            Vlastní pravidla upřesňující klasifikaci a kategorizaci dokladů.
            Pravidla jsou při ukládání ověřena AI — povoleny jsou pouze instrukce týkající se zpracování dokladů.
        </p>

        <form method="POST" action="{{ route('firma.ulozitPravidla') }}">
            @csrf
            <div class="form-group">
                <textarea name="pravidla_zpracovani" id="pravidlaText" rows="20" maxlength="3000"
                    style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #ddd; border-radius: 6px; font-size: 0.85rem; font-family: inherit; resize: vertical; line-height: 1.5;"
                >{{ old('pravidla_zpracovani', $firma->pravidla_zpracovani ?? '') }}</textarea>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 0.3rem;">
                    <span id="pravidlaCounter" style="font-size: 0.8rem; color: #999;">0 / 3000</span>
                    <button type="button" onclick="obnovitVychozi()"
                        style="padding: 0.3rem 0.75rem; border: 1px solid #95a5a6; background: white; color: #555; border-radius: 6px; cursor: pointer; font-size: 0.8rem;">
                        Obnovit výchozí pravidla
                    </button>
                </div>
            </div>

            @error('pravidla_zpracovani')
                <div class="error-msg" style="margin-bottom: 0.75rem;">{{ $message }}</div>
            @enderror

            <button type="submit" class="btn-save" style="background: #8e44ad;">Uložit pravidla</button>
        </form>
    </div>

    <script>
    (function() {
        const DEFAULT_PRAVIDLA = `KATEGORIE NÁKLADŮ:
pohonné_hmoty - Pohonné hmoty: benzín, nafta, CNG, LPG, AdBlue
stravování - Stravování: potraviny, restaurace, občerstvení
telekomunikace - Telekomunikace: telefon, internet, hosting
energie - Energie: elektřina, plyn, voda, teplo
doprava - Doprava a cestovné: jízdenky, parkování, mýtné, taxi, poštovné, ubytování
kancelářské_potřeby - Kancelářské potřeby: tonery, papír, drobný materiál
software - Software a licence: předplatné, cloudové služby
opravy_a_údržba - Opravy a údržba: servis, náhradní díly, revize
služby - Služby: poskytování služeb, účetnictví
reklama - Reklama: inzerce, propagace, marketing
školení - Školení: kurzy, semináře, konference
pojištění - Pojištění: vozidla, majetek, odpovědnost
nájem - Nájem: pronájem prostor, leasing
dokumenty - Dokumenty: smlouvy, objednávky, upomínky, protokoly
ostatní - Pokuty, penále a ostatní

KVALITA A STAVY (tolerantní k nedokonalostem skenů):
dobrá - Čitelný doklad, i rozmazaný/šikmý/s šumem = stav dokončeno
nízká - Klíčové údaje těžko čitelné = stav nekvalitní + poznámka
nečitelná - Zcela nečitelný dokument
Mírné rozmazání, nízké rozlišení nebo stíny NEJSOU důvodem ke snížení kvality.
Příjemce odlišný od firmy = poznámka "Chybný příjemce".
Účtenky bez IČO odběratele = odberatel_ico null.

ZPRACOVÁNÍ:
Více dokladů na jednom skenu = oddělené záznamy.
Dobropisy = záporná částka.`;

        const textarea = document.getElementById('pravidlaText');
        const counter = document.getElementById('pravidlaCounter');

        // Pre-fill with defaults if empty
        if (!textarea.value.trim()) {
            textarea.value = DEFAULT_PRAVIDLA;
        }

        function updateCounter() {
            counter.textContent = textarea.value.length + ' / 3000';
        }

        textarea.addEventListener('input', updateCounter);
        updateCounter();

        window.obnovitVychozi = function() {
            if (confirm('Přepsat aktuální pravidla výchozími?')) {
                textarea.value = DEFAULT_PRAVIDLA;
                updateCounter();
            }
        };
    })();
    </script>
    @endif
</div>
@endsection
