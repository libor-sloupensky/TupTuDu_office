@extends('layouts.app')

@section('title', 'Nastavení firmy')

@section('styles')
<style>
    .form-group { margin-bottom: 1rem; }
    .form-group label { display: block; font-weight: 600; margin-bottom: 0.3rem; font-size: 0.9rem; color: #555; }
    .form-group input, .form-group textarea { width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #ddd; border-radius: 6px; font-size: 0.95rem; }
    .form-group input:focus, .form-group textarea:focus { outline: none; border-color: #3498db; box-shadow: 0 0 0 2px rgba(52,152,219,0.2); }
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    .btn-save { background: #2ecc71; color: white; border: none; padding: 0.7rem 2rem; border-radius: 6px; font-size: 1rem; cursor: pointer; }
    .btn-save:hover { background: #27ae60; }
    .success-msg { background: #d4edda; color: #155724; padding: 0.75rem 1rem; border-radius: 6px; margin-bottom: 1rem; }
    .error-msg { color: #c0392b; font-size: 0.85rem; margin-top: 0.25rem; }
    .section { margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid #eee; }
    .section h3 { margin-bottom: 1rem; }

    .firma-info { display: grid; grid-template-columns: auto 1fr; gap: 0.3rem 1rem; font-size: 0.95rem; margin-bottom: 1rem; }
    .firma-info dt { font-weight: 600; color: #555; }
    .firma-info dd { margin: 0; }

    .toggle-switch { position: relative; display: inline-block; width: 50px; height: 26px; }
    .toggle-switch input { opacity: 0; width: 0; height: 0; }
    .toggle-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background: #ccc; border-radius: 26px; transition: 0.3s; }
    .toggle-slider:before { position: absolute; content: ""; height: 20px; width: 20px; left: 3px; bottom: 3px; background: white; border-radius: 50%; transition: 0.3s; }
    .toggle-switch input:checked + .toggle-slider { background: #2ecc71; }
    .toggle-switch input:checked + .toggle-slider:before { transform: translateX(24px); }
    .toggle-switch input:disabled + .toggle-slider { opacity: 0.5; cursor: not-allowed; }

    .kat-row { display: flex; gap: 0.5rem; align-items: center; margin-bottom: 0.5rem; }
    .kat-row input { flex: 1; padding: 0.4rem 0.6rem; border: 1px solid #ddd; border-radius: 6px; font-size: 0.9rem; }
    .kat-row input:first-child { max-width: 200px; }
    .kat-row .btn-remove { background: none; border: none; color: #e74c3c; cursor: pointer; font-size: 1.2rem; padding: 0 0.3rem; }
    .kat-row .btn-remove:hover { color: #c0392b; }
</style>
@endsection

@section('content')
<div class="card">
    <h2 style="margin-bottom: 1.5rem;">Nastavení firmy</h2>

    @if (session('success'))
        <div class="success-msg">{{ session('success') }}</div>
    @endif
    @if (session('flash'))
        <div class="success-msg">{{ session('flash') }}</div>
    @endif

    {{-- Firma info as plain text --}}
    @if ($firma)
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">
        <dl class="firma-info">
            <dt>IČO:</dt><dd>{{ $firma->ico }}</dd>
            <dt>DIČ:</dt><dd>{{ $firma->dic ?? '—' }}</dd>
            <dt>Název:</dt><dd>{{ $firma->nazev }}</dd>
            <dt>Ulice:</dt><dd>{{ $firma->ulice ?? '—' }}</dd>
            <dt>Město:</dt><dd>{{ $firma->mesto ?? '—' }}</dd>
            <dt>PSČ:</dt><dd>{{ $firma->psc ?? '—' }}</dd>
        </dl>
        <form method="POST" action="{{ route('firma.obnovitAres') }}" style="margin: 0; flex-shrink: 0;">
            @csrf
            <button type="submit" style="padding: 0.5rem 0.75rem; border: 1px solid #3498db; background: white; color: #3498db; border-radius: 6px; cursor: pointer; font-size: 0.85rem; white-space: nowrap;">Obnovit z ARES</button>
        </form>
    </div>

    {{-- Editable email + telefon --}}
    <form method="POST" action="{{ route('firma.ulozit') }}">
        @csrf
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
    @endif

    {{-- Toggle "Jsem účetní" --}}
    @if ($firma)
    <div class="section">
        <div style="display: flex; align-items: center; gap: 1rem;">
            <label class="toggle-switch">
                <input type="checkbox" id="toggleUcetni" {{ $jeUcetni ? 'checked' : '' }} {{ $toggleDisabledReason ? 'disabled' : '' }}>
                <span class="toggle-slider"></span>
            </label>
            <span style="font-weight: 600; font-size: 1rem;">Jsem účetní firma</span>
        </div>
        @if ($toggleDisabledReason)
            <p style="font-size: 0.85rem; color: #e67e22; margin-top: 0.5rem;">{{ $toggleDisabledReason }}</p>
        @endif
        <p style="font-size: 0.85rem; color: #888; margin-top: 0.5rem;">
            Zapnutím získáte přístup k záložce Klienti, kde můžete spravovat firmy, kterým vedete účetnictví.
        </p>
    </div>
    @endif

    {{-- Email pro doklady --}}
    @if ($firma && $firma->email_doklady)
    <div class="section">
        <h3>Email pro zasílání dokladů</h3>
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

    {{-- Účetní vazby --}}
    @if ($vazby->isNotEmpty())
    <div class="section">
        <h3>Účetní vazby</h3>

        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr>
                    <th style="padding: 0.6rem 0.8rem; text-align: left; border-bottom: 2px solid #eee; background: #f8f9fa; font-weight: 600; color: #555; font-size: 0.9rem;">Účetní firma</th>
                    <th style="padding: 0.6rem 0.8rem; text-align: left; border-bottom: 2px solid #eee; background: #f8f9fa; font-weight: 600; color: #555; font-size: 0.9rem;">IČO</th>
                    <th style="padding: 0.6rem 0.8rem; text-align: left; border-bottom: 2px solid #eee; background: #f8f9fa; font-weight: 600; color: #555; font-size: 0.9rem;">Stav</th>
                    <th style="padding: 0.6rem 0.8rem; border-bottom: 2px solid #eee; background: #f8f9fa;"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($vazby as $vazba)
                    <tr>
                        <td style="padding: 0.6rem 0.8rem; border-bottom: 1px solid #eee; font-size: 0.9rem;">{{ $vazba->ucetniFirma?->nazev ?? '—' }}</td>
                        <td style="padding: 0.6rem 0.8rem; border-bottom: 1px solid #eee; font-size: 0.9rem;">{{ $vazba->ucetni_ico }}</td>
                        <td style="padding: 0.6rem 0.8rem; border-bottom: 1px solid #eee; font-size: 0.9rem;">
                            @if ($vazba->stav === 'schvaleno')
                                <span style="display:inline-block; padding:0.2rem 0.6rem; border-radius:12px; font-size:0.75rem; font-weight:600; background:#d4edda; color:#155724;">Schváleno</span>
                            @elseif ($vazba->stav === 'ceka_na_firmu')
                                <span style="display:inline-block; padding:0.2rem 0.6rem; border-radius:12px; font-size:0.75rem; font-weight:600; background:#fff3cd; color:#856404;">Čeká na schválení</span>
                            @elseif ($vazba->stav === 'zamitnuto')
                                <span style="display:inline-block; padding:0.2rem 0.6rem; border-radius:12px; font-size:0.75rem; font-weight:600; background:#f8d7da; color:#721c24;">Zamítnuto</span>
                            @endif
                        </td>
                        <td style="padding: 0.6rem 0.8rem; border-bottom: 1px solid #eee;">
                            @if ($vazba->stav === 'ceka_na_firmu')
                                <div style="display: flex; gap: 0.5rem;">
                                    <form method="POST" action="{{ route('vazby.approve', $vazba->id) }}">
                                        @csrf
                                        <button type="submit" style="padding:0.3rem 0.7rem; border:none; border-radius:4px; cursor:pointer; font-size:0.8rem; font-weight:600; background:#27ae60; color:white;">Schválit</button>
                                    </form>
                                    <form method="POST" action="{{ route('vazby.reject', $vazba->id) }}">
                                        @csrf
                                        <button type="submit" style="padding:0.3rem 0.7rem; border:none; border-radius:4px; cursor:pointer; font-size:0.8rem; font-weight:600; background:#e74c3c; color:white;">Zamítnout</button>
                                    </form>
                                </div>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    {{-- Kategorie nákladů --}}
    @if ($firma)
    <div class="section">
        <h3>Kategorie nákladů</h3>
        <p style="font-size: 0.85rem; color: #888; margin-bottom: 1rem;">
            Definujte kategorie pro třídění dokladů. AI bude doklady automaticky zařazovat do těchto kategorií.
        </p>

        <form method="POST" action="{{ route('firma.ulozitKategorie') }}" id="kategorieForm">
            @csrf
            <div id="kategorieList">
                @foreach ($kategorie as $kat)
                <div class="kat-row" data-id="{{ $kat->id }}">
                    <input type="hidden" name="kategorie[{{ $loop->index }}][id]" value="{{ $kat->id }}">
                    <input type="text" name="kategorie[{{ $loop->index }}][nazev]" value="{{ $kat->nazev }}" placeholder="Název" required>
                    <input type="text" name="kategorie[{{ $loop->index }}][popis]" value="{{ $kat->popis }}" placeholder="Popis (příklady)">
                    <button type="button" class="btn-remove" onclick="removeKategorie(this)" title="Odebrat">&times;</button>
                </div>
                @endforeach
            </div>

            @error('kategorie')
                <div class="error-msg" style="margin-bottom: 0.75rem;">{{ $message }}</div>
            @enderror

            <div style="display: flex; gap: 0.75rem; margin-top: 0.75rem;">
                <button type="button" onclick="addKategorie()" style="padding: 0.5rem 1rem; border: 1px dashed #aaa; background: white; color: #555; border-radius: 6px; cursor: pointer; font-size: 0.9rem;">+ Přidat kategorii</button>
                <button type="submit" class="btn-save" style="background: #8e44ad;">Uložit kategorie</button>
            </div>
        </form>
    </div>
    @endif

    {{-- Pravidla zpracování --}}
    @if ($firma)
    <div class="section">
        <h3>Vlastní pravidla zpracování</h3>
        <p style="font-size: 0.85rem; color: #888; margin-bottom: 1rem;">
            Doplňková pravidla pro AI zpracování dokladů (nepovinné). Kategorie se definují výše, zde zadejte jen specifické instrukce.
        </p>

        <form method="POST" action="{{ route('firma.ulozitPravidla') }}">
            @csrf
            <div class="form-group">
                <textarea name="pravidla_zpracovani" id="pravidlaText" rows="6" maxlength="3000"
                    style="resize: vertical; line-height: 1.5; font-family: inherit; font-size: 0.85rem;"
                    placeholder="Např.: Doklady od dodavatele XY vždy zařadit do kategorie Služby."
                >{{ old('pravidla_zpracovani', $firma->pravidla_zpracovani ?? '') }}</textarea>
                <span id="pravidlaCounter" style="font-size: 0.8rem; color: #999;">0 / 3000</span>
            </div>

            @error('pravidla_zpracovani')
                <div class="error-msg" style="margin-bottom: 0.75rem;">{{ $message }}</div>
            @enderror

            <button type="submit" class="btn-save" style="background: #8e44ad;">Uložit pravidla</button>
        </form>
    </div>
    @endif
</div>

<script>
(function() {
    // Toggle účetní
    const toggle = document.getElementById('toggleUcetni');
    if (toggle && !toggle.disabled) {
        toggle.addEventListener('change', function() {
            const jeUcetni = this.checked;
            fetch('{{ route("firma.toggleUcetni") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ je_ucetni: jeUcetni ? 1 : 0 })
            })
            .then(r => r.json())
            .then(data => {
                if (data.ok) {
                    window.location.reload();
                } else {
                    alert(data.error || 'Chyba při přepínání.');
                    toggle.checked = !jeUcetni;
                }
            })
            .catch(() => {
                alert('Chyba připojení.');
                toggle.checked = !jeUcetni;
            });
        });
    }

    // Kategorie - přidat řádek
    let katIndex = {{ $kategorie->count() }};
    window.addKategorie = function() {
        const list = document.getElementById('kategorieList');
        const row = document.createElement('div');
        row.className = 'kat-row';
        row.innerHTML = `
            <input type="hidden" name="kategorie[${katIndex}][id]" value="">
            <input type="text" name="kategorie[${katIndex}][nazev]" placeholder="Název" required>
            <input type="text" name="kategorie[${katIndex}][popis]" placeholder="Popis (příklady)">
            <button type="button" class="btn-remove" onclick="removeKategorie(this)" title="Odebrat">&times;</button>
        `;
        list.appendChild(row);
        katIndex++;
        row.querySelector('input[type="text"]').focus();
    };

    window.removeKategorie = function(btn) {
        const row = btn.closest('.kat-row');
        if (document.querySelectorAll('.kat-row').length <= 1) {
            alert('Musíte mít alespoň jednu kategorii.');
            return;
        }
        row.remove();
        reindexKategorie();
    };

    function reindexKategorie() {
        document.querySelectorAll('.kat-row').forEach((row, i) => {
            row.querySelectorAll('input, select').forEach(input => {
                const name = input.getAttribute('name');
                if (name) {
                    input.setAttribute('name', name.replace(/kategorie\[\d+\]/, `kategorie[${i}]`));
                }
            });
        });
        katIndex = document.querySelectorAll('.kat-row').length;
    }

    // Pravidla counter
    const textarea = document.getElementById('pravidlaText');
    const counter = document.getElementById('pravidlaCounter');
    if (textarea && counter) {
        function updateCounter() {
            counter.textContent = textarea.value.length + ' / 3000';
        }
        textarea.addEventListener('input', updateCounter);
        updateCounter();
    }
})();
</script>
@endsection
