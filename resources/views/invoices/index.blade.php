@extends('layouts.app')

@section('title', 'Doklady')

@section('styles')
<style>
    .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
    .page-header h2 { margin: 0; }

    .upload-zone { border: 2px dashed #bdc3c7; border-radius: 8px; padding: 1.2rem; text-align: center; cursor: pointer; transition: all 0.3s; margin-bottom: 1.5rem; }
    .upload-zone:hover, .upload-zone.dragover { border-color: #3498db; background: #ebf5fb; }
    .upload-zone.compact { padding: 0.5rem; margin-bottom: 0.75rem; }
    .upload-zone.compact p { font-size: 0.8rem; }
    .upload-zone.compact .formats { display: none; }
    .upload-zone p { color: #7f8c8d; margin: 0; font-size: 0.9rem; }
    .upload-zone .formats { font-size: 0.8rem; color: #95a5a6; margin-top: 0.3rem; }
    @keyframes spin { to { transform: rotate(360deg); } }

    /* Upload list */
    .upload-panel { display: none; border: 1px solid #d0d8e0; border-radius: 8px; margin-bottom: 1.5rem; overflow: hidden; }
    .upload-panel.active { display: block; }
    .upload-panel-header { background: #f0f4f8; padding: 0.5rem 1rem; font-size: 0.85rem; font-weight: 600; color: #555; display: flex; justify-content: space-between; align-items: center; }
    .upload-item { display: flex; align-items: center; padding: 0.45rem 1rem; border-bottom: 1px solid #eee; font-size: 0.85rem; gap: 0.6rem; transition: opacity 0.5s; }
    .upload-item:last-child { border-bottom: none; }
    .upload-item-icon { width: 20px; text-align: center; flex-shrink: 0; }
    .upload-item-name { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .upload-item-size { color: #95a5a6; font-size: 0.75rem; flex-shrink: 0; }
    .upload-item-status { font-size: 0.8rem; flex-shrink: 1; min-width: 0; }
    .upload-item-dismiss { background: none; border: none; cursor: pointer; color: #bdc3c7; font-size: 1rem; padding: 0 0.3rem; line-height: 1; flex-shrink: 0; }
    .upload-item-dismiss:hover { color: #555; }
    .upload-item .spinner-sm { display: inline-block; width: 14px; height: 14px; border: 2px solid #bdc3c7; border-top-color: #3498db; border-radius: 50%; animation: spin 0.8s linear infinite; }
    .upload-item.status-ok .upload-item-icon { color: #27ae60; }
    .upload-item.status-warning .upload-item-icon { color: #e67e22; }
    .upload-item.status-error .upload-item-icon { color: #e74c3c; }
    .upload-item.status-duplicate .upload-item-icon { color: #95a5a6; }
    .upload-item.status-info .upload-item-icon { color: #2980b9; }
    .upload-item.status-warning, .upload-item.status-error { background: #fffaf5; flex-wrap: wrap; }
    .upload-item.status-warning .upload-item-status, .upload-item.status-error .upload-item-status { flex-basis: 100%; padding-left: 26px; font-size: 0.78rem; margin-top: -0.1rem; }
    .upload-item.status-warning .upload-item-status { color: #e67e22; }
    .upload-item.status-error .upload-item-status { color: #e74c3c; }

    /* Alert history */
    .alert-history-toggle { font-size: 0.8rem; color: #95a5a6; cursor: pointer; margin-bottom: 0.5rem; display: none; }
    .alert-history-toggle:hover { color: #555; }
    .alert-history { display: none; border: 1px solid #e8ecf0; border-radius: 6px; margin-bottom: 1rem; }
    .alert-history.open { display: block; }

    .ai-search-bar { display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.75rem; padding: 0.5rem 1rem; background: #f0f4f8; border: 1px solid #d0d8e0; border-radius: 8px; }
    .ai-search-label { font-size: 0.78rem; font-weight: 600; color: #555; white-space: nowrap; }
    .ai-search-input-wrap { flex: 1; display: flex; }
    .ai-search-input { flex: 1; padding: 0.45rem 0.75rem; border: 1px solid #d0d8e0; border-radius: 6px 0 0 6px; font-size: 0.85rem; outline: none; }
    .ai-search-input:focus { border-color: #3498db; }
    .ai-search-btn { padding: 0.45rem 0.75rem; background: #3498db; color: white; border: 1px solid #2980b9; border-left: none; border-radius: 0 6px 6px 0; cursor: pointer; font-size: 0.9rem; }
    .ai-search-btn:hover { background: #2980b9; }
    .ai-search-btn:disabled { background: #95a5a6; cursor: wait; }
    .ai-search-help { width: 20px; height: 20px; background: #bdc3c7; color: white; border-radius: 50%; font-size: 0.7rem; font-weight: bold; cursor: help; display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .ai-search-result { background: #eaf6ff; border: 1px solid #bee0f7; border-radius: 6px; padding: 0.5rem 1rem; margin-bottom: 0.75rem; font-size: 0.85rem; color: #2c3e50; display: flex; justify-content: space-between; align-items: center; }
    .ai-search-clear { background: none; border: 1px solid #bee0f7; border-radius: 4px; color: #3498db; cursor: pointer; padding: 0.2rem 0.5rem; font-size: 0.8rem; }
    .ai-search-clear:hover { background: #d6eeff; }

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
    .stav-chyba { color: #e74c3c; font-weight: 600; cursor: help; }
    .stav-zpracovava { color: #f39c12; font-weight: 600; }
    .stav-nekvalitni { color: #e67e22; font-weight: 600; cursor: help; }
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

    .recent-upload { color: #27ae60 !important; font-weight: 600; }

    .preview-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 1000; justify-content: center; align-items: center; }
    .preview-overlay.active { display: flex; }
    .preview-container { position: relative; width: 90vw; height: 90vh; max-width: 1000px; background: white; border-radius: 8px; overflow: hidden; display: flex; flex-direction: column; }
    .preview-container #previewContent { flex: 1; min-height: 0; overflow: hidden; position: relative; cursor: grab; }
    .preview-container #previewContent.dragging { cursor: grabbing; }
    .preview-container iframe { width: 100%; height: 100%; border: none; }
    .preview-container #previewContent img { position: absolute; top: 50%; left: 50%; transform-origin: 0 0; user-select: none; -webkit-user-drag: none; }
    .preview-close { position: absolute; top: 8px; right: 12px; background: rgba(0,0,0,0.5); color: white; border: none; font-size: 1.5rem; width: 36px; height: 36px; border-radius: 50%; cursor: pointer; z-index: 1001; line-height: 1; }
    .preview-close:hover { background: rgba(0,0,0,0.8); }
    .preview-toolbar { position: absolute; bottom: 12px; left: 50%; transform: translateX(-50%); display: flex; gap: 6px; z-index: 1001; background: rgba(0,0,0,0.6); border-radius: 20px; padding: 4px 8px; }
    .preview-toolbar button { background: none; border: none; color: white; font-size: 1.2rem; width: 32px; height: 32px; border-radius: 50%; cursor: pointer; line-height: 1; }
    .preview-toolbar button:hover { background: rgba(255,255,255,0.2); }
    .preview-toolbar .zoom-level { color: rgba(255,255,255,0.8); font-size: 0.75rem; display: flex; align-items: center; padding: 0 4px; min-width: 40px; justify-content: center; }
</style>
@endsection

@section('content')
<div class="card">
    <div class="page-header">
        <h2>Doklady</h2>
    </div>

    <div class="alert-history-toggle" id="alertHistoryToggle" onclick="toggleAlertHistory()">
        <span id="alertHistoryArrow">&#9654;</span> Zobrazit skryté hlášky (<span id="alertHistoryCount">0</span>)
    </div>
    <div class="alert-history" id="alertHistory"></div>

    @if (!$firma)
        <div class="warning-msg">Nejdříve vyplňte <a href="{{ route('firma.nastaveni') }}">nastavení firmy</a>.</div>
    @else
        <script>
            var csrfToken = '{{ csrf_token() }}';
            var uploadUrl = '{{ route("invoices.store") }}';
            var aiSearchUrl = '{{ route("doklady.aiSearch") }}';
        </script>
        <div class="upload-zone" id="dropZone">
            <p>Přetáhněte soubory sem nebo klikněte pro výběr</p>
            <p class="formats">PDF, JPG, PNG (max 10 MB)</p>
        </div>
        <input type="file" id="fileInput" accept=".pdf,.jpg,.jpeg,.png" multiple style="display: none;">
        <div class="upload-panel" id="uploadPanel">
            <div class="upload-panel-header">
                <span id="uploadPanelTitle">Nahrávám soubory...</span>
                <span id="uploadPanelProgress"></span>
            </div>
            <div id="uploadList"></div>
        </div>
    @endif

    @if (session('flash') && ($doklady->isEmpty() && empty($q)))
    <script>
    (function() {
        const msg = @json(session('flash'));
        const panel = document.getElementById('uploadPanel');
        const list = document.getElementById('uploadList');
        if (!panel || !list) return;
        const item = document.createElement('div');
        item.className = 'upload-item status-info';
        item.innerHTML = '<span class="upload-item-icon">&#8505;</span><span class="upload-item-name">' + msg + '</span>';
        const btn = document.createElement('button');
        btn.className = 'upload-item-dismiss';
        btn.innerHTML = '&times;';
        btn.onclick = function() { item.style.opacity='0'; setTimeout(() => { item.remove(); if (!list.children.length) panel.classList.remove('active'); }, 300); };
        item.appendChild(btn);
        list.appendChild(item);
        panel.classList.add('active');
        document.getElementById('uploadPanelTitle').textContent = '';
        document.getElementById('uploadPanelProgress').textContent = '';
        setTimeout(() => { item.style.opacity='0'; setTimeout(() => { item.remove(); if (!list.children.length) panel.classList.remove('active'); }, 500); }, 8000);
    })();
    </script>
    @endif

    <script>
        var dokladyData = {!! json_encode($dokladyJson, JSON_UNESCAPED_UNICODE) !!};
        var sortCol = '{{ $sort }}';
        var sortDir = '{{ $dir }}';
        var searchQ = '{{ $q }}';
    </script>

    <div class="ai-search-bar">
        <span class="ai-search-label">AI hledání</span>
        <div class="ai-search-input-wrap">
            <input type="text" id="aiSearchInput" class="ai-search-input"
                   placeholder="Napište co hledáte, např: pohonné hmoty, květen 2025..."
                   value="{{ $q }}">
            <button type="button" id="aiSearchBtn" class="ai-search-btn"
                    title="Hledat">&#128269;</button>
        </div>
        <span class="ai-search-help" title="Pište přirozeně česky:&#10;&#8226; 'pohonné hmoty za květen 2025'&#10;&#8226; 'faktury od Alza nad 5000 Kč'&#10;&#8226; 'doklady s chybou'&#10;&#8226; 'účtenky z minulého měsíce'&#10;AI převede dotaz na filtry automaticky.">?</span>
    </div>
    <div id="aiSearchResult" class="ai-search-result" style="display:none;">
        <span id="aiSearchDesc"></span>
        <button type="button" id="aiSearchClear" class="ai-search-clear">Zrušit filtr</button>
    </div>

    <div class="col-toggle" id="colToggle" onclick="document.getElementById('colPanel').classList.toggle('open'); this.querySelector('span').textContent = document.getElementById('colPanel').classList.contains('open') ? '\u25BC' : '\u25B6';"><span>&#9654;</span> Přidat/ubrat sloupce</div>
    <div class="col-panel" id="colPanel"></div>

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
</div>

<div class="preview-overlay" id="previewOverlay">
    <div class="preview-container">
        <button class="preview-close" onclick="closePreview()">&times;</button>
        <div id="previewContent"></div>
        <div class="preview-toolbar" id="previewToolbar" style="display:none;">
            <button onclick="previewZoom(-1)" title="Oddálit">&#8722;</button>
            <span class="zoom-level" id="zoomLevel">100%</span>
            <button onclick="previewZoom(1)" title="Přiblížit">&#43;</button>
            <button onclick="previewZoomFit()" title="Přizpůsobit">&#8596;</button>
            <button onclick="previewZoomReal()" title="Skutečná velikost">1:1</button>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
// ===== Alert history =====
let dismissedAlerts = [];

function toggleAlertHistory() {
    const el = document.getElementById('alertHistory');
    const arrow = document.getElementById('alertHistoryArrow');
    el.classList.toggle('open');
    arrow.innerHTML = el.classList.contains('open') ? '&#9660;' : '&#9654;';
}

function dismissUploadItem(btn) {
    const item = btn.closest('.upload-item');
    const msg = item.querySelector('.upload-item-status')?.textContent || '';
    const name = item.querySelector('.upload-item-name')?.textContent || '';
    dismissedAlerts.push({ name, msg, cls: item.className });
    item.style.opacity = '0';
    setTimeout(() => {
        item.remove();
        updateAlertHistory();
        checkUploadPanelEmpty();
    }, 300);
}

function updateAlertHistory() {
    const toggle = document.getElementById('alertHistoryToggle');
    const container = document.getElementById('alertHistory');
    const count = document.getElementById('alertHistoryCount');
    if (dismissedAlerts.length === 0) { toggle.style.display = 'none'; return; }
    toggle.style.display = 'block';
    count.textContent = dismissedAlerts.length;
    container.innerHTML = dismissedAlerts.map(a =>
        '<div class="upload-item" style="opacity:0.7"><span class="upload-item-name">' + a.name + '</span><span class="upload-item-status" style="color:#999">' + a.msg + '</span></div>'
    ).join('');
}

function checkUploadPanelEmpty() {
    const panel = document.getElementById('uploadPanel');
    const items = panel.querySelectorAll('.upload-item');
    if (items.length === 0) panel.classList.remove('active');
}

// ===== Column definitions =====
const COLUMNS = [
    { id: 'expand',    label: '',           tip: null, sortable: false, editable: false, fixed: true,  field: null },
    { id: 'nahrano',   label: 'Nahráno',    tip: 'Datum a čas vložení do systému', sortable: 'created_at', editable: false, fixed: false, field: null },
    { id: 'cas_nahrani', label: 'Čas',      tip: 'Čas nahrání dokladu', sortable: false, editable: false, fixed: false, field: null },
    { id: 'datum_prijeti', label: 'Příjetí', tip: 'Datum příjetí dokladu do účetnictví', sortable: 'datum_prijeti', editable: 'date', fixed: false, field: 'datum_prijeti' },
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
    { id: 'kategorie', label: 'Kategorie',  tip: 'Účetní kategorie nákladů', sortable: false, editable: 'text', fixed: false, field: 'kategorie' },
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
FIXED_COLS.forEach(c => { if (!visibleCols.includes(c)) visibleCols.push(c); });

let colOrder = loadPref('doklady_column_order', COLUMNS.map(c => c.id));
COLUMNS.forEach(c => { if (!colOrder.includes(c.id)) colOrder.push(c.id); });

function getOrderedVisible() {
    return colOrder.filter(id => visibleCols.includes(id));
}

function getColDef(id) { return COLUMNS.find(c => c.id === id); }

// ===== Build column panel =====
function buildColPanel() {
    const panel = document.getElementById('colPanel');
    if (!panel) return;
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

// ===== Recent upload check =====
function isRecentUpload(isoDate) {
    if (!isoDate) return false;
    const uploadTime = new Date(isoDate).getTime();
    const oneHourAgo = Date.now() - 3600000;
    return uploadTime > oneHourAgo;
}

// ===== Cell value =====
function cellValue(d, colId) {
    switch(colId) {
        case 'expand': return '<button class="expand-btn" onclick="toggleDetail('+d.id+',this)">&#9654;</button>';
        case 'nahrano': {
            const cls = isRecentUpload(d.created_at_iso) ? ' class="recent-upload"' : '';
            return '<span'+cls+'>' + (d.created_at || '-') + '</span>';
        }
        case 'cas_nahrani': {
            const cls = isRecentUpload(d.created_at_iso) ? ' class="recent-upload"' : '';
            return '<span'+cls+'>' + (d.created_at_time || '-') + '</span>';
        }
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
            if (d.stav === 'dokonceno') return '<span class="stav-dokonceno" title="V pořádku">&#10003;</span>';
            if (d.stav === 'nekvalitni') return '<span class="stav-nekvalitni" title="'+(d.kvalita_poznamka||'Nízká kvalita')+'">&#9888;</span>';
            if (d.stav === 'chyba') return '<span class="stav-chyba" title="'+(d.chybova_zprava||'Chyba zpracování').replace(/"/g,'&quot;')+'">Chyba</span>';
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

    if (dokladyData.length === 0) {
        container.innerHTML = '<div class="empty-state"><p>Zatím žádné doklady.</p></div>';
        return;
    }

    let html = '<table class="doklady-table"><thead><tr>';
    cols.forEach(colId => {
        const c = getColDef(colId);
        const draggable = !c.fixed ? ' draggable="true"' : '';
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
    html += '<div class="table-count">'+dokladyData.length+' '+(dokladyData.length===1?'doklad':(dokladyData.length<5?'doklady':'dokladu'))+'</div>';

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
        cislo_dokladu: 'Číslo dokladu', datum_vystaveni: 'Datum vystavení', datum_prijeti: 'Datum příjetí',
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
            if (val === 'dokonceno') val = '<span class="stav-dokonceno">V pořádku</span>';
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
                if (c.editable === 'date' && newVal) {
                    const parts = newVal.split('-');
                    d[colId === 'vystaveni' ? 'datum_vystaveni' : colId === 'splatnost' ? 'datum_splatnosti' : colId] = parts[2]+'.'+parts[1]+'.'+parts[0].slice(2);
                    d[(colId === 'vystaveni' ? 'datum_vystaveni' : colId === 'splatnost' ? 'datum_splatnosti' : colId) + '_raw'] = newVal;
                } else {
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
const uploadPanel = document.getElementById('uploadPanel');
const uploadList = document.getElementById('uploadList');

if (dropZone) {
    dropZone.addEventListener('click', () => fileInput.click());
    dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('dragover'); });
    dropZone.addEventListener('dragleave', () => dropZone.classList.remove('dragover'));
    dropZone.addEventListener('drop', e => { e.preventDefault(); dropZone.classList.remove('dragover'); enqueueFiles(e.dataTransfer.files); });
    fileInput.addEventListener('change', () => { enqueueFiles(fileInput.files); fileInput.value = ''; });
}

function formatSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1048576) return (bytes / 1024).toFixed(0) + ' KB';
    return (bytes / 1048576).toFixed(1) + ' MB';
}

// Global upload queue
const uploadQueue = [];     // {file, item} objects waiting or in progress
let uploadCompleted = 0;
let uploadActive = 0;
const MAX_CONCURRENT = 3;   // Parallel uploads: while file 1 is processed by AI, files 2+3 upload
let autoHideTimer = null;

function enqueueFiles(files) {
    const allowed = ['application/pdf', 'image/jpeg', 'image/png'];
    let added = 0;
    for (const file of files) {
        if (!allowed.includes(file.type) || file.size > 10*1024*1024) continue;

        const item = document.createElement('div');
        item.className = 'upload-item status-waiting';
        item.innerHTML =
            '<span class="upload-item-icon"><span class="spinner-sm"></span></span>' +
            '<span class="upload-item-name">' + file.name + '</span>' +
            '<span class="upload-item-size">' + formatSize(file.size) + '</span>' +
            '<span class="upload-item-status">Čeká...</span>';
        uploadList.appendChild(item);
        uploadQueue.push({ file, item });
        added++;
    }
    if (!added) return;

    // Cancel pending auto-hide (new files added)
    if (autoHideTimer) { clearTimeout(autoHideTimer); autoHideTimer = null; }

    // Show panel, shrink drop zone but keep it active
    uploadPanel.classList.add('active');
    dropZone.classList.add('compact');
    dropZone.querySelector('p:first-child').textContent = 'Přidat další soubory';
    updateUploadHeader();

    // Kick workers
    while (uploadActive < MAX_CONCURRENT) {
        if (!processNextInQueue()) break;
    }
}

function updateUploadHeader() {
    const total = uploadQueue.length;
    const pending = total - uploadCompleted - uploadActive;
    if (pending > 0 || uploadActive > 0) {
        document.getElementById('uploadPanelTitle').textContent = 'Nahrávání souborů...';
    } else {
        document.getElementById('uploadPanelTitle').textContent = 'Nahrávání dokončeno';
    }
    document.getElementById('uploadPanelProgress').textContent = uploadCompleted + ' / ' + total;
}

function processNextInQueue() {
    // Find next waiting item
    const entry = uploadQueue.find(e => e.item.classList.contains('status-waiting'));
    if (!entry) return false;

    uploadActive++;
    const { file, item } = entry;

    item.className = 'upload-item status-processing';
    const statusEl = item.querySelector('.upload-item-status');
    statusEl.textContent = 'Zpracovávám... 0s';
    updateUploadHeader();

    // Elapsed timer
    const startTime = Date.now();
    const timerInterval = setInterval(() => {
        const elapsed = Math.round((Date.now() - startTime) / 1000);
        statusEl.textContent = 'Zpracovávám... ' + elapsed + 's';
    }, 1000);

    uploadSingleFile(file).then(result => {
        clearInterval(timerInterval);
        uploadActive--;
        uploadCompleted++;

        try {
            const icon = item.querySelector('.upload-item-icon');
            const status = item.querySelector('.upload-item-status');

            if (result.status === 'ok') {
                item.className = 'upload-item status-ok';
                icon.innerHTML = '&#10003;';
                status.textContent = result.message || 'Nahráno';
            } else if (result.status === 'warning') {
                item.className = 'upload-item status-warning';
                icon.innerHTML = '&#9888;';
                status.textContent = result.message || 'Nahráno s upozorněním';
                addDismissBtn(item);
            } else if (result.status === 'duplicate') {
                item.className = 'upload-item status-duplicate';
                icon.innerHTML = '&#8212;';
                status.textContent = result.message || 'Již existuje';
            } else {
                item.className = 'upload-item status-error';
                icon.innerHTML = '&#10007;';
                status.textContent = result.message || 'Chyba';
                addDismissBtn(item);
            }

            updateUploadHeader();
            refreshTableData();
        } catch(e) {
            console.error('[UPLOAD] UI update error:', e);
        }

        // ALWAYS continue queue - never let it stall
        if (!processNextInQueue()) {
            if (uploadActive === 0) {
                onAllUploadsComplete();
            }
        }
    }).catch(err => {
        // Safety net: uploadSingleFile should never reject, but if it does, continue queue
        clearInterval(timerInterval);
        uploadActive--;
        uploadCompleted++;
        console.error('[UPLOAD] Unexpected error:', err);
        item.className = 'upload-item status-error';
        item.querySelector('.upload-item-icon').innerHTML = '&#10007;';
        item.querySelector('.upload-item-status').textContent = 'Neočekávaná chyba';
        addDismissBtn(item);
        updateUploadHeader();
        if (!processNextInQueue()) {
            if (uploadActive === 0) onAllUploadsComplete();
        }
    });

    return true;
}

function addDismissBtn(item) {
    const btn = document.createElement('button');
    btn.className = 'upload-item-dismiss';
    btn.innerHTML = '&times;';
    btn.onclick = function() { dismissUploadItem(this); };
    item.appendChild(btn);
}

function refreshTableData() {
    fetch(window.location.pathname + (window.location.search || ''), {
        headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'}
    }).then(r => r.json()).then(data => {
        dokladyData = data;
        renderTable();
    }).catch(() => {});
}

function onAllUploadsComplete() {
    updateUploadHeader();
    dropZone.classList.remove('compact');
    dropZone.querySelector('p:first-child').textContent = 'Přetáhněte soubory sem nebo klikněte pro výběr';

    // Auto-hide success and duplicate items after 10s
    autoHideTimer = setTimeout(() => {
        uploadQueue.forEach(e => {
            if (e.item.classList.contains('status-ok') || e.item.classList.contains('status-duplicate')) {
                e.item.style.opacity = '0';
                setTimeout(() => {
                    e.item.remove();
                    checkUploadPanelEmpty();
                }, 500);
            }
        });
        autoHideTimer = null;
    }, 10000);
}

function uploadSingleFile(file) {
    const formData = new FormData();
    formData.append('_token', csrfToken);
    formData.append('documents[]', file);

    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 120000);

    const fetchStart = Date.now();
    console.log('[UPLOAD] START', file.name, Math.round(file.size/1024)+'KB', 'url='+uploadUrl, new Date().toISOString());

    return fetch(uploadUrl, {
        method: 'POST',
        body: formData,
        headers: {'X-Requested-With':'XMLHttpRequest','Accept':'application/json'},
        signal: controller.signal,
    }).then(r => {
        console.log('[UPLOAD] RESPONSE', file.name, 'status='+r.status, 'elapsed='+(Date.now()-fetchStart)+'ms', new Date().toISOString());
        clearTimeout(timeoutId);
        const fetchMs = Date.now() - fetchStart;
        const ct = r.headers.get('content-type') || '';
        if (!ct.includes('application/json')) {
            return r.text().then(body => ({
                status: 'error',
                message: file.name + ' - HTTP ' + r.status + ' (' + fetchMs + 'ms): ' + body.substring(0, 100)
            }));
        }
        return r.json().then(data => {
            const results = data.results || data;
            const firstResult = Array.isArray(results) && results.length > 0 ? results[0] : null;
            if (!firstResult) return { status: 'error', message: file.name + ' - prázdná odpověď' };

            const secs = Math.round(fetchMs / 1000);
            const timingStr = secs > 3 ? ' (' + secs + 's)' : '';

            return {
                status: firstResult.status,
                message: (firstResult.message || file.name) + timingStr,
            };
        });
    }).catch(err => {
        clearTimeout(timeoutId);
        const fetchMs = Date.now() - fetchStart;
        console.log('[UPLOAD] ERROR', file.name, err.name, err.message, 'elapsed='+(Date.now()-fetchStart)+'ms');
        return {
            status: 'error',
            message: err.name === 'AbortError'
                ? file.name + ' - Časový limit (' + Math.round(fetchMs/1000) + 's)'
                : (err.message || 'Chyba sítě') + ' (' + Math.round(fetchMs/1000) + 's)'
        };
    });
}

// ===== AI Search =====
function doAiSearch() {
    const input = document.getElementById('aiSearchInput');
    const btn = document.getElementById('aiSearchBtn');
    const resultBar = document.getElementById('aiSearchResult');
    const descEl = document.getElementById('aiSearchDesc');
    const q = (input ? input.value.trim() : '');
    if (!q) return;

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-sm" style="width:12px;height:12px;border-width:1.5px;vertical-align:middle;"></span>';

    fetch(aiSearchUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        },
        body: JSON.stringify({ q: q })
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '&#128269;';

        const count = data.count || 0;
        const countLabel = count === 1 ? '1 doklad' : (count < 5 ? count + ' doklady' : count + ' dokladů');
        descEl.textContent = (data.description || 'Výsledky') + ' (' + countLabel + ')';
        resultBar.style.display = 'flex';

        dokladyData = data.data || [];
        renderTable();
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = '&#128269;';
        console.error('AI search error:', err);
    });
}

document.getElementById('aiSearchBtn')?.addEventListener('click', doAiSearch);
document.getElementById('aiSearchInput')?.addEventListener('keydown', function(e) {
    if (e.key === 'Enter') { e.preventDefault(); doAiSearch(); }
});
document.getElementById('aiSearchClear')?.addEventListener('click', function() {
    document.getElementById('aiSearchResult').style.display = 'none';
    document.getElementById('aiSearchInput').value = '';
    refreshTableData();
});

// ===== Preview with zoom/pan =====
let pvScale = 1, pvX = 0, pvY = 0, pvDrag = false, pvStartX = 0, pvStartY = 0, pvImg = null;

function pvApply() {
    if (!pvImg) return;
    pvImg.style.transform = 'translate(-50%,-50%) translate('+pvX+'px,'+pvY+'px) scale('+pvScale+')';
    document.getElementById('zoomLevel').textContent = Math.round(pvScale*100)+'%';
}

function openPreview(url, ext) {
    const content = document.getElementById('previewContent');
    const overlay = document.getElementById('previewOverlay');
    const toolbar = document.getElementById('previewToolbar');
    if (ext === 'pdf') {
        content.innerHTML = '<iframe src="'+url+'"></iframe>';
        toolbar.style.display = 'none';
        content.style.cursor = 'default';
    } else {
        content.innerHTML = '';
        const img = new Image();
        img.alt = 'Náhled dokladu';
        img.onload = function() {
            content.appendChild(img);
            pvImg = img;
            pvScale = 1; pvX = 0; pvY = 0;
            previewZoomFit();
            toolbar.style.display = 'flex';
        };
        img.src = url;
        content.style.cursor = 'grab';
    }
    overlay.classList.add('active');
}

function closePreview() {
    document.getElementById('previewOverlay').classList.remove('active');
    document.getElementById('previewContent').innerHTML = '';
    document.getElementById('previewToolbar').style.display = 'none';
    pvImg = null;
}

function previewZoom(dir) {
    if (!pvImg) return;
    const steps = [0.1, 0.15, 0.2, 0.25, 0.33, 0.5, 0.67, 0.75, 1, 1.25, 1.5, 2, 3, 4, 5];
    let idx = steps.findIndex(s => s >= pvScale - 0.01);
    if (idx === -1) idx = steps.length - 1;
    idx = Math.max(0, Math.min(steps.length - 1, idx + dir));
    pvScale = steps[idx];
    pvApply();
}

function previewZoomFit() {
    if (!pvImg) return;
    const cont = document.getElementById('previewContent');
    const sw = cont.clientWidth / pvImg.naturalWidth;
    const sh = cont.clientHeight / pvImg.naturalHeight;
    pvScale = Math.min(sw, sh, 1);
    pvX = 0; pvY = 0;
    pvApply();
}

function previewZoomReal() {
    if (!pvImg) return;
    pvScale = 1; pvX = 0; pvY = 0;
    pvApply();
}

// Mouse wheel zoom
document.getElementById('previewContent')?.addEventListener('wheel', function(e) {
    if (!pvImg) return;
    e.preventDefault();
    previewZoom(e.deltaY < 0 ? 1 : -1);
}, {passive: false});

// Drag to pan
document.getElementById('previewContent')?.addEventListener('mousedown', function(e) {
    if (!pvImg || e.button !== 0) return;
    pvDrag = true; pvStartX = e.clientX - pvX; pvStartY = e.clientY - pvY;
    this.classList.add('dragging');
    e.preventDefault();
});
document.addEventListener('mousemove', function(e) {
    if (!pvDrag) return;
    pvX = e.clientX - pvStartX; pvY = e.clientY - pvStartY;
    pvApply();
});
document.addEventListener('mouseup', function() {
    if (pvDrag) {
        pvDrag = false;
        document.getElementById('previewContent')?.classList.remove('dragging');
    }
});

// Double-click to toggle fit/100%
document.getElementById('previewContent')?.addEventListener('dblclick', function() {
    if (!pvImg) return;
    if (pvScale < 0.99) { previewZoomReal(); } else { previewZoomFit(); }
});

document.getElementById('previewOverlay')?.addEventListener('click', function(e) { if (e.target === this) closePreview(); });
document.addEventListener('keydown', e => { if (e.key === 'Escape') closePreview(); });

// ===== Init =====
buildColPanel();
renderTable();

// ===== Flash → upload panel =====
(function() {
    const flashMsg = @json(session('flash', ''));
    if (!flashMsg) return;
    const panel = document.getElementById('uploadPanel');
    const list = document.getElementById('uploadList');
    if (!panel || !list) return;

    const item = document.createElement('div');
    item.className = 'upload-item status-info';
    item.innerHTML =
        '<span class="upload-item-icon">&#8505;</span>' +
        '<span class="upload-item-name">' + flashMsg + '</span>';
    addDismissBtn(item);
    list.appendChild(item);
    panel.classList.add('active');
    document.getElementById('uploadPanelTitle').textContent = '';
    document.getElementById('uploadPanelProgress').textContent = '';

    setTimeout(() => {
        item.style.opacity = '0';
        setTimeout(() => { item.remove(); checkUploadPanelEmpty(); }, 500);
    }, 8000);
})();
</script>
@endsection
