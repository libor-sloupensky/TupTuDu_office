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

    .kat-section-header { display: flex; align-items: center; gap: 0.5rem; cursor: pointer; user-select: none; }
    .kat-section-header h3 { margin: 0; }
    .kat-arrow { font-size: 0.7rem; color: #888; transition: transform 0.2s; }
    .kat-arrow.open { transform: rotate(90deg); }
    .kat-body { display: none; margin-top: 0.75rem; }
    .kat-body.open { display: block; }
    .kat-desc { font-size: 0.85rem; color: #888; margin-bottom: 0.75rem; }
    .kat-table { width: 100%; border-collapse: collapse; }
    .kat-table th { padding: 0.4rem 0.5rem; text-align: left; font-weight: 600; color: #888; font-size: 0.8rem; border-bottom: 2px solid #eee; text-transform: uppercase; letter-spacing: 0.03em; }
    .kat-table th:first-child { width: 30%; }
    .kat-table td { padding: 0.25rem 0.3rem; border-bottom: 1px solid #f0f0f0; vertical-align: middle; }
    .kat-table td:last-child { width: 30px; text-align: center; }
    .kat-table input { width: 100%; padding: 0.35rem 0.5rem; border: 1px solid transparent; border-radius: 4px; font-size: 0.9rem; background: transparent; }
    .kat-table input:hover { border-color: #e0e0e0; }
    .kat-table input:focus { outline: none; border-color: #3498db; background: white; box-shadow: 0 0 0 2px rgba(52,152,219,0.15); }
    .kat-table .btn-remove { background: none; border: none; color: #ddd; cursor: pointer; font-size: 1.1rem; padding: 0; line-height: 1; }
    .kat-table .btn-remove:hover { color: #e74c3c; }
    .kat-table tr.kat-empty input { color: #999; }
    .kat-table tr.kat-empty input::placeholder { color: #ccc; }
    .kat-save-status { font-size: 0.8rem; color: #27ae60; margin-left: 0.5rem; opacity: 0; transition: opacity 0.3s; }
    .kat-save-status.visible { opacity: 1; }
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
        <div class="kat-section-header" onclick="toggleKategorie()">
            <span class="kat-arrow" id="katArrow">&#9654;</span>
            <h3>Kategorie nákladů</h3>
            <span class="kat-save-status" id="katSaveStatus"></span>
        </div>
        <p class="kat-desc">
            Kategorie pro automatické třídění dokladů. AI zařazuje doklady podle tohoto seznamu.
        </p>

        <div class="kat-body" id="katBody">
            <table class="kat-table" id="katTable">
                <thead>
                    <tr>
                        <th>Kategorie</th>
                        <th>Příklady</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="katTbody">
                    @foreach ($kategorie as $kat)
                    <tr data-id="{{ $kat->id }}">
                        <td><input type="text" value="{{ $kat->nazev }}" data-field="nazev" placeholder="Název"></td>
                        <td><input type="text" value="{{ $kat->popis }}" data-field="popis" placeholder="příklady..."></td>
                        <td><button type="button" class="btn-remove" onclick="removeKat(this)" title="Odebrat">&times;</button></td>
                    </tr>
                    @endforeach
                    <tr class="kat-empty" data-id="">
                        <td><input type="text" value="" data-field="nazev" placeholder="Nová kategorie..."></td>
                        <td><input type="text" value="" data-field="popis" placeholder="příklady..."></td>
                        <td></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- Správa uživatelů (pouze superadmin) --}}
    @if ($firma && $jeSuperadmin)
    <div class="section">
        <h3>Uživatelé firmy</h3>

        <table style="width: 100%; border-collapse: collapse; margin-bottom: 1rem;">
            <thead>
                <tr>
                    <th style="padding: 0.5rem 0.8rem; text-align: left; border-bottom: 2px solid #eee; background: #f8f9fa; font-weight: 600; color: #555; font-size: 0.85rem;">Jméno</th>
                    <th style="padding: 0.5rem 0.8rem; text-align: left; border-bottom: 2px solid #eee; background: #f8f9fa; font-weight: 600; color: #555; font-size: 0.85rem;">Email</th>
                    <th style="padding: 0.5rem 0.8rem; text-align: left; border-bottom: 2px solid #eee; background: #f8f9fa; font-weight: 600; color: #555; font-size: 0.85rem;">Role</th>
                    <th style="padding: 0.5rem 0.8rem; border-bottom: 2px solid #eee; background: #f8f9fa;"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($uzivatele as $u)
                <tr>
                    <td style="padding: 0.5rem 0.8rem; border-bottom: 1px solid #eee; font-size: 0.9rem;">{{ $u->cele_jmeno }}</td>
                    <td style="padding: 0.5rem 0.8rem; border-bottom: 1px solid #eee; font-size: 0.9rem;">{{ $u->email }}</td>
                    <td style="padding: 0.5rem 0.8rem; border-bottom: 1px solid #eee; font-size: 0.9rem;">
                        @if ($u->pivot->interni_role === 'superadmin')
                            <span style="display:inline-block; padding:0.2rem 0.6rem; border-radius:12px; font-size:0.75rem; font-weight:600; background:#e8daef; color:#6c3483;">Superadmin</span>
                        @else
                            <span style="display:inline-block; padding:0.2rem 0.6rem; border-radius:12px; font-size:0.75rem; font-weight:600; background:#d5f5e3; color:#1e8449;">Správce</span>
                        @endif
                    </td>
                    <td style="padding: 0.5rem 0.8rem; border-bottom: 1px solid #eee;">
                        @if ($u->id !== auth()->id())
                        <form method="POST" action="{{ route('firma.odebratUzivatele', $u->id) }}" onsubmit="return confirm('Odebrat uživatele {{ $u->cele_jmeno }}?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" style="padding:0.25rem 0.6rem; border:1px solid #e74c3c; background:white; color:#e74c3c; border-radius:4px; cursor:pointer; font-size:0.8rem;">Odebrat</button>
                        </form>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>

        @if ($pozvani->isNotEmpty())
        <p style="font-size: 0.85rem; color: #888; margin-bottom: 0.5rem;">Čekající pozvánky:</p>
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 1rem;">
            <tbody>
                @foreach ($pozvani as $p)
                <tr>
                    <td style="padding: 0.4rem 0.8rem; border-bottom: 1px solid #f0f0f0; font-size: 0.85rem; color: #888;">{{ $p->jmeno }}</td>
                    <td style="padding: 0.4rem 0.8rem; border-bottom: 1px solid #f0f0f0; font-size: 0.85rem; color: #888;">{{ $p->email }}</td>
                    <td style="padding: 0.4rem 0.8rem; border-bottom: 1px solid #f0f0f0; font-size: 0.85rem;">
                        <span style="display:inline-block; padding:0.15rem 0.5rem; border-radius:12px; font-size:0.7rem; font-weight:600; background:#fff3cd; color:#856404;">Odesláno</span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif

        <form method="POST" action="{{ route('firma.pridatUzivatele') }}" style="background: #f8f9fa; border-radius: 8px; padding: 1rem; margin-top: 0.5rem;">
            @csrf
            <p style="font-weight: 600; font-size: 0.9rem; margin-bottom: 0.75rem;">Pozvat nového uživatele</p>
            @if ($errors->has('email') || $errors->has('jmeno'))
                <div class="error-msg" style="margin-bottom: 0.75rem;">{{ $errors->first('email') ?: $errors->first('jmeno') }}</div>
            @endif
            <div style="display: grid; grid-template-columns: 1fr 1fr auto; gap: 0.5rem; align-items: end;">
                <div>
                    <label style="font-size: 0.8rem; color: #666; display: block; margin-bottom: 0.2rem;">Jméno</label>
                    <input type="text" name="jmeno" value="{{ old('jmeno') }}" required style="width: 100%; padding: 0.4rem 0.6rem; border: 1px solid #ddd; border-radius: 6px; font-size: 0.9rem;">
                </div>
                <div>
                    <label style="font-size: 0.8rem; color: #666; display: block; margin-bottom: 0.2rem;">Email</label>
                    <input type="email" name="email" value="{{ old('email') }}" required style="width: 100%; padding: 0.4rem 0.6rem; border: 1px solid #ddd; border-radius: 6px; font-size: 0.9rem;">
                </div>
                <div>
                    <select name="interni_role" style="padding: 0.4rem 0.6rem; border: 1px solid #ddd; border-radius: 6px; font-size: 0.9rem;">
                        <option value="spravce">Správce</option>
                        <option value="superadmin">Superadmin</option>
                    </select>
                </div>
            </div>
            <button type="submit" style="margin-top: 0.75rem; padding: 0.5rem 1.2rem; background: #3498db; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 0.9rem;">Pozvat</button>
        </form>
    </div>
    @endif
</div>

<script>
(function() {
    const csrfToken = '{{ csrf_token() }}';

    // Toggle účetní
    const toggle = document.getElementById('toggleUcetni');
    if (toggle && !toggle.disabled) {
        toggle.addEventListener('change', function() {
            const jeUcetni = this.checked;
            fetch('{{ route("firma.toggleUcetni") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
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

    // ===== Kategorie =====
    let saveTimer = null;
    const SAVE_DELAY = 800; // ms after last change

    window.toggleKategorie = function() {
        const body = document.getElementById('katBody');
        const arrow = document.getElementById('katArrow');
        body.classList.toggle('open');
        arrow.classList.toggle('open');
    };

    function showSaveStatus(text, color) {
        const el = document.getElementById('katSaveStatus');
        el.textContent = text;
        el.style.color = color || '#27ae60';
        el.classList.add('visible');
        setTimeout(() => el.classList.remove('visible'), 2000);
    }

    function scheduleSave() {
        if (saveTimer) clearTimeout(saveTimer);
        saveTimer = setTimeout(saveKategorie, SAVE_DELAY);
    }

    function saveKategorie() {
        const rows = document.querySelectorAll('#katTbody tr');
        const kategorie = [];

        rows.forEach((row, idx) => {
            const nazev = row.querySelector('[data-field="nazev"]').value.trim();
            const popis = row.querySelector('[data-field="popis"]').value.trim();
            if (!nazev) return; // skip empty rows
            kategorie.push({
                id: row.dataset.id || '',
                nazev: nazev,
                popis: popis,
                poradi: idx + 1
            });
        });

        if (kategorie.length === 0) return;

        fetch('{{ route("firma.ulozitKategorie") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify({ kategorie: kategorie })
        })
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                showSaveStatus('Uloženo', '#27ae60');
                // Update row IDs from server response
                if (data.ids) {
                    const dataRows = Array.from(document.querySelectorAll('#katTbody tr:not(.kat-empty)'));
                    data.ids.forEach((id, i) => {
                        if (dataRows[i]) dataRows[i].dataset.id = id;
                    });
                }
                ensureEmptyRow();
            } else {
                showSaveStatus('Chyba ukládání', '#e74c3c');
            }
        })
        .catch(() => showSaveStatus('Chyba připojení', '#e74c3c'));
    }

    window.removeKat = function(btn) {
        const row = btn.closest('tr');
        const id = row.dataset.id;
        row.remove();
        ensureEmptyRow();

        if (id) {
            // Delete from server
            fetch('{{ url("/nastaveni/kategorie") }}/' + id, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                }
            })
            .then(r => r.json())
            .then(data => {
                if (data.ok) showSaveStatus('Odstraněno', '#27ae60');
            })
            .catch(() => {});
        }
    };

    function ensureEmptyRow() {
        const tbody = document.getElementById('katTbody');
        const emptyRows = tbody.querySelectorAll('tr.kat-empty');
        // Remove extra empty rows
        emptyRows.forEach(row => {
            const nazev = row.querySelector('[data-field="nazev"]').value.trim();
            if (!nazev && emptyRows.length > 1) row.remove();
        });
        // Make sure there's exactly one empty row at the end
        const lastRow = tbody.querySelector('tr:last-child');
        if (!lastRow || !lastRow.classList.contains('kat-empty') || lastRow.querySelector('[data-field="nazev"]').value.trim()) {
            addEmptyRow();
        }
    }

    function addEmptyRow() {
        const tbody = document.getElementById('katTbody');
        const tr = document.createElement('tr');
        tr.className = 'kat-empty';
        tr.dataset.id = '';
        tr.innerHTML = '<td><input type="text" value="" data-field="nazev" placeholder="Nová kategorie..."></td>' +
                        '<td><input type="text" value="" data-field="popis" placeholder="příklady..."></td>' +
                        '<td></td>';
        tbody.appendChild(tr);
        attachInputListeners(tr);
    }

    function attachInputListeners(scope) {
        (scope || document).querySelectorAll('#katTbody input').forEach(input => {
            if (input._katBound) return;
            input._katBound = true;
            input.addEventListener('input', function() {
                const row = this.closest('tr');
                // If typing in empty row, convert it to data row
                if (row.classList.contains('kat-empty') && this.dataset.field === 'nazev' && this.value.trim()) {
                    row.classList.remove('kat-empty');
                    // Add remove button
                    const lastTd = row.querySelector('td:last-child');
                    if (!lastTd.querySelector('.btn-remove')) {
                        lastTd.innerHTML = '<button type="button" class="btn-remove" onclick="removeKat(this)" title="Odebrat">&times;</button>';
                    }
                    ensureEmptyRow();
                }
                scheduleSave();
            });
            input.addEventListener('blur', function() {
                // If a data row nazev is now empty, remove it
                const row = this.closest('tr');
                if (!row.classList.contains('kat-empty') && this.dataset.field === 'nazev' && !this.value.trim()) {
                    const id = row.dataset.id;
                    row.remove();
                    ensureEmptyRow();
                    if (id) {
                        fetch('{{ url("/nastaveni/kategorie") }}/' + id, {
                            method: 'DELETE',
                            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }
                        }).catch(() => {});
                    }
                    scheduleSave();
                }
            });
        });
    }

    // Init listeners
    attachInputListeners(document);
})();
</script>
@endsection
