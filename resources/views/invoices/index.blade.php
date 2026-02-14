@extends('layouts.app')

@section('title', 'Doklady')

@section('styles')
<style>
    .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
    .page-header h2 { margin: 0; }

    .upload-zone { border: 2px dashed #bdc3c7; border-radius: 8px; padding: 1.2rem; text-align: center; cursor: pointer; transition: all 0.3s; margin-bottom: 1.5rem; }
    .upload-zone:hover, .upload-zone.dragover { border-color: #3498db; background: #ebf5fb; }
    .upload-zone p { color: #7f8c8d; margin: 0; font-size: 0.9rem; }
    .upload-zone .formats { font-size: 0.8rem; color: #95a5a6; margin-top: 0.3rem; }
    .upload-processing { display: none; padding: 1rem; text-align: center; color: #555; background: #eaf2f8; border-radius: 8px; margin-bottom: 1.5rem; }
    .upload-processing .spinner { display: inline-block; width: 18px; height: 18px; border: 2px solid #bdc3c7; border-top-color: #3498db; border-radius: 50%; animation: spin 0.8s linear infinite; margin-right: 0.5rem; vertical-align: middle; }
    @keyframes spin { to { transform: rotate(360deg); } }

    .toast-container { margin-bottom: 1rem; }
    .toast { padding: 0.6rem 1rem; border-radius: 6px; margin-bottom: 0.4rem; font-size: 0.85rem; transition: opacity 0.5s; }
    .toast-ok { background: #d4edda; color: #155724; }
    .toast-error { background: #f8d7da; color: #721c24; }
    .toast-duplicate { background: #fff3cd; color: #856404; }
    .toast-warning { background: #fff3cd; color: #856404; }
    .toast-info { background: #d4edda; color: #155724; }

    .toolbar { display: flex; gap: 0.75rem; align-items: center; margin-bottom: 0.75rem; }
    .search-input { flex: 1; padding: 0.4rem 0.75rem; border: 1px solid #d0d8e0; border-radius: 6px; font-size: 0.85rem; outline: none; }
    .search-input:focus { border-color: #3498db; }

    .col-toggle { font-size: 0.8rem; color: #777; cursor: pointer; user-select: none; }
    .col-toggle:hover { color: #2c3e50; }
    .col-panel { display: none; padding: 0.5rem 0; margin-bottom: 0.75rem; }
    .col-panel.open { display: flex; flex-wrap: wrap; gap: 0.3rem 1rem; }
    .col-panel label { font-size: 0.8rem; color: #555; cursor: pointer; white-space: nowrap; }
    .col-panel input { margin-right: 0.25rem; vertical-align: middle; }

    .doklady-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
    .doklady-table th { text-align: left; padding: 0.5rem 0.6rem; background: #f0f4f8; border-bottom: 2px solid #d0d8e0; border-right: 1px solid #e4e8ec; font-size: 0.75rem; color: #555; font-weight: 600; white-space: nowrap; cursor: default; }
    .doklady-table th:last-child { border-right: none; }
    .doklady-table th[draggable="true"] { cursor: grab; }
    .doklady-table th[draggable="true"]:active { cursor: grabbing; }
    .doklady-table th.drag-over { border-left: 3px solid #3498db; }
    .doklady-table td { padding: 0.45rem 0.6rem; border-bottom: 1px solid #e8ecf0; border-right: 1px solid #f0f0f0; }
    .doklady-table td:last-child { border-right: none; }
    .doklady-table tr:hover > td { background: #f8fafb; }
    .doklady-table a { color: #3498db; text-decoration: none; }
    .doklady-table a:hover { text-decoration: underline; }
    .sort-link { color: #555; text-decoration: none; }
    .sort-link:hover { color: #2c3e50; text-decoration: none; }
    .sort-arrow { font-size: 0.65rem; margin-left: 0.15rem; }
    .col-help { color: #bdc3c7; font-size: 0.65rem; margin-left: 0.2rem; cursor: help; vertical-align: super; }

    .stav-dokonceno { color: #27ae60; }
    .stav-chyba { color: #e74c3c; font-weight: 600; }
    .stav-zpracovava { color: #f39c12; font-weight: 600; }
    .stav-nekvalitni { color: #e67e22; font-weight: 600; }
    .badge-kvalita { display: inline-block; padding: 0.1rem 0.4rem; border-radius: 4px; font-size: 0.65rem; font-weight: 600; margin-left: 0.2rem; vertical-align: middle; }
    .kvalita-nizka { background: #fff3cd; color: #856404; }
    .kvalita-necitelna { background: #f8d7da; color: #721c24; }
    .amount { text-align: right; font-weight: 600; }
    .empty-state { text-align: center; padding: 2rem; color: #999; }
    .warning-msg { background: #fff3cd; color: #856404; padding: 0.75rem 1rem; border-radius: 6px; margin-bottom: 1rem; }
    .badge-dup { display: inline-block; padding: 0.1rem 0.4rem; border-radius: 4px; background: #fff3cd; color: #856404; font-size: 0.65rem; font-weight: 600; margin-left: 0.2rem; vertical-align: middle; }
    .btn-del-sm { background: none; border: none; color: #bdc3c7; cursor: pointer; font-size: 0.85rem; padding: 0.2rem 0.4rem; line-height: 1; }
    .btn-del-sm:hover { color: #e74c3c; }
    .btn-preview { color: #95a5a6; text-decoration: none; font-size: 0.85rem; }
    .btn-preview:hover { color: #3498db; text-decoration: none; }

    .expand-btn { background: none; border: none; cursor: pointer; color: #95a5a6; font-size: 0.7rem; padding: 0; line-height: 1; }
    .expand-btn:hover { color: #555; }
    .detail-row td { padding: 0; background: #fafbfc; }
    .detail-inner { padding: 0.75rem 1rem 0.75rem 2rem; }
    .detail-inner table { width: 100%; border-collapse: collapse; font-size: 0.8rem; }
    .detail-inner th { text-align: left; padding: 0.3rem 0.6rem; color: #777; font-weight: 600; width: 140px; background: transparent; border-bottom: 1px solid #eee; border-right: none; }
    .detail-inner td { padding: 0.3rem 0.6rem; border-bottom: 1px solid #eee; border-right: none; }

    .editable { position: relative; cursor: default; }
    .edit-icon { color: #ccc; font-size: 0.65rem; margin-left: 0.3rem; visibility: hidden; cursor: pointer; }
    .editable:hover .edit-icon { visibility: visible; color: #95a5a6; }
    .edit-input { width: 100%; padding: 0.2rem 0.3rem; border: 1px solid #3498db; border-radius: 3px; font-size: 0.85rem; outline: none; }

    .month-downloads { margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid #e0e0e0; }
    .month-downloads h3 { font-size: 0.9rem; color: #555; margin-bottom: 0.75rem; }
    .month-list { display: flex; flex-wrap: wrap; gap: 0.5rem; }
    .month-link { display: inline-block; padding: 0.35rem 0.75rem; background: #eaf2f8; border-radius: 6px; color: #2c3e50; text-decoration: none; font-size: 0.85rem; }
    .month-link:hover { background: #d4e6f1; }
    .table-count { font-size: 0.8rem; color: #95a5a6; margin-top: 0.5rem; }

    .preview-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 1000; justify-content: center; align-items: center; }
    .preview-overlay.active { display: flex; }
    .preview-container { position: relative; width: 90vw; height: 90vh; max-width: 1000px; background: white; border-radius: 8px; overflow: hidden; display: flex; flex-direction: column; }
    .preview-container #previewContent { flex: 1; min-height: 0; display: flex; align-items: center; justify-content: center; overflow: auto; }
    .preview-container iframe { width: 100%; height: 100%; border: none; }
    .preview-container img { max-width: 100%; max-height: 100%; object-fit: contain; }
    .preview-close { position: absolute; top: 8px; right: 12px; background: rgba(0,0,0,0.5); color: white; border: none; font-size: 1.5rem; width: 36px; height: 36px; border-radius: 50%; cursor: pointer; z-index: 1001; line-height: 1; }
    .preview-close:hover { background: rgba(0,0,0,0.8); }
</style>
@endsection

@section('content')
<div class="card">
    <div class="page-header">
        <h2>Doklady</h2>
    </div>

    <div class="toast-container" id="toastContainer">
        @if (session('flash'))
            <div class="toast toast-info" data-auto-hide>{{ session('flash') }}</div>
        @endif
    </div>

    @if (!$firma)
        <div class="warning-msg">Nejdříve vyplňte <a href="{{ route('firma.nastaveni') }}">nastavení firmy</a>.</div>
    @else
        <div class="upload-zone" id="dropZone">
            <p>Přetáhněte soubory sem nebo klikněte pro výběr</p>
            <p class="formats">PDF, JPG, PNG (max 10 MB)</p>
        </div>
        <input type="file" id="fileInput" accept=".pdf,.jpg,.jpeg,.png" multiple style="display: none;">
        <div class="upload-processing" id="uploadProcessing">
            <span class="spinner"></span> <span id="uploadStatus">Zpracovávám doklady...</span>
        </div>
    @endif

    @if ($doklady->isEmpty() && empty($q))
        <div class="empty-state"><p>Zatím žádné doklady.</p></div>
    @else
        <div class="toolbar">
            <form method="GET" action="{{ route('doklady.index') }}" style="flex:1; display:flex;">
                <input type="hidden" name="sort" value="{{ $sort }}">
                <input type="hidden" name="dir" value="{{ $dir }}">
                <input type="text" name="q" value="{{ $q }}" class="search-input" placeholder="Hledat v dokladech...">
            </form>
        </div>

        <div class="col-toggle" id="colToggle" onclick="document.getElementById('colPanel').classList.toggle('open'); this.querySelector('span').textContent = document.getElementById('colPanel').classList.contains('open') ? '\u25BC' : '\u25B6';"><span>&#9654;</span> Přidat/ubrat sloupce</div>
        <div class="col-panel" id="colPanel"></div>

        @if ($doklady->isEmpty())
            <div class="empty-state"><p>Žádné doklady neodpovídají hledání.</p></div>
        @else

        @php
            $dokladyJson = $doklady->map(function($d) {
                return [
                    'id' => $d->id,
                    'created_at' => $d->created_at->format('d.m.y'),
                    'created_at_time' => $d->created_at->format('H:i'),
                    'created_at_iso' => $d->created_at->toISOString(),
                    'datum_prijeti' => $d->datum_prijeti ? $d->datum_prijeti->format('d.m.y') : null,
                    'datum_prijeti_raw' => $d->datum_prijeti ? $d->datum_prijeti->format('Y-m-d') : null,
                    'duzp' => $d->duzp ? $d->duzp->format('d.m.y') : null,
                    'duzp_raw' => $d->duzp ? $d->duzp->format('Y-m-d') : null,
                    'datum_vystaveni' => $d->datum_vystaveni ? $d->datum_vystaveni->format('d.m.y') : null,
                    'datum_vystaveni_raw' => $d->datum_vystaveni ? $d->datum_vystaveni->format('Y-m-d') : null,
                    'datum_splatnosti' => $d->datum_splatnosti ? $d->datum_splatnosti->format('d.m.y') : null,
                    'datum_splatnosti_raw' => $d->datum_splatnosti ? $d->datum_splatnosti->format('Y-m-d') : null,
                    'cislo_dokladu' => $d->cislo_dokladu,
                    'nazev_souboru' => $d->nazev_souboru,
                    'dodavatel_nazev' => $d->dodavatel_nazev,
                    'dodavatel_ico' => $d->dodavatel_ico,
                    'castka_celkem' => $d->castka_celkem,
                    'mena' => $d->mena,
                    'castka_dph' => $d->castka_dph,
                    'kategorie' => $d->kategorie,
                    'stav' => $d->stav,
                    'typ_dokladu' => $d->typ_dokladu,
                    'kvalita' => $d->kvalita,
                    'kvalita_poznamka' => $d->kvalita_poznamka,
                    'zdroj' => $d->zdroj,
                    'cesta_souboru' => $d->cesta_souboru ? true : false,
                    'duplicita_id' => $d->duplicita_id,
                    'show_url' => route('doklady.show', $d),
                    'update_url' => route('doklady.update', $d),
                    'destroy_url' => route('doklady.destroy', $d),
                    'preview_url' => $d->cesta_souboru ? route('doklady.preview', $d) : null,
                    'preview_ext' => strtolower(pathinfo($d->nazev_souboru, PATHINFO_EXTENSION)),
                    'adresni' => $d->adresni,
                    'overeno_adresat' => $d->overeno_adresat,
                    'chybova_zprava' => $d->chybova_zprava,
                    'raw_ai_odpoved' => $d->raw_ai_odpoved,
                    'created_at_full' => $d->created_at->format('d.m.Y H:i'),
                ];
            })->values();
        @endphp
        <script>
            var dokladyData = {!! json_encode($dokladyJson, JSON_UNESCAPED_UNICODE) !!};
            var csrfToken = '{{ csrf_token() }}';
            var sortCol = '{{ $sort }}';
            var sortDir = '{{ $dir }}';
            var searchQ = '{{ $q }}';
        </script>

        <div id="tableContainer"></div>

        @php
            $mesice = $doklady
                ->filter(fn($d) => $d->datum_vystaveni)
                ->map(fn($d) => $d->datum_vystaveni->format('Y-m'))
                ->unique()
                ->sort()
                ->reverse();
            $czMonths = [1=>'leden','únor','březen','duben','květen','červen','červenec','srpen','září','říjen','listopad','prosinec'];
        @endphp

        @if ($mesice->isNotEmpty())
        <div class="month-downloads">
            <h3>Stáhnout doklady za měsíc (ZIP)</h3>
            <div class="month-list">
                @foreach ($mesice as $m)
                    @php [$y,$mo] = explode('-', $m); @endphp
                    <a href="{{ route('doklady.downloadMonth', $m) }}" class="month-link">{{ $czMonths[(int)$mo] }} {{ $y }}</a>
                @endforeach
            </div>
        </div>
        @endif
        @endif
    @endif
</div>

<div class="preview-overlay" id="previewOverlay">
    <div class="preview-container">
        <button class="preview-close" onclick="closePreview()">&times;</button>
        <div id="previewContent"></div>
    </div>
</div>
@endsection

@section('scripts')
<script>
// ===== Toast =====
function autoHideToasts() {
    document.querySelectorAll('.toast[data-auto-hide]').forEach(t => {
        setTimeout(() => { t.style.opacity = '0'; setTimeout(() => t.remove(), 500); }, 12000);
    });
}
autoHideToasts();

function addToast(message, type) {
    const c = document.getElementById('toastContainer');
    const d = document.createElement('div');
    d.className = 'toast toast-' + type;
    d.setAttribute('data-auto-hide', '');
    d.textContent = message;
    c.appendChild(d);
    setTimeout(() => { d.style.opacity = '0'; setTimeout(() => d.remove(), 500); }, 12000);
}

// ===== Column definitions =====
const COLUMNS = [
    { id: 'expand',    label: '',           tip: null, sortable: false, editable: false, fixed: true,  field: null },
    { id: 'nahrano',   label: 'Nahráno',    tip: 'Datum a čas vložení do systému', sortable: 'created_at', editable: false, fixed: false, field: null },
    { id: 'cas_nahrani', label: 'Čas',      tip: 'Čas nahrání dokladu', sortable: false, editable: false, fixed: false, field: null },
    { id: 'datum_prijeti', label: 'Přijetí', tip: 'Datum přijetí dokladu do účetnictví', sortable: 'datum_prijeti', editable: 'date', fixed: false, field: 'datum_prijeti' },
    { id: 'duzp',      label: 'DUZP',       tip: 'Datum uskutečnění zdanitelného plnění', sortable: 'duzp', editable: 'date', fixed: false, field: 'duzp' },
    { id: 'vystaveni', label: 'Vystavení',  tip: 'Datum vystavení dokladu dodavatelem', sortable: 'datum_vystaveni', editable: 'date', fixed: false, field: 'datum_vystaveni' },
    { id: 'splatnost', label: 'Splatnost',  tip: 'Datum splatnosti', sortable: 'datum_splatnosti', editable: 'date', fixed: false, field: 'datum_splatnosti' },
    { id: 'cislo',     label: 'Číslo',      tip: 'Číslo/variabilní symbol dokladu', sortable: false, editable: 'text', fixed: false, field: 'cislo_dokladu' },
    { id: 'nahled',    label: '',            tip: null, sortable: false, editable: false, fixed: true,  field: null },
    { id: 'dodavatel', label: 'Dodavatel',  tip: 'Název dodavatele/vystavitele', sortable: false, editable: 'text', fixed: false, field: 'dodavatel_nazev' },
    { id: 'ico',       label: 'IČ',         tip: 'IČO dodavatele', sortable: false, editable: 'text', fixed: false, field: 'dodavatel_ico' },
    { id: 'castka',    label: 'Částka',      tip: 'Celková částka s DPH', sortable: false, editable: 'text', fixed: false, field: 'castka_celkem' },
    { id: 'mena',      label: 'Měna',       tip: 'Měna dokladu', sortable: false, editable: 'text', fixed: false, field: 'mena' },
    { id: 'dph',       label: 'DPH',        tip: 'Částka DPH', sortable: false, editable: 'text', fixed: false, field: 'castka_dph' },
    { id: 'kategorie', label: 'Kategorie',  tip: 'Účetní kategorie nákladu', sortable: false, editable: 'text', fixed: false, field: 'kategorie' },
    { id: 'stav',      label: 'Stav',       tip: 'Stav zpracování', sortable: false, editable: false, fixed: false, field: null },
    { id: 'typ',       label: 'Typ',        tip: 'Typ dokladu (faktura, účtenka, ...)', sortable: false, editable: false, fixed: false, field: null },
    { id: 'kvalita',   label: 'Kvalita',    tip: 'Kvalita čitelnosti dokladu', sortable: false, editable: false, fixed: false, field: null },
    { id: 'zdroj',     label: 'Zdroj',      tip: 'Způsob vložení (ruční/email)', sortable: false, editable: false, fixed: false, field: null },
    { id: 'soubor',    label: 'Soubor',     tip: 'Název nahraného souboru', sortable: false, editable: false, fixed: false, field: null },
    { id: 'smazat',    label: '',            tip: null, sortable: false, editable: false, fixed: true,  field: null },
];

const DEFAULT_VISIBLE = ['expand','nahrano','vystaveni','nahled','dodavatel','ico','castka','mena','stav','smazat'];
const FIXED_COLS = ['expand','nahled','smazat'];

function loadPref(key, def) { try { const v = localStorage.getItem(key); return v ? JSON.parse(v) : def; } catch(e) { return def; } }
function savePref(key, val) { localStorage.setItem(key, JSON.stringify(val)); }

let visibleCols = loadPref('doklady_columns', DEFAULT_VISIBLE);
// Ensure fixed cols always present
FIXED_COLS.forEach(c => { if (!visibleCols.includes(c)) visibleCols.push(c); });

let colOrder = loadPref('doklady_column_order', COLUMNS.map(c => c.id));
// Ensure all columns are in order (add any new ones at end)
COLUMNS.forEach(c => { if (!colOrder.includes(c.id)) colOrder.push(c.id); });

function getOrderedVisible() {
    return colOrder.filter(id => visibleCols.includes(id));
}

function getColDef(id) { return COLUMNS.find(c => c.id === id); }

// ===== Build column panel =====
function buildColPanel() {
    const panel = document.getElementById('colPanel');
    panel.innerHTML = '';
    COLUMNS.filter(c => !c.fixed && c.label).forEach(c => {
        const lbl = document.createElement('label');
        const cb = document.createElement('input');
        cb.type = 'checkbox';
        cb.checked = visibleCols.includes(c.id);
        cb.onchange = () => {
            if (cb.checked) { if (!visibleCols.includes(c.id)) visibleCols.push(c.id); }
            else { visibleCols = visibleCols.filter(x => x !== c.id); }
            savePref('doklady_columns', visibleCols);
            renderTable();
        };
        lbl.appendChild(cb);
        lbl.appendChild(document.createTextNode(' ' + c.label));
        panel.appendChild(lbl);
    });
}

// ===== Cell value =====
function cellValue(d, colId) {
    switch(colId) {
        case 'expand': return '<button class="expand-btn" onclick="toggleDetail('+d.id+',this)">&#9654;</button>';
        case 'nahrano': return d.created_at || '-';
        case 'cas_nahrani': return d.created_at_time || '-';
        case 'datum_prijeti': return d.datum_prijeti || '-';
        case 'duzp': return d.duzp || '-';
        case 'vystaveni': return d.datum_vystaveni || '-';
        case 'splatnost': return d.datum_splatnosti || '-';
        case 'cislo': return '<a href="'+d.show_url+'">'+(d.cislo_dokladu || d.nazev_souboru)+'</a>' + (d.duplicita_id ? '<span class="badge-dup" title="Možná duplicita">DUP</span>' : '');
        case 'nahled': return d.preview_url ? '<a href="#" class="btn-preview" title="Náhled" onclick="openPreview(\''+d.preview_url+'\',\''+d.preview_ext+'\');return false;">&#128065;</a>' : '';
        case 'dodavatel': return d.dodavatel_nazev || '-';
        case 'ico': return d.dodavatel_ico || '-';
        case 'castka': return d.castka_celkem ? Number(d.castka_celkem).toLocaleString('cs-CZ', {minimumFractionDigits:2, maximumFractionDigits:2}) : '-';
        case 'mena': return d.mena || '-';
        case 'dph': return d.castka_dph ? Number(d.castka_dph).toLocaleString('cs-CZ', {minimumFractionDigits:2, maximumFractionDigits:2}) : '-';
        case 'kategorie': return d.kategorie || '-';
        case 'stav':
            if (d.stav === 'dokonceno') return '<span class="stav-dokonceno" title="Dokončeno">&#10003;</span>';
            if (d.stav === 'nekvalitni') return '<span class="stav-nekvalitni" title="'+(d.kvalita_poznamka||'Nízká kvalita')+'">&#9888;</span>';
            if (d.stav === 'chyba') return '<span class="stav-chyba">Chyba</span>';
            return '<span class="stav-zpracovava">'+d.stav+'</span>';
        case 'typ':
            const typLabels = {faktura:'Faktura', uctenka:'Účtenka', pokladni_doklad:'Pokl. dokl.', dobropis:'Dobropis', zalohova_faktura:'Zál. faktura', pokuta:'Pokuta', jine:'Jiné'};
            return typLabels[d.typ_dokladu] || d.typ_dokladu || '-';
        case 'kvalita':
            if (d.kvalita === 'nizka') return '<span class="badge-kvalita kvalita-nizka" title="'+(d.kvalita_poznamka||'')+'">Nízká</span>';
            if (d.kvalita === 'necitelna') return '<span class="badge-kvalita kvalita-necitelna" title="'+(d.kvalita_poznamka||'')+'">Nečitelná</span>';
            return '';
        case 'zdroj': return d.zdroj === 'email' ? 'Email' : 'Ruční';
        case 'soubor': return d.nazev_souboru || '-';
        case 'smazat': return '<form action="'+d.destroy_url+'" method="POST" style="display:inline" onsubmit="return confirm(\'Smazat doklad '+(d.cislo_dokladu||d.nazev_souboru)+'?\')"><input type="hidden" name="_token" value="'+csrfToken+'"><input type="hidden" name="_method" value="DELETE"><button type="submit" class="btn-del-sm" title="Smazat">&times;</button></form>';
        default: return '-';
    }
}

function editRawValue(d, colId) {
    switch(colId) {
        case 'datum_prijeti': return d.datum_prijeti_raw || '';
        case 'duzp': return d.duzp_raw || '';
        case 'vystaveni': return d.datum_vystaveni_raw || '';
        case 'splatnost': return d.datum_splatnosti_raw || '';
        case 'cislo': return d.cislo_dokladu || '';
        case 'dodavatel': return d.dodavatel_nazev || '';
        case 'ico': return d.dodavatel_ico || '';
        case 'castka': return d.castka_celkem || '';
        case 'mena': return d.mena || '';
        case 'dph': return d.castka_dph || '';
        case 'kategorie': return d.kategorie || '';
        default: return '';
    }
}

// ===== Render table =====
function renderTable() {
    const cols = getOrderedVisible();
    const container = document.getElementById('tableContainer');
    if (!container) return;

    let html = '<table class="doklady-table"><thead><tr>';
    cols.forEach(colId => {
        const c = getColDef(colId);
        const draggable = !c.fixed ? ' draggable="true"' : '';
        const sortAttr = c.sortable ? ` data-sort="${c.sortable}"` : '';
        let label = c.label;
        if (c.sortable) {
            const newDir = (sortCol === c.sortable && sortDir === 'desc') ? 'asc' : 'desc';
            const arrow = sortCol === c.sortable ? (sortDir === 'asc' ? ' <span class="sort-arrow">&#9650;</span>' : ' <span class="sort-arrow">&#9660;</span>') : '';
            const params = new URLSearchParams({sort: c.sortable, dir: newDir});
            if (searchQ) params.set('q', searchQ);
            label = '<a href="?'+params.toString()+'" class="sort-link">'+c.label+arrow+'</a>';
        }
        const tip = c.tip ? '<span class="col-help" title="'+c.tip+'">?</span>' : '';
        const align = colId === 'castka' ? ' style="text-align:right"' : '';
        html += '<th data-col="'+colId+'"'+draggable+align+'>'+label+tip+'</th>';
    });
    html += '</tr></thead><tbody>';

    dokladyData.forEach(d => {
        html += '<tr data-id="'+d.id+'">';
        cols.forEach(colId => {
            const c = getColDef(colId);
            const align = colId === 'castka' ? ' class="amount"' : '';
            const editCls = c.editable ? ' class="editable"' : '';
            const editIcon = c.editable ? '<span class="edit-icon" onclick="startEdit(this,'+d.id+',\''+colId+'\')">&#9998;</span>' : '';
            html += '<td data-col="'+colId+'"'+align+editCls+'><span class="cell-val">'+cellValue(d, colId)+'</span>'+editIcon+'</td>';
        });
        html += '</tr>';
    });
    html += '</tbody></table>';
    html += '<div class="table-count">'+dokladyData.length+' '+(dokladyData.length===1?'doklad':(dokladyData.length<5?'doklady':'dokladů'))+'</div>';

    container.innerHTML = html;
    initDragDrop();
}

// ===== Expand detail row =====
function toggleDetail(id, btn) {
    const tr = btn.closest('tr');
    const existing = tr.nextElementSibling;
    if (existing && existing.classList.contains('detail-row')) {
        existing.remove();
        btn.innerHTML = '&#9654;';
        return;
    }
    btn.innerHTML = '&#9660;';
    const d = dokladyData.find(x => x.id === id);
    if (!d) return;

    const colCount = getOrderedVisible().length;
    const detailTr = document.createElement('tr');
    detailTr.className = 'detail-row';

    const labels = {
        nazev_souboru: 'Soubor', stav: 'Stav', typ_dokladu: 'Typ dokladu', kvalita: 'Kvalita',
        kvalita_poznamka: 'Poznámka ke kvalitě',
        dodavatel_nazev: 'Dodavatel', dodavatel_ico: 'IČO dodavatele',
        cislo_dokladu: 'Číslo dokladu', datum_vystaveni: 'Datum vystavení', datum_prijeti: 'Datum přijetí',
        duzp: 'DUZP', datum_splatnosti: 'Datum splatnosti', castka_celkem: 'Celková částka', mena: 'Měna',
        castka_dph: 'DPH', kategorie: 'Kategorie', zdroj: 'Zdroj', created_at_full: 'Nahráno',
        chybova_zprava: 'Chyba'
    };
    const fields = ['nazev_souboru','stav','typ_dokladu','kvalita','kvalita_poznamka',
        'dodavatel_nazev','dodavatel_ico','cislo_dokladu',
        'datum_vystaveni','datum_prijeti','duzp','datum_splatnosti','castka_celkem','mena',
        'castka_dph','kategorie','zdroj','created_at_full','chybova_zprava'];

    let inner = '<table>';
    fields.forEach(f => {
        let val = d[f];
        if (val === null || val === undefined || val === '') val = '-';
        if (f === 'stav') {
            if (val === 'dokonceno') val = '<span class="stav-dokonceno">Dokončeno</span>';
            else if (val === 'nekvalitni') val = '<span class="stav-nekvalitni">Nekvalitní</span>';
            else if (val === 'chyba') val = '<span class="stav-chyba">Chyba</span>';
        }
        if (f === 'typ_dokladu') {
            const typL = {faktura:'Faktura', uctenka:'Účtenka', pokladni_doklad:'Pokladní doklad', dobropis:'Dobropis', zalohova_faktura:'Zálohová faktura', pokuta:'Pokuta', jine:'Jiné'};
            val = typL[val] || val || '-';
        }
        if (f === 'kvalita') {
            if (val === 'dobra') val = 'Dobrá';
            else if (val === 'nizka') val = '<span class="stav-nekvalitni">Nízká</span>';
            else if (val === 'necitelna') val = '<span class="stav-chyba">Nečitelná</span>';
        }
        if (f === 'zdroj') val = val === 'email' ? 'Email' : 'Ruční nahrání';
        if ((f === 'castka_celkem' || f === 'castka_dph') && val !== '-') {
            val = Number(val).toLocaleString('cs-CZ', {minimumFractionDigits:2, maximumFractionDigits:2}) + ' ' + (d.mena || '');
        }
        inner += '<tr><th>'+labels[f]+'</th><td>'+val+'</td></tr>';
    });
    inner += '</table>';

    detailTr.innerHTML = '<td colspan="'+colCount+'"><div class="detail-inner">'+inner+'</div></td>';
    tr.after(detailTr);
}

// ===== Inline edit =====
function startEdit(icon, id, colId) {
    const td = icon.closest('td');
    if (td.querySelector('.edit-input')) return;
    const d = dokladyData.find(x => x.id === id);
    const c = getColDef(colId);
    const rawVal = editRawValue(d, colId);

    const valSpan = td.querySelector('.cell-val');
    const editSpan = td.querySelector('.edit-icon');
    valSpan.style.display = 'none';
    if (editSpan) editSpan.style.display = 'none';

    const input = document.createElement('input');
    input.className = 'edit-input';
    input.type = c.editable === 'date' ? 'date' : 'text';
    input.value = rawVal;
    td.appendChild(input);
    input.focus();
    input.select();

    function save() {
        const newVal = input.value;
        input.remove();
        valSpan.style.display = '';
        if (editSpan) editSpan.style.display = '';

        fetch(d.update_url, {
            method: 'PATCH',
            headers: {'Content-Type':'application/json','X-CSRF-TOKEN':csrfToken,'X-Requested-With':'XMLHttpRequest','Accept':'application/json'},
            body: JSON.stringify({field: c.field, value: newVal || null})
        }).then(r => r.json()).then(res => {
            if (res.ok) {
                // Update local data and re-render cell
                if (c.editable === 'date' && newVal) {
                    const parts = newVal.split('-');
                    d[colId === 'vystaveni' ? 'datum_vystaveni' : colId === 'splatnost' ? 'datum_splatnosti' : colId] = parts[2]+'.'+parts[1]+'.'+parts[0].slice(2);
                    d[(colId === 'vystaveni' ? 'datum_vystaveni' : colId === 'splatnost' ? 'datum_splatnosti' : colId) + '_raw'] = newVal;
                } else {
                    // text fields
                    if (c.field) d[c.field.replace('dodavatel_','dodavatel_')] = newVal;
                    // Map field back to data key
                    const keyMap = {dodavatel_nazev:'dodavatel_nazev', dodavatel_ico:'dodavatel_ico', cislo_dokladu:'cislo_dokladu', castka_celkem:'castka_celkem', mena:'mena', castka_dph:'castka_dph', kategorie:'kategorie'};
                    if (keyMap[c.field]) d[keyMap[c.field]] = newVal;
                }
                valSpan.innerHTML = cellValue(d, colId);
            }
        }).catch(() => {});
    }

    function cancel() { input.remove(); valSpan.style.display = ''; if (editSpan) editSpan.style.display = ''; }

    input.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); save(); } if (e.key === 'Escape') cancel(); });
    input.addEventListener('blur', save);
}

// ===== Drag & Drop column reorder =====
function initDragDrop() {
    let dragCol = null;
    document.querySelectorAll('.doklady-table th[draggable="true"]').forEach(th => {
        th.addEventListener('dragstart', e => {
            dragCol = th.dataset.col;
            e.dataTransfer.effectAllowed = 'move';
            th.style.opacity = '0.5';
        });
        th.addEventListener('dragend', () => { th.style.opacity = '1'; document.querySelectorAll('.drag-over').forEach(el => el.classList.remove('drag-over')); });
        th.addEventListener('dragover', e => { e.preventDefault(); e.dataTransfer.dropEffect = 'move'; th.classList.add('drag-over'); });
        th.addEventListener('dragleave', () => th.classList.remove('drag-over'));
        th.addEventListener('drop', e => {
            e.preventDefault();
            th.classList.remove('drag-over');
            const targetCol = th.dataset.col;
            if (dragCol && dragCol !== targetCol) {
                const fromIdx = colOrder.indexOf(dragCol);
                const toIdx = colOrder.indexOf(targetCol);
                colOrder.splice(fromIdx, 1);
                colOrder.splice(toIdx, 0, dragCol);
                savePref('doklady_column_order', colOrder);
                renderTable();
            }
        });
    });
}

// ===== Upload =====
const dropZone = document.getElementById('dropZone');
const fileInput = document.getElementById('fileInput');
const uploadProcessing = document.getElementById('uploadProcessing');
const uploadStatus = document.getElementById('uploadStatus');

if (dropZone) {
    dropZone.addEventListener('click', () => fileInput.click());
    dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('dragover'); });
    dropZone.addEventListener('dragleave', () => dropZone.classList.remove('dragover'));
    dropZone.addEventListener('drop', e => { e.preventDefault(); dropZone.classList.remove('dragover'); uploadFiles(e.dataTransfer.files); });
    fileInput.addEventListener('change', () => { uploadFiles(fileInput.files); fileInput.value = ''; });
}

function uploadFiles(files) {
    const allowed = ['application/pdf', 'image/jpeg', 'image/png'];
    const validFiles = [];
    for (const file of files) {
        if (!allowed.includes(file.type) || file.size > 10*1024*1024) continue;
        validFiles.push(file);
    }
    if (!validFiles.length) return;

    dropZone.style.display = 'none';
    uploadProcessing.style.display = 'block';
    const noun = validFiles.length === 1 ? 'doklad' : (validFiles.length < 5 ? 'doklady' : 'dokladů');
    uploadStatus.textContent = 'Zpracovávám ' + validFiles.length + ' ' + noun + '...';

    const formData = new FormData();
    formData.append('_token', csrfToken);
    validFiles.forEach(f => formData.append('documents[]', f));

    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 90000);

    fetch('{{ route("invoices.store") }}', {
        method: 'POST', body: formData,
        headers: {'X-Requested-With':'XMLHttpRequest','Accept':'application/json'},
        signal: controller.signal,
    }).then(r => {
        clearTimeout(timeoutId);
        uploadStatus.textContent = 'Zpracovávám odpověď (HTTP ' + r.status + ')...';
        const contentType = r.headers.get('content-type') || '';
        if (!contentType.includes('application/json')) {
            return r.text().then(body => {
                throw new Error('Server vrátil HTTP ' + r.status + ' (' + contentType + '): ' + body.substring(0, 200));
            });
        }
        return r.json();
    }).then(results => {
        uploadProcessing.style.display = 'none';
        dropZone.style.display = 'block';
        if (Array.isArray(results)) {
            results.forEach(r => addToast(r.message, r.status));
        } else {
            addToast('Neočekávaná odpověď ze serveru.', 'error');
        }
        setTimeout(() => window.location.href = '{{ route("doklady.index") }}', 1500);
    }).catch(err => {
        clearTimeout(timeoutId);
        dropZone.style.display = 'block';
        uploadProcessing.style.display = 'none';
        const msg = err.name === 'AbortError'
            ? 'Časový limit vypršel (90s). Zkontrolujte diagnose.php?mode=log pro detaily.'
            : (err.message || 'Chyba při odesílání. Zkuste to znovu.');
        addToast(msg, 'error');
    });
}

// ===== Search on Enter =====
document.querySelector('.search-input')?.addEventListener('keydown', function(e) {
    if (e.key === 'Enter') this.closest('form').submit();
});

// ===== Preview =====
function openPreview(url, ext) {
    const content = document.getElementById('previewContent');
    const overlay = document.getElementById('previewOverlay');
    content.innerHTML = ext === 'pdf' ? '<iframe src="'+url+'"></iframe>' : '<img src="'+url+'" alt="Náhled dokladu">';
    overlay.classList.add('active');
}
function closePreview() {
    document.getElementById('previewOverlay').classList.remove('active');
    document.getElementById('previewContent').innerHTML = '';
}
document.getElementById('previewOverlay').addEventListener('click', function(e) { if (e.target === this) closePreview(); });
document.addEventListener('keydown', e => { if (e.key === 'Escape') closePreview(); });

// ===== Init =====
buildColPanel();
renderTable();
</script>
@endsection
