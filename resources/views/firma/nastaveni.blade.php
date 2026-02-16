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

    .usr-table { width: 100%; border-collapse: collapse; }
    .usr-table th { padding: 0.4rem 0.5rem; text-align: left; font-weight: 600; color: #888; font-size: 0.8rem; border-bottom: 2px solid #eee; text-transform: uppercase; letter-spacing: 0.03em; }
    .usr-table td { padding: 0.25rem 0.3rem; border-bottom: 1px solid #f0f0f0; vertical-align: middle; }
    .usr-input { width: 100%; padding: 0.35rem 0.5rem; border: 1px solid transparent; border-radius: 4px; font-size: 0.9rem; background: transparent; }
    .usr-input:hover { border-color: #e0e0e0; }
    .usr-input:focus { outline: none; border-color: #3498db; background: white; box-shadow: 0 0 0 2px rgba(52,152,219,0.15); }
    .usr-select { padding: 0.3rem 0.4rem; border: 1px solid transparent; border-radius: 4px; font-size: 0.85rem; background: transparent; cursor: pointer; width: 100%; }
    .usr-select:hover { border-color: #e0e0e0; }
    .usr-select:focus { outline: none; border-color: #3498db; }
    .usr-select:disabled { cursor: default; color: #999; }
    .usr-email { font-size: 0.9rem; padding-left: 0.5rem !important; color: #555; }
    .usr-remove { background: none; border: none; color: #ddd; cursor: pointer; font-size: 1.2rem; padding: 0; line-height: 1; }
    .usr-remove:hover { color: #e74c3c; }
    .usr-add { background: none; border: none; color: #bbb; cursor: pointer; font-size: 1.3rem; padding: 0; line-height: 1; font-weight: bold; }
    .usr-add:hover { color: #27ae60; }
    .usr-pending td { opacity: 0.6; }
    .usr-pending-name { padding-left: 0.5rem; }
    .usr-badge-pending { display: inline-block; padding: 0.1rem 0.4rem; border-radius: 10px; font-size: 0.65rem; font-weight: 600; background: #fff3cd; color: #856404; margin-left: 0.4rem; vertical-align: middle; }
    .usr-new .usr-input::placeholder { color: #ccc; }
    .usr-save-status { font-size: 0.8rem; color: #27ae60; opacity: 0; transition: opacity 0.3s; display: inline-block; margin-top: 0.3rem; }
    .usr-save-status.visible { opacity: 1; }
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

        @if ($errors->has('email') || $errors->has('jmeno'))
            <div class="error-msg" style="margin-bottom: 0.75rem;">{{ $errors->first('email') ?: $errors->first('jmeno') }}</div>
        @endif

        <table class="usr-table" id="usrTable">
            <thead>
                <tr>
                    <th>Jméno</th>
                    <th>Email</th>
                    <th style="width: 130px;">Role</th>
                    <th style="width: 30px;"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($uzivatele as $u)
                <tr data-user-id="{{ $u->id }}">
                    <td><input type="text" class="usr-input" value="{{ $u->cele_jmeno }}" data-field="jmeno" {{ $u->id === auth()->id() ? '' : '' }}></td>
                    <td class="usr-email">{{ $u->email }}</td>
                    <td>
                        <select class="usr-select" data-field="interni_role">
                            <option value="spravce" {{ $u->pivot->interni_role === 'spravce' ? 'selected' : '' }}>Správce</option>
                            <option value="superadmin" {{ $u->pivot->interni_role === 'superadmin' ? 'selected' : '' }}>Superadmin</option>
                        </select>
                    </td>
                    <td>
                        @if ($u->id !== auth()->id())
                        <button type="button" class="usr-remove" onclick="removeUser({{ $u->id }}, '{{ addslashes($u->cele_jmeno) }}')" title="Odebrat">&times;</button>
                        @endif
                    </td>
                </tr>
                @endforeach
                @foreach ($pozvani as $p)
                <tr class="usr-pending" data-pozvani-id="{{ $p->id }}">
                    <td><span class="usr-pending-name">{{ $p->jmeno }}</span></td>
                    <td class="usr-email">{{ $p->email }} <span class="usr-badge-pending">Pozvánka</span></td>
                    <td>
                        <select class="usr-select" disabled>
                            <option value="spravce" {{ $p->interni_role === 'spravce' ? 'selected' : '' }}>Správce</option>
                            <option value="superadmin" {{ $p->interni_role === 'superadmin' ? 'selected' : '' }}>Superadmin</option>
                        </select>
                    </td>
                    <td></td>
                </tr>
                @endforeach
                <tr class="usr-new">
                    <td><input type="text" class="usr-input" id="newUserJmeno" placeholder="Jméno..." value="{{ old('jmeno') }}"></td>
                    <td><input type="email" class="usr-input" id="newUserEmail" placeholder="Email..." value="{{ old('email') }}"></td>
                    <td>
                        <select class="usr-select" id="newUserRole">
                            <option value="spravce">Správce</option>
                            <option value="superadmin">Superadmin</option>
                        </select>
                    </td>
                    <td><button type="button" class="usr-add" onclick="addUser()" title="Přidat">+</button></td>
                </tr>
            </tbody>
        </table>
        <span class="usr-save-status" id="usrSaveStatus"></span>
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

    // ===== Uživatelé =====
    function showUsrStatus(text, color) {
        const el = document.getElementById('usrSaveStatus');
        if (!el) return;
        el.textContent = text;
        el.style.color = color || '#27ae60';
        el.classList.add('visible');
        setTimeout(() => el.classList.remove('visible'), 2000);
    }

    let usrSaveTimer = null;
    function scheduleUsrSave(userId) {
        if (usrSaveTimer) clearTimeout(usrSaveTimer);
        usrSaveTimer = setTimeout(() => saveUser(userId), 800);
    }

    function saveUser(userId) {
        const row = document.querySelector('tr[data-user-id="'+userId+'"]');
        if (!row) return;
        const jmeno = row.querySelector('[data-field="jmeno"]').value.trim();
        const role = row.querySelector('[data-field="interni_role"]').value;
        if (!jmeno) return;

        fetch('{{ url("/nastaveni/uzivatele") }}/' + userId, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify({ jmeno: jmeno, interni_role: role })
        })
        .then(r => r.json())
        .then(data => {
            if (data.ok) showUsrStatus('Uloženo', '#27ae60');
            else showUsrStatus(data.error || 'Chyba', '#e74c3c');
        })
        .catch(() => showUsrStatus('Chyba připojení', '#e74c3c'));
    }

    // Attach listeners to existing user rows
    document.querySelectorAll('#usrTable tr[data-user-id]').forEach(row => {
        const userId = row.dataset.userId;
        const nameInput = row.querySelector('[data-field="jmeno"]');
        const roleSelect = row.querySelector('[data-field="interni_role"]');
        if (nameInput) nameInput.addEventListener('input', () => scheduleUsrSave(userId));
        if (roleSelect) roleSelect.addEventListener('change', () => saveUser(userId));
    });

    window.removeUser = function(userId, name) {
        if (!confirm('Odebrat uživatele ' + name + '?')) return;
        fetch('{{ url("/nastaveni/uzivatele") }}/' + userId, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }
        })
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                document.querySelector('tr[data-user-id="'+userId+'"]').remove();
                showUsrStatus('Uživatel odebrán', '#27ae60');
            } else {
                showUsrStatus(data.error || 'Chyba', '#e74c3c');
            }
        })
        .catch(() => showUsrStatus('Chyba připojení', '#e74c3c'));
    };

    window.addUser = function() {
        const jmeno = document.getElementById('newUserJmeno').value.trim();
        const email = document.getElementById('newUserEmail').value.trim();
        const role = document.getElementById('newUserRole').value;
        if (!jmeno || !email) { showUsrStatus('Vyplňte jméno a email', '#e74c3c'); return; }

        fetch('{{ route("firma.pridatUzivatele") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify({ jmeno: jmeno, email: email, interni_role: role })
        })
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                showUsrStatus(data.message || 'Uživatel přidán', '#27ae60');
                // Reload to refresh the table
                setTimeout(() => window.location.reload(), 500);
            } else {
                showUsrStatus(data.error || 'Chyba', '#e74c3c');
            }
        })
        .catch(() => showUsrStatus('Chyba připojení', '#e74c3c'));
    };
})();
</script>
@endsection
