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
    .usr-save-status { font-size: 0.9rem; color: #27ae60; opacity: 0; transition: opacity 0.3s; display: inline-block; margin-top: 0.5rem; font-weight: 600; }
    .usr-save-status.visible { opacity: 1; }

    .badge-pending { display: inline-flex; align-items: center; justify-content: center; min-width: 20px; height: 20px; padding: 0 6px; border-radius: 10px; font-size: 0.7rem; font-weight: 700; background: #e74c3c; color: white; margin-left: 0.3rem; }
    .client-table { width: 100%; border-collapse: collapse; margin-top: 0.75rem; }
    .client-table th, .client-table td { padding: 0.5rem 0.6rem; text-align: left; border-bottom: 1px solid #eee; font-size: 0.9rem; }
    .client-table th { background: #f8f9fa; font-weight: 600; color: #555; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.03em; }
    .badge { display: inline-block; padding: 0.2rem 0.6rem; border-radius: 12px; font-size: 0.75rem; font-weight: 600; }
    .badge-success { background: #d4edda; color: #155724; }
    .badge-warning { background: #fff3cd; color: #856404; }
    .badge-danger { background: #f8d7da; color: #721c24; }
    .btn-sm { padding: 0.3rem 0.7rem; border: none; border-radius: 4px; cursor: pointer; font-size: 0.8rem; font-weight: 600; }
    .btn-sm-success { background: #27ae60; color: white; }
    .btn-sm-danger { background: #e74c3c; color: white; }
    .btn-sm-outline { background: white; color: #e74c3c; border: 1px solid #e74c3c; }
    .btn-sm-outline:hover { background: #fde8e8; }
    .lookup-result { margin-top: 0.75rem; padding: 0.75rem 1rem; border-radius: 6px; font-size: 0.9rem; }
    .lookup-result.info { background: #e8f4fd; border: 1px solid #bee3f8; color: #2b6cb0; }
    .lookup-result.warning { background: #fff8e1; border: 1px solid #ffe082; color: #795548; }
    .lookup-result.error { background: #fde8e8; border: 1px solid #f5c6cb; color: #721c24; }
    .perm-group { display: flex; flex-direction: column; gap: 0.3rem; }
    .perm-item { display: flex; align-items: center; gap: 0.4rem; font-size: 0.8rem; color: #555; white-space: nowrap; }
    .perm-item input[type="checkbox"] { margin: 0; accent-color: #27ae60; }
    .perm-item input[type="checkbox"]:disabled { accent-color: #999; }
    .perm-save-ok { font-size: 0.75rem; color: #27ae60; margin-left: 0.3rem; opacity: 0; transition: opacity 0.3s; }
    .perm-save-ok.visible { opacity: 1; }
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

    {{-- Nastavení firmy --}}
    @if ($firma)
    <div class="section" style="margin-top: 0; padding-top: 0; border-top: none;">
        <div class="kat-section-header" onclick="toggleSection('firmaInfo')">
            <span class="kat-arrow" id="firmaInfoArrow">&#9654;</span>
            <h3>🏢 Údaje firmy</h3>
        </div>
        <p class="kat-desc">Základní údaje firmy, kontaktní informace</p>

        <div class="kat-body" id="firmaInfoBody">
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
        </div>
    </div>
    @endif

    {{-- Účetní napojení --}}
    @if ($firma)
    <div class="section">
        <div class="kat-section-header" onclick="toggleUcetniNapojeni()">
            <span class="kat-arrow {{ $expandUcetni ? 'open' : '' }}" id="ucetniArrow">&#9654;</span>
            <h3>📊 Účetní napojení</h3>
            @if ($cekajiciVazby > 0)
                <span class="badge-pending">{{ $cekajiciVazby }}</span>
            @endif
        </div>
        <p class="kat-desc">Napojení na účetní firmu nebo správa klientů</p>

        <div class="kat-body {{ $expandUcetni ? 'open' : '' }}" id="ucetniBody">

            {{-- Stav A: Jsem účetní firma → správa klientů --}}
            @if ($jeUcetni)
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem;">
                    <span style="font-weight: 600; color: #27ae60;">Jste účetní firma</span>
                    <button type="button" class="btn-sm btn-sm-outline" onclick="toggleUcetniOff()">Přestat být účetní</button>
                </div>
                @if ($toggleDisabledReason)
                    <p style="font-size: 0.85rem; color: #e67e22; margin-bottom: 0.75rem;">{{ $toggleDisabledReason }}</p>
                @endif

                <div style="margin-bottom: 1rem; padding: 0.75rem 1rem; background: #f8f9fa; border-radius: 6px;">
                    <label for="klient_ico" style="font-weight: 600; font-size: 0.9rem; display: block; margin-bottom: 0.4rem;">Přidat klienta</label>
                    <input type="text" id="klient_ico" maxlength="8" placeholder="IČO klienta (8 číslic)" style="padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px; font-size: 0.95rem; max-width: 250px; width: 100%;">
                    <div id="lookupResult" style="display: none;"></div>
                </div>

                @if ($klientVazby->isNotEmpty())
                    <table class="client-table">
                        <thead>
                            <tr>
                                <th>IČO</th>
                                <th>Název</th>
                                <th>Stav</th>
                                <th>Oprávnění</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($klientVazby as $kv)
                                <tr>
                                    <td>{{ $kv->klient_ico }}</td>
                                    <td>{{ $kv->klientFirma?->nazev ?? '—' }}</td>
                                    <td>
                                        @if ($kv->stav === 'schvaleno')
                                            <span class="badge badge-success">Schváleno</span>
                                        @elseif ($kv->stav === 'ceka_na_firmu')
                                            <span class="badge badge-warning">Čeká na schválení</span>
                                        @elseif ($kv->stav === 'zamitnuto')
                                            <span class="badge badge-danger">Zamítnuto</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($kv->stav === 'schvaleno')
                                            <div class="perm-group">
                                                <label class="perm-item"><input type="checkbox" {{ $kv->perm_vkladat ? 'checked' : '' }} disabled> Vkládat</label>
                                                <label class="perm-item"><input type="checkbox" {{ $kv->perm_upravovat ? 'checked' : '' }} disabled> Upravovat</label>
                                                <label class="perm-item"><input type="checkbox" {{ $kv->perm_mazat ? 'checked' : '' }} disabled> Mazat</label>
                                            </div>
                                        @endif
                                    </td>
                                    <td>
                                        <form method="POST" action="{{ route('klienti.destroy', $kv->klient_ico) }}" onsubmit="return confirm('Opravdu odebrat klienta?');" style="margin: 0;">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn-sm btn-sm-danger">Odebrat</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <p style="color: #888; font-size: 0.9rem;">Zatím nemáte žádné klienty.</p>
                @endif
            @endif

            {{-- Stav B: Napojení na účetní firmu (příchozí vazby) --}}
            @if ($vazby->isNotEmpty())
                @if ($jeUcetni)
                    <div style="border-top: 1px solid #eee; margin-top: 1rem; padding-top: 1rem;"></div>
                @endif
                <h4 style="font-size: 0.95rem; margin-bottom: 0.5rem;">Napojení na účetní firmu</h4>
                <table class="client-table">
                    <thead>
                        <tr>
                            <th>Účetní firma</th>
                            <th>IČO</th>
                            <th>Stav</th>
                            <th>Oprávnění</th>
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
                                        <span class="badge badge-warning">Čeká na schválení</span>
                                    @elseif ($vazba->stav === 'zamitnuto')
                                        <span class="badge badge-danger">Zamítnuto</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($vazba->stav === 'schvaleno')
                                        <div class="perm-group" data-vazba-id="{{ $vazba->id }}">
                                            <label class="perm-item" title="Účetní firma může nahrávat nové doklady">
                                                <input type="checkbox" data-perm="perm_vkladat" {{ $vazba->perm_vkladat ? 'checked' : '' }} onchange="savePerm(this)"> Vkládat doklady
                                            </label>
                                            <label class="perm-item" title="Účetní firma může měnit údaje dokladů">
                                                <input type="checkbox" data-perm="perm_upravovat" {{ $vazba->perm_upravovat ? 'checked' : '' }} onchange="savePerm(this)"> Upravovat doklady
                                            </label>
                                            <label class="perm-item" title="Účetní firma může odstraňovat doklady">
                                                <input type="checkbox" data-perm="perm_mazat" {{ $vazba->perm_mazat ? 'checked' : '' }} onchange="savePerm(this)"> Mazat doklady
                                            </label>
                                            <span class="perm-save-ok" id="permStatus{{ $vazba->id }}">Uloženo</span>
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    @if ($vazba->stav === 'ceka_na_firmu')
                                        <div style="display: flex; gap: 0.4rem;">
                                            <form method="POST" action="{{ route('vazby.approve', $vazba->id) }}" style="margin:0;">
                                                @csrf
                                                <button type="submit" class="btn-sm btn-sm-success">Schválit</button>
                                            </form>
                                            <form method="POST" action="{{ route('vazby.reject', $vazba->id) }}" style="margin:0;">
                                                @csrf
                                                <button type="submit" class="btn-sm btn-sm-danger">Zamítnout</button>
                                            </form>
                                        </div>
                                    @elseif ($vazba->stav === 'schvaleno')
                                        <form method="POST" action="{{ route('vazby.disconnect', $vazba->id) }}" onsubmit="return confirm('Opravdu odpojit účetní firmu {{ addslashes($vazba->ucetniFirma?->nazev) }}?');" style="margin:0;">
                                            @csrf
                                            <button type="submit" class="btn-sm btn-sm-outline">Odpojit</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            {{-- Stav C: Ani jedno → toggle pro zapnutí účetního režimu --}}
            @if (!$jeUcetni && $vazby->isEmpty())
                <div style="display: flex; align-items: center; gap: 1rem; margin-top: 0.5rem;">
                    <label class="toggle-switch">
                        <input type="checkbox" id="toggleUcetni" {{ $toggleDisabledReason ? 'disabled' : '' }}>
                        <span class="toggle-slider"></span>
                    </label>
                    <span style="font-weight: 600;">Jsem účetní firma</span>
                </div>
                @if ($toggleDisabledReason)
                    <p style="font-size: 0.85rem; color: #e67e22; margin-top: 0.5rem;">{{ $toggleDisabledReason }}</p>
                @endif
                <p style="font-size: 0.85rem; color: #888; margin-top: 0.5rem;">
                    Zapnutím získáte možnost spravovat firmy, kterým vedete účetnictví.
                </p>
            @endif

        </div>
    </div>
    @endif

    {{-- Email pro doklady --}}
    @if ($firma)
    <div class="section">
        <div class="kat-section-header" onclick="toggleSection('emailDoklady')">
            <span class="kat-arrow" id="emailDokladyArrow">&#9654;</span>
            <h3>📧 Email pro zasílání dokladů</h3>
        </div>
        <p class="kat-desc">Příjem dokladů emailem — systémová adresa nebo vlastní IMAP schránka</p>

        <div class="kat-body" id="emailDokladyBody">

        {{-- Systémový email --}}
        <div style="border: 1px solid #e0e0e0; border-radius: 8px; padding: 1rem; margin-bottom: 1rem;">
            <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 0.5rem;">
                <label class="toggle-switch">
                    <input type="checkbox" id="toggleSystemEmail" {{ $firma->email_system_aktivni ? 'checked' : '' }}>
                    <span class="toggle-slider"></span>
                </label>
                <span style="font-weight: 600;">Systémový email</span>
            </div>
            <div style="background: #f0f7ff; border: 1px solid #bee3f8; border-radius: 6px; padding: 0.6rem 1rem; font-size: 1rem; font-weight: 600; color: #2b6cb0;">
                {{ $firma->ico }}@tuptudu.cz
            </div>
            <p style="font-size: 0.8rem; color: #888; margin-top: 0.4rem;">
                Doklady odeslané na tuto adresu budou automaticky zpracovány a přiřazeny k vaší firmě.
            </p>
        </div>

        {{-- Vlastní email (IMAP) --}}
        <div style="border: 1px solid #e0e0e0; border-radius: 8px; padding: 1rem;">
            <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 0.75rem;">
                <label class="toggle-switch">
                    <input type="checkbox" id="toggleVlastniEmail" {{ $firma->email_vlastni_aktivni ? 'checked' : '' }}>
                    <span class="toggle-slider"></span>
                </label>
                <span style="font-weight: 600;">Vlastní email (IMAP)</span>
            </div>

            <div id="vlastniEmailForm" style="{{ $firma->email_vlastni_aktivni ? '' : 'display:none;' }}">
                <div class="form-group">
                    <label for="vlastniEmail">Email adresa *</label>
                    <input type="email" id="vlastniEmail" class="usr-input" value="{{ $firma->email_vlastni ?? '' }}" placeholder="faktury@mojefirma.cz">
                </div>
                <div class="form-group">
                    <label for="vlastniHost">IMAP server *</label>
                    <input type="text" id="vlastniHost" class="usr-input" value="{{ $firma->email_vlastni_host ?? '' }}" placeholder="imap.seznam.cz">
                </div>
                <div class="form-group">
                    <label for="vlastniHeslo">Heslo *</label>
                    <input type="password" id="vlastniHeslo" class="usr-input" value="{{ $firma->email_vlastni_heslo ?? '' }}">
                </div>

                <div id="vlastniAdvanced" style="display: none;">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="vlastniPort">Port</label>
                            <input type="number" id="vlastniPort" class="usr-input" value="{{ $firma->email_vlastni_port ?? 993 }}" min="1" max="65535">
                        </div>
                        <div class="form-group">
                            <label for="vlastniSifrovani">Šifrování</label>
                            <select id="vlastniSifrovani" class="usr-select" style="border: 1px solid #ddd; padding: 0.5rem;">
                                <option value="ssl" {{ ($firma->email_vlastni_sifrovani ?? 'ssl') === 'ssl' ? 'selected' : '' }}>SSL</option>
                                <option value="tls" {{ ($firma->email_vlastni_sifrovani ?? '') === 'tls' ? 'selected' : '' }}>TLS</option>
                                <option value="none" {{ ($firma->email_vlastni_sifrovani ?? '') === 'none' ? 'selected' : '' }}>Žádné</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="vlastniUzivatel">Uživatelské jméno</label>
                        <input type="text" id="vlastniUzivatel" class="usr-input" value="{{ $firma->email_vlastni_uzivatel ?? '' }}" placeholder="stejné jako email">
                    </div>
                </div>
                <p style="font-size: 0.8rem; color: #3498db; cursor: pointer; margin-bottom: 0.75rem;" onclick="document.getElementById('vlastniAdvanced').style.display = document.getElementById('vlastniAdvanced').style.display === 'none' ? 'block' : 'none';">
                    Pokročilé nastavení (port, šifrování, uživatel)
                </p>

                <div style="display: flex; gap: 0.5rem; align-items: center;">
                    <button type="button" class="btn-save" style="background: #3498db;" onclick="testVlastniEmail()">Otestovat připojení</button>
                    <button type="button" class="btn-save" onclick="ulozitVlastniEmail()">Uložit</button>
                </div>
                <div id="vlastniEmailStatus" style="font-size: 0.85rem; margin-top: 0.5rem; display: none;"></div>
            </div>
        </div>
        </div>
    </div>
    @endif

    {{-- Google Drive --}}
    @if ($firma)
    <div class="section">
        <div class="kat-section-header" onclick="toggleSection('gdrive')">
            <span class="kat-arrow" id="gdriveArrow">&#9654;</span>
            <h3>📁 Google Drive</h3>
        </div>
        <p class="kat-desc">Automatické ukládání kopií dokladů na Google Drive</p>

        <div class="kat-body" id="gdriveBody">
            @if ($firma->google_drive_aktivni)
                <div style="background: #d4edda; border: 1px solid #c3e6cb; border-radius: 8px; padding: 1rem; margin-bottom: 1rem;">
                    <div style="font-weight: 600; color: #155724; margin-bottom: 0.3rem;">✅ Google Drive je připojen</div>
                    <div style="font-size: 0.85rem; color: #155724;">
                        Kořenová složka: <strong>office.tuptudu.cz/</strong>
                    </div>
                </div>

                {{-- Šablona pojmenování --}}
                <div style="margin: 1.2rem 0;">
                    <h4 style="font-size: 0.95rem; margin-bottom: 0.5rem;">Pojmenování souborů a složek</h4>
                    <p style="font-size: 0.8rem; color: #888; margin-bottom: 0.75rem;">
                        Pomocí tokenů v <code style="background:#f0f0f0; padding:0.1rem 0.3rem; border-radius:3px;">{}</code> sestavíte šablonu pro název souboru a strukturu složek na Google Drive.
                        Lomítko <code style="background:#f0f0f0; padding:0.1rem 0.3rem; border-radius:3px;">/</code> vytvoří podsložku. IČO vaší firmy se automaticky vloží jako první složka a <code style="background:#f0f0f0; padding:0.1rem 0.3rem; border-radius:3px;">{id}</code> jako povinná součást názvu souboru.
                    </p>

                    <details style="margin-bottom: 0.75rem;">
                        <summary style="cursor: pointer; font-size: 0.85rem; color: #3498db; font-weight: 600;">Dostupné tokeny</summary>
                        <table style="width: 100%; font-size: 0.8rem; border-collapse: collapse; margin-top: 0.5rem;">
                            <tr style="background: #f8f8f8;"><td style="padding: 0.3rem 0.5rem; border: 1px solid #eee;"><code>{id}</code></td><td style="padding: 0.3rem 0.5rem; border: 1px solid #eee;">ID dokladu <strong>(povinné)</strong></td><td style="padding: 0.3rem 0.5rem; border: 1px solid #eee;">12345</td></tr>
                            <tr><td style="padding: 0.3rem 0.5rem; border: 1px solid #eee;"><code>{nahrano:FORMAT}</code></td><td style="padding: 0.3rem 0.5rem; border: 1px solid #eee;">Datum nahrání</td><td style="padding: 0.3rem 0.5rem; border: 1px solid #eee;">{nahrano:YYYY} → 2026</td></tr>
                            <tr style="background: #f8f8f8;"><td style="padding: 0.3rem 0.5rem; border: 1px solid #eee;"><code>{duzp:FORMAT}</code></td><td style="padding: 0.3rem 0.5rem; border: 1px solid #eee;">DÚZP</td><td style="padding: 0.3rem 0.5rem; border: 1px solid #eee;">{duzp:YY-MM-DD} → 26-01-12</td></tr>
                            <tr><td style="padding: 0.3rem 0.5rem; border: 1px solid #eee;"><code>{vystaveni:FORMAT}</code></td><td style="padding: 0.3rem 0.5rem; border: 1px solid #eee;">Datum vystavení</td><td style="padding: 0.3rem 0.5rem; border: 1px solid #eee;">{vystaveni:DD.MM.YYYY} → 12.01.2026</td></tr>
                            <tr style="background: #f8f8f8;"><td style="padding: 0.3rem 0.5rem; border: 1px solid #eee;"><code>{dodavatel:N}</code></td><td style="padding: 0.3rem 0.5rem; border: 1px solid #eee;">Dodavatel (max N znaků)</td><td style="padding: 0.3rem 0.5rem; border: 1px solid #eee;">{dodavatel:15} → dodavatel s.r.o.</td></tr>
                            <tr><td style="padding: 0.3rem 0.5rem; border: 1px solid #eee;"><code>{dodavatel_ico}</code></td><td style="padding: 0.3rem 0.5rem; border: 1px solid #eee;">IČO dodavatele</td><td style="padding: 0.3rem 0.5rem; border: 1px solid #eee;">12345678</td></tr>
                            <tr style="background: #f8f8f8;"><td style="padding: 0.3rem 0.5rem; border: 1px solid #eee;"><code>{castka}</code></td><td style="padding: 0.3rem 0.5rem; border: 1px solid #eee;">Celková částka</td><td style="padding: 0.3rem 0.5rem; border: 1px solid #eee;">1250.00</td></tr>
                            <tr><td style="padding: 0.3rem 0.5rem; border: 1px solid #eee;"><code>{vs}</code></td><td style="padding: 0.3rem 0.5rem; border: 1px solid #eee;">Variabilní symbol</td><td style="padding: 0.3rem 0.5rem; border: 1px solid #eee;">2024001</td></tr>
                            <tr style="background: #f8f8f8;"><td style="padding: 0.3rem 0.5rem; border: 1px solid #eee;"><code>{typ}</code></td><td style="padding: 0.3rem 0.5rem; border: 1px solid #eee;">Typ dokladu</td><td style="padding: 0.3rem 0.5rem; border: 1px solid #eee;">faktura</td></tr>
                            <tr><td style="padding: 0.3rem 0.5rem; border: 1px solid #eee;"><code>{cislo}</code></td><td style="padding: 0.3rem 0.5rem; border: 1px solid #eee;">Číslo dokladu</td><td style="padding: 0.3rem 0.5rem; border: 1px solid #eee;">FV-2024-001</td></tr>
                        </table>
                        <p style="font-size: 0.78rem; color: #999; margin-top: 0.4rem;">
                            Formát dat: <code>YYYY</code>=rok (2026), <code>YY</code>=rok (26), <code>MM</code>=měsíc (01), <code>DD</code>=den (12).
                            Pokud hodnota chybí, zobrazí se "nezname". IČO vaší firmy ({{ $firma->ico }}) se automaticky přidá jako první složka.
                        </p>
                    </details>

                    <form method="POST" action="{{ route('firma.ulozit') }}" onsubmit="ensureIdInTemplate()">
                        @csrf
                        <label style="font-size: 0.85rem; font-weight: 600; display: block; margin-bottom: 0.3rem;">Šablona:</label>
                        <input type="text" name="google_drive_sablona" id="gdriveSablona"
                            value="{{ old('google_drive_sablona', $firma->google_drive_sablona ?? '{nahrano:YYYY}/{duzp:YY-MM-DD}_{dodavatel:15}_{id}') }}"
                            style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 6px; font-family: monospace; font-size: 0.9rem;"
                            oninput="updateGdrivePreview()"
                            placeholder="{nahrano:YYYY}/{duzp:YY-MM-DD}_{dodavatel:15}_{id}">
                        @error('google_drive_sablona')
                            <div style="color: #e74c3c; font-size: 0.8rem; margin-top: 0.3rem;">{{ $message }}</div>
                        @enderror

                        <div style="font-size: 0.82rem; color: #666; margin-top: 0.4rem;">
                            Náhled: <strong>office.tuptudu.cz/{{ $firma->ico }}/<span id="gdrivePreview"></span></strong>
                        </div>

                        <div style="display: flex; gap: 0.5rem; margin-top: 0.6rem;">
                            <button type="submit" class="btn-save">Uložit šablonu</button>
                            <button type="button" class="btn-sm btn-sm-outline" onclick="resetGdriveSablona()">Obnovit výchozí</button>
                        </div>
                    </form>
                </div>

                {{-- Info box --}}
                <div style="background: #f0f7ff; border: 1px solid #d0e3f7; border-radius: 8px; padding: 0.8rem; margin: 1rem 0; font-size: 0.8rem; color: #555;">
                    <strong>Jak to funguje:</strong>
                    <ul style="margin: 0.3rem 0 0 1.2rem; padding: 0;">
                        <li>Nové doklady se automaticky kopírují na váš Google Drive (každých 5 minut).</li>
                        <li>Pokud má vaše firma napojeného účetního, kopie se uloží i na jeho Drive.</li>
                        <li>Každá firma má vlastní šablonu — vaše nastavení neovlivní Drive účetního a naopak.</li>
                        <li>Změna šablony se projeví pouze u nově nahrávaných dokladů. Soubory, které už na Disku jsou, zůstanou beze změny.</li>
                        <li>Pokud na dokladu upravíte údaje (např. DÚZP), již nahraný soubor na Disku se nepřejmenuje — slouží jako archivní kopie v době nahrání.</li>
                    </ul>
                </div>

                <form method="POST" action="{{ route('google.disconnect') }}" style="margin-top: 0.5rem;">
                    @csrf
                    <button type="submit" class="btn-sm btn-sm-outline" onclick="return confirm('Opravdu chcete odpojit Google Drive?')">Odpojit Google Drive</button>
                </form>
            @else
                <p style="font-size: 0.9rem; color: #555; margin-bottom: 0.75rem;">
                    Propojte svůj Google účet pro automatické ukládání kopií dokladů na Google Drive.
                </p>
                <p style="font-size: 0.8rem; color: #888; margin-bottom: 1rem;">
                    Aplikace bude mít přístup pouze k souborům, které sama vytvoří. Vaše ostatní soubory na Disku zůstanou nedotčené.
                </p>
                <a href="{{ route('google.redirect') }}" style="display: inline-block; background: #4285f4; color: white; padding: 0.6rem 1.2rem; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 0.9rem;">
                    Připojit Google Drive
                </a>
            @endif
        </div>
    </div>
    @endif

    {{-- Kategorie nákladů --}}
    @if ($firma)
    <div class="section">
        <div class="kat-section-header" onclick="toggleKategorie()">
            <span class="kat-arrow" id="katArrow">&#9654;</span>
            <h3>🏷️ Kategorie nákladů</h3>
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
        <div class="kat-section-header" onclick="toggleSection('uzivatele')">
            <span class="kat-arrow" id="uzivateleArrow">&#9654;</span>
            <h3>👥 Uživatelé firmy</h3>
        </div>
        <p class="kat-desc">Správa uživatelů a jejich oprávnění k firmě</p>

        <div class="kat-body" id="uzivateleBody">

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
    </div>
    @endif
</div>

<script>
(function() {
    const csrfToken = '{{ csrf_token() }}';

    // ===== Generic section toggle =====
    window.toggleSection = function(name) {
        var body = document.getElementById(name + 'Body');
        var arrow = document.getElementById(name + 'Arrow');
        if (body) body.classList.toggle('open');
        if (arrow) arrow.classList.toggle('open');
    };

    // ===== Účetní napojení =====
    window.toggleUcetniNapojeni = function() {
        toggleSection('ucetni');
    };

    // Toggle účetní ON (stav C → zapnutí)
    var toggle = document.getElementById('toggleUcetni');
    if (toggle && !toggle.disabled) {
        toggle.addEventListener('change', function() {
            var jeUcetni = this.checked;
            fetch('{{ route("firma.toggleUcetni") }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: JSON.stringify({ je_ucetni: jeUcetni ? 1 : 0 })
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.ok) { window.location.reload(); }
                else { alert(data.error || 'Chyba.'); toggle.checked = !jeUcetni; }
            })
            .catch(function() { alert('Chyba připojení.'); toggle.checked = !jeUcetni; });
        });
    }

    // Toggle účetní OFF (stav A → vypnutí)
    window.toggleUcetniOff = function() {
        if (!confirm('Opravdu chcete přestat být účetní firma?')) return;
        fetch('{{ route("firma.toggleUcetni") }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            body: JSON.stringify({ je_ucetni: 0 })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.ok) { window.location.reload(); }
            else { alert(data.error || 'Chyba.'); }
        })
        .catch(function() { alert('Chyba připojení.'); });
    };

    // ===== Klienti — IČO lookup =====
    var klientInput = document.getElementById('klient_ico');
    var lookupResult = document.getElementById('lookupResult');
    var lookupTimer = null;
    var currentIco = '';

    if (klientInput) {
        klientInput.addEventListener('input', function() {
            clearTimeout(lookupTimer);
            var ico = this.value.trim().replace(/\D/g, '');
            this.value = ico;
            lookupResult.style.display = 'none';
            lookupResult.innerHTML = '';
            currentIco = '';

            if (ico.length < 8) return;
            if (ico.length > 8) { this.value = ico.substring(0, 8); ico = this.value; }

            lookupResult.style.display = 'block';
            lookupResult.className = 'lookup-result info';
            lookupResult.textContent = 'Hledám v ARES...';

            lookupTimer = setTimeout(function() {
                fetch('{{ route("klienti.lookup") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                    body: JSON.stringify({ ico: ico })
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    lookupResult.style.display = 'block';
                    if (data.error) {
                        lookupResult.className = 'lookup-result error';
                        lookupResult.textContent = data.error;
                        return;
                    }
                    currentIco = ico;
                    if (data.v_systemu) {
                        var html = '<div style="margin-bottom: 0.5rem;"><strong>' + escHtml(data.nazev) + '</strong></div>';
                        html += '<div style="margin-bottom: 0.5rem;">Firma je již v systému registrována.</div>';
                        if (data.superadmins && data.superadmins.length > 1) {
                            html += '<div style="margin-bottom: 0.5rem;">Vyberte příjemce žádosti:</div>';
                            for (var i = 0; i < data.superadmins.length; i++) {
                                var sa = data.superadmins[i];
                                html += '<label style="display: block; margin-bottom: 0.3rem; cursor: pointer;">';
                                html += '<input type="radio" name="superadmin_id" value="' + sa.id + '"' + (i === 0 ? ' checked' : '') + ' style="margin-right: 0.4rem;">';
                                html += escHtml(sa.masked_email) + '</label>';
                            }
                        } else if (data.superadmins && data.superadmins.length === 1) {
                            html += '<div style="margin-bottom: 0.5rem;">Žádost bude odeslána na <strong>' + escHtml(data.superadmins[0].masked_email) + '</strong></div>';
                        }
                        if (data.cooldown) {
                            html += '<div style="color: #856404; margin-top: 0.5rem;">Žádost byla odeslána nedávno. Další za 24 hodin.</div>';
                        } else {
                            html += '<button type="button" class="btn-sm btn-sm-success" onclick="poslZadost()" style="margin-top: 0.3rem;">Odeslat žádost</button>';
                        }
                        html += '<span id="zadostStatus" style="margin-left: 0.75rem; font-size: 0.85rem;"></span>';
                        lookupResult.className = 'lookup-result info';
                        lookupResult.innerHTML = html;
                    } else {
                        var html = '<div style="margin-bottom: 0.5rem;"><strong>' + escHtml(data.nazev) + '</strong></div>';
                        html += '<div style="margin-bottom: 0.5rem;">Zadejte email oprávněné osoby:</div>';
                        html += '<div style="display: flex; gap: 0.5rem; align-items: center;">';
                        html += '<input type="email" id="zadostEmail" placeholder="email@firma.cz" style="padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px; font-size: 0.9rem; flex: 1; max-width: 300px;">';
                        if (data.cooldown) {
                            html += '</div>';
                            html += '<div style="color: #856404; margin-top: 0.5rem;">Žádost byla odeslána nedávno. Další za 24 hodin.</div>';
                        } else {
                            html += '<button type="button" class="btn-sm btn-sm-success" onclick="poslZadost()">Odeslat žádost</button>';
                            html += '</div>';
                        }
                        html += '<span id="zadostStatus" style="display: block; margin-top: 0.5rem; font-size: 0.85rem;"></span>';
                        lookupResult.className = 'lookup-result warning';
                        lookupResult.innerHTML = html;
                    }
                })
                .catch(function() {
                    lookupResult.className = 'lookup-result error';
                    lookupResult.textContent = 'Chyba při komunikaci se serverem.';
                });
            }, 400);
        });
    }

    window.poslZadost = function() {
        if (!currentIco) return;
        var emailInput = document.getElementById('zadostEmail');
        var email = emailInput ? emailInput.value.trim() : null;
        var status = document.getElementById('zadostStatus');
        if (emailInput && !email) { status.textContent = 'Vyplňte email.'; status.style.color = '#e74c3c'; return; }
        var superadminId = null;
        var radios = document.querySelectorAll('input[name="superadmin_id"]');
        for (var i = 0; i < radios.length; i++) { if (radios[i].checked) { superadminId = parseInt(radios[i].value); break; } }
        status.textContent = 'Odesílám...'; status.style.color = '#666';
        var body = { ico: currentIco, email: email };
        if (superadminId) body.superadmin_id = superadminId;
        fetch('{{ route("klienti.poslZadost") }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            body: JSON.stringify(body)
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.ok) { status.textContent = data.message; status.style.color = '#27ae60'; setTimeout(function() { window.location.reload(); }, 2000); }
            else { status.textContent = data.error || 'Chyba'; status.style.color = '#e74c3c'; }
        })
        .catch(function() { status.textContent = 'Chyba připojení.'; status.style.color = '#e74c3c'; });
    };

    function escHtml(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

    // ===== Kategorie =====
    let saveTimer = null;
    const SAVE_DELAY = 800; // ms after last change

    window.toggleKategorie = function() {
        toggleSection('kat');
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
        setTimeout(() => el.classList.remove('visible'), 5000);
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

    // Enter v inputech nového uživatele → addUser() místo odeslání formuláře
    ['newUserJmeno', 'newUserEmail'].forEach(function(id) {
        const el = document.getElementById(id);
        if (el) el.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') { e.preventDefault(); addUser(); }
        });
    });

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
    // ===== Oprávnění účetní vazby =====
    window.savePerm = function(checkbox) {
        var group = checkbox.closest('.perm-group');
        var vazbaId = group.dataset.vazbaId;
        var perms = {
            perm_vkladat: group.querySelector('[data-perm="perm_vkladat"]').checked ? 1 : 0,
            perm_upravovat: group.querySelector('[data-perm="perm_upravovat"]').checked ? 1 : 0,
            perm_mazat: group.querySelector('[data-perm="perm_mazat"]').checked ? 1 : 0,
        };
        var status = document.getElementById('permStatus' + vazbaId);

        fetch('/vazby/' + vazbaId + '/opravneni', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            body: JSON.stringify(perms)
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.ok) {
                status.textContent = 'Uloženo';
                status.style.color = '#27ae60';
                status.classList.add('visible');
                setTimeout(function() { status.classList.remove('visible'); }, 2000);
            } else {
                status.textContent = 'Chyba';
                status.style.color = '#e74c3c';
                status.classList.add('visible');
                setTimeout(function() { status.classList.remove('visible'); }, 3000);
            }
        })
        .catch(function() {
            status.textContent = 'Chyba';
            status.style.color = '#e74c3c';
            status.classList.add('visible');
            setTimeout(function() { status.classList.remove('visible'); }, 3000);
        });
    };

    // ===== Email pro doklady =====
    // Systémový email toggle
    const toggleSys = document.getElementById('toggleSystemEmail');
    if (toggleSys) {
        toggleSys.addEventListener('change', function() {
            fetch('{{ route("firma.toggleSystemEmail") }}', {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json'},
                body: JSON.stringify({ aktivni: this.checked ? 1 : 0 })
            })
            .then(r => r.json())
            .then(data => {
                if (!data.ok) { alert(data.error || 'Chyba'); toggleSys.checked = !toggleSys.checked; }
            })
            .catch(() => { alert('Chyba připojení.'); toggleSys.checked = !toggleSys.checked; });
        });
    }

    // Vlastní email toggle → show/hide form
    const toggleVlastni = document.getElementById('toggleVlastniEmail');
    if (toggleVlastni) {
        toggleVlastni.addEventListener('change', function() {
            document.getElementById('vlastniEmailForm').style.display = this.checked ? '' : 'none';
        });
    }

    // Auto port on encryption change
    const sifrovaniSelect = document.getElementById('vlastniSifrovani');
    if (sifrovaniSelect) {
        sifrovaniSelect.addEventListener('change', function() {
            const portInput = document.getElementById('vlastniPort');
            portInput.value = this.value === 'ssl' ? 993 : 143;
        });
    }

    function getVlastniData() {
        return {
            email: document.getElementById('vlastniEmail').value.trim(),
            host: document.getElementById('vlastniHost').value.trim(),
            heslo: document.getElementById('vlastniHeslo').value,
            port: parseInt(document.getElementById('vlastniPort').value) || 993,
            sifrovani: document.getElementById('vlastniSifrovani').value,
            uzivatel: document.getElementById('vlastniUzivatel').value.trim(),
        };
    }

    function showVlastniStatus(text, color) {
        const el = document.getElementById('vlastniEmailStatus');
        el.textContent = text;
        el.style.color = color || '#27ae60';
        el.style.display = 'block';
        setTimeout(() => { el.style.display = 'none'; }, 8000);
    }

    window.testVlastniEmail = function() {
        const d = getVlastniData();
        if (!d.host || !d.heslo) { showVlastniStatus('Vyplňte server a heslo.', '#e74c3c'); return; }

        showVlastniStatus('Testuji připojení...', '#666');

        fetch('{{ route("firma.testEmailVlastni") }}', {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json'},
            body: JSON.stringify({
                host: d.host,
                port: d.port,
                sifrovani: d.sifrovani,
                uzivatel: d.uzivatel || d.email,
                heslo: d.heslo,
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                showVlastniStatus(data.message, '#27ae60');
            } else {
                showVlastniStatus(data.error, '#e74c3c');
                // Show advanced settings on failure
                document.getElementById('vlastniAdvanced').style.display = 'block';
            }
        })
        .catch(() => showVlastniStatus('Chyba připojení.', '#e74c3c'));
    };

    window.ulozitVlastniEmail = function() {
        const d = getVlastniData();
        const aktivni = document.getElementById('toggleVlastniEmail').checked;

        fetch('{{ route("firma.ulozitVlastniEmail") }}', {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json'},
            body: JSON.stringify({
                aktivni: aktivni ? 1 : 0,
                email: d.email,
                host: d.host,
                port: d.port,
                sifrovani: d.sifrovani,
                uzivatel: d.uzivatel,
                heslo: d.heslo,
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.ok) showVlastniStatus('Nastavení uloženo.', '#27ae60');
            else showVlastniStatus(data.error || 'Chyba při ukládání.', '#e74c3c');
        })
        .catch(() => showVlastniStatus('Chyba připojení.', '#e74c3c'));
    };
    // ===== Google Drive šablona preview =====
    var gdriveDefaultTemplate = '{nahrano:YYYY}/{duzp:YY-MM-DD}_{dodavatel:15}_{id}';
    var gdrivePreviewData = {
        id: '12345',
        nahrano: new Date(2026, 1, 25),
        duzp: new Date(2026, 0, 12),
        vystaveni: new Date(2026, 0, 10),
        splatnost: new Date(2026, 1, 10),
        dodavatel: 'dodavatel s.r.o.',
        dodavatel_ico: '12345678',
        ico: '{{ $firma ? $firma->ico : "87700484" }}',
        castka: '1250.00',
        vs: '2024001',
        typ: 'faktura',
        kategorie: 'materiál',
        cislo: 'FV-2024-001'
    };

    function gdriveFormatDate(date, fmt) {
        if (!fmt) return date.getFullYear() + '-' + String(date.getMonth()+1).padStart(2,'0') + '-' + String(date.getDate()).padStart(2,'0');
        return fmt
            .replace('YYYY', String(date.getFullYear()))
            .replace('YY', String(date.getFullYear()).slice(-2))
            .replace('MM', String(date.getMonth()+1).padStart(2,'0'))
            .replace('DD', String(date.getDate()).padStart(2,'0'));
    }

    function gdriveResolveToken(token, format) {
        var datTokens = {nahrano:'nahrano', duzp:'duzp', vystaveni:'vystaveni', splatnost:'splatnost'};
        if (token === 'id') return gdrivePreviewData.id;
        if (datTokens[token]) {
            var d = gdrivePreviewData[token];
            return d ? gdriveFormatDate(d, format) : 'nezname';
        }
        var val = gdrivePreviewData[token];
        if (!val) return 'nezname';
        if (format && /^\d+$/.test(format)) val = val.substring(0, parseInt(format));
        return val;
    }

    window.updateGdrivePreview = function() {
        var input = document.getElementById('gdriveSablona');
        if (!input) return;
        var tpl = input.value;
        var result = tpl.replace(/\{([a-z_]+)(?::([^}]*))?\}/g, function(m, token, fmt) {
            return gdriveResolveToken(token, fmt || null);
        });
        if (tpl.indexOf('{id}') === -1) result += '_' + gdrivePreviewData.id;
        var el = document.getElementById('gdrivePreview');
        if (el) el.textContent = result + '.pdf';
    };

    // Před odesláním formuláře: pokud chybí {id}, přidej ho do inputu
    window.ensureIdInTemplate = function() {
        var input = document.getElementById('gdriveSablona');
        if (!input) return;
        var val = input.value.trim();
        if (val !== '' && val.indexOf('{id}') === -1) {
            input.value = val + '_{id}';
        }
    };

    // Obnovit výchozí šablonu
    window.resetGdriveSablona = function() {
        var input = document.getElementById('gdriveSablona');
        if (input) {
            input.value = gdriveDefaultTemplate;
            updateGdrivePreview();
        }
    };

    // Init preview on load
    updateGdrivePreview();

})();
</script>
@endsection
