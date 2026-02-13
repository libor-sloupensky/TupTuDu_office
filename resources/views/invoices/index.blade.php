@extends('layouts.app')

@section('title', 'Doklady')

@section('styles')
<style>
    .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
    .page-header h2 { margin: 0; }

    .upload-zone {
        border: 2px dashed #bdc3c7;
        border-radius: 8px;
        padding: 1.2rem;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s;
        margin-bottom: 1.5rem;
    }
    .upload-zone:hover, .upload-zone.dragover {
        border-color: #3498db;
        background: #ebf5fb;
    }
    .upload-zone p { color: #7f8c8d; margin: 0; font-size: 0.9rem; }
    .upload-zone .formats { font-size: 0.8rem; color: #95a5a6; margin-top: 0.3rem; }
    .upload-processing {
        display: none;
        padding: 1rem;
        text-align: center;
        color: #555;
        background: #eaf2f8;
        border-radius: 8px;
        margin-bottom: 1.5rem;
    }
    .upload-processing .spinner {
        display: inline-block; width: 18px; height: 18px;
        border: 2px solid #bdc3c7; border-top-color: #3498db;
        border-radius: 50%; animation: spin 0.8s linear infinite;
        margin-right: 0.5rem; vertical-align: middle;
    }
    @keyframes spin { to { transform: rotate(360deg); } }

    .toast-container { margin-bottom: 1rem; }
    .toast {
        padding: 0.6rem 1rem;
        border-radius: 6px;
        margin-bottom: 0.4rem;
        font-size: 0.85rem;
        transition: opacity 0.5s;
    }
    .toast-ok { background: #d4edda; color: #155724; }
    .toast-error { background: #f8d7da; color: #721c24; }
    .toast-duplicate { background: #fff3cd; color: #856404; }
    .toast-info { background: #d4edda; color: #155724; }

    .toolbar { display: flex; gap: 0.75rem; align-items: center; margin-bottom: 1rem; }
    .search-input {
        flex: 1;
        padding: 0.4rem 0.75rem;
        border: 1px solid #d0d8e0;
        border-radius: 6px;
        font-size: 0.85rem;
        outline: none;
    }
    .search-input:focus { border-color: #3498db; }

    .doklady-table { width: 100%; border-collapse: collapse; }
    .doklady-table th { text-align: left; padding: 0.6rem 0.75rem; background: #f0f4f8; border-bottom: 2px solid #d0d8e0; font-size: 0.8rem; color: #555; font-weight: 600; white-space: nowrap; }
    .doklady-table td { padding: 0.6rem 0.75rem; border-bottom: 1px solid #e8ecf0; font-size: 0.9rem; }
    .doklady-table tr:hover { background: #f8fafb; }
    .doklady-table a { color: #3498db; text-decoration: none; }
    .doklady-table a:hover { text-decoration: underline; }
    .sort-link { color: #555; text-decoration: none; }
    .sort-link:hover { color: #2c3e50; text-decoration: none; }
    .sort-arrow { font-size: 0.7rem; margin-left: 0.2rem; }
    .stav-dokonceno { color: #27ae60; }
    .stav-chyba { color: #e74c3c; font-weight: 600; }
    .stav-zpracovava { color: #f39c12; font-weight: 600; }
    .amount { text-align: right; font-weight: 600; }
    .date-sub { display: block; font-size: 0.75rem; color: #95a5a6; }
    .empty-state { text-align: center; padding: 2rem; color: #999; }
    .warning-msg { background: #fff3cd; color: #856404; padding: 0.75rem 1rem; border-radius: 6px; margin-bottom: 1rem; }
    .month-downloads { margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid #e0e0e0; }
    .month-downloads h3 { font-size: 0.95rem; color: #555; margin-bottom: 0.75rem; }
    .month-list { display: flex; flex-wrap: wrap; gap: 0.5rem; }
    .month-link { display: inline-block; padding: 0.35rem 0.75rem; background: #eaf2f8; border-radius: 6px; color: #2c3e50; text-decoration: none; font-size: 0.85rem; }
    .month-link:hover { background: #d4e6f1; }
    .badge-dup { display: inline-block; padding: 0.1rem 0.4rem; border-radius: 4px; background: #fff3cd; color: #856404; font-size: 0.7rem; font-weight: 600; margin-left: 0.3rem; vertical-align: middle; }
    .btn-del-sm { background: none; border: none; color: #bdc3c7; cursor: pointer; font-size: 0.85rem; padding: 0.2rem 0.4rem; line-height: 1; }
    .btn-del-sm:hover { color: #e74c3c; }
    .btn-preview { color: #95a5a6; text-decoration: none; margin-right: 0.4rem; font-size: 0.85rem; vertical-align: middle; }
    .btn-preview:hover { color: #3498db; text-decoration: none; }

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
        <div class="empty-state">
            <p>Zatím žádné doklady.</p>
        </div>
    @else
        <div class="toolbar">
            <form method="GET" action="{{ route('doklady.index') }}" style="flex:1; display:flex;">
                <input type="hidden" name="sort" value="{{ $sort }}">
                <input type="hidden" name="dir" value="{{ $dir }}">
                <input type="text" name="q" value="{{ $q }}" class="search-input" placeholder="Hledat (dodavatel, IČO, číslo dokladu...)">
            </form>
        </div>

        @if ($doklady->isEmpty())
            <div class="empty-state">
                <p>Žádné doklady neodpovídají hledání.</p>
            </div>
        @else
        @php
            $sortUrl = function($col) use ($sort, $dir, $q) {
                $newDir = ($sort === $col && $dir === 'desc') ? 'asc' : 'desc';
                $params = ['sort' => $col, 'dir' => $newDir];
                if ($q) $params['q'] = $q;
                return route('doklady.index', $params);
            };
            $arrow = function($col) use ($sort, $dir) {
                if ($sort !== $col) return '';
                return '<span class="sort-arrow">' . ($dir === 'asc' ? '&#9650;' : '&#9660;') . '</span>';
            };
        @endphp
        <table class="doklady-table">
            <thead>
                <tr>
                    <th><a href="{{ $sortUrl('created_at') }}" class="sort-link">Nahráno{!! $arrow('created_at') !!}</a></th>
                    <th><a href="{{ $sortUrl('datum_vystaveni') }}" class="sort-link">Vystavení{!! $arrow('datum_vystaveni') !!}</a></th>
                    <th>Číslo</th>
                    <th>Dodavatel</th>
                    <th style="text-align: right">Částka</th>
                    <th>Stav</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($doklady as $d)
                <tr>
                    <td>{{ $d->created_at->format('d.m.Y') }}<span class="date-sub">{{ $d->created_at->format('H:i') }}</span></td>
                    <td>{{ $d->datum_vystaveni ? $d->datum_vystaveni->format('d.m.Y') : '-' }}</td>
                    <td>
                        @if ($d->cesta_souboru)
                            <a href="#" class="btn-preview" title="Náhled" onclick="openPreview('{{ route('doklady.preview', $d) }}', '{{ strtolower(pathinfo($d->nazev_souboru, PATHINFO_EXTENSION)) }}'); return false;">&#128065;</a>
                        @endif
                        <a href="{{ route('doklady.show', $d) }}">{{ $d->cislo_dokladu ?: $d->nazev_souboru }}</a>
                        @if ($d->duplicita_id)<span class="badge-dup" title="Možná duplicita">DUP</span>@endif
                    </td>
                    <td>{{ $d->dodavatel_nazev ?: '-' }}</td>
                    <td class="amount">
                        @if ($d->castka_celkem)
                            {{ number_format((float)$d->castka_celkem, 2, ',', ' ') }} {{ $d->mena }}
                        @else
                            -
                        @endif
                    </td>
                    <td>
                        @if ($d->stav === 'dokonceno')
                            <span class="stav-dokonceno" title="Dokončeno">&#10003;</span>
                        @elseif ($d->stav === 'chyba')
                            <span class="stav-chyba">Chyba</span>
                        @else
                            <span class="stav-zpracovava">{{ $d->stav }}</span>
                        @endif
                    </td>
                    <td>
                        <form action="{{ route('doklady.destroy', $d) }}" method="POST" style="display:inline" onsubmit="return confirm('Smazat doklad {{ $d->cislo_dokladu ?: $d->nazev_souboru }}?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-del-sm" title="Smazat">&times;</button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>

        @php
            $mesice = $doklady
                ->filter(fn($d) => $d->datum_vystaveni)
                ->map(fn($d) => $d->datum_vystaveni->format('Y-m'))
                ->unique()
                ->sort()
                ->reverse();
        @endphp

        @if ($mesice->isNotEmpty())
        <div class="month-downloads">
            <h3>Stáhnout doklady za měsíc (ZIP)</h3>
            <div class="month-list">
                @foreach ($mesice as $m)
                    <a href="{{ route('doklady.downloadMonth', $m) }}" class="month-link">{{ \Carbon\Carbon::parse($m . '-01')->translatedFormat('F Y') }}</a>
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
    // Auto-hide toasts after 6 seconds
    function autoHideToasts() {
        document.querySelectorAll('.toast[data-auto-hide]').forEach(function(toast) {
            setTimeout(function() {
                toast.style.opacity = '0';
                setTimeout(function() { toast.remove(); }, 500);
            }, 6000);
        });
    }
    autoHideToasts();

    function addToast(message, type) {
        const container = document.getElementById('toastContainer');
        const div = document.createElement('div');
        div.className = 'toast toast-' + type;
        div.setAttribute('data-auto-hide', '');
        div.textContent = message;
        container.appendChild(div);
        setTimeout(function() {
            div.style.opacity = '0';
            setTimeout(function() { div.remove(); }, 500);
        }, 6000);
    }

    // Upload
    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('fileInput');
    const uploadProcessing = document.getElementById('uploadProcessing');
    const uploadStatus = document.getElementById('uploadStatus');

    if (dropZone) {
        dropZone.addEventListener('click', () => fileInput.click());

        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.classList.add('dragover');
        });

        dropZone.addEventListener('dragleave', () => {
            dropZone.classList.remove('dragover');
        });

        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('dragover');
            uploadFiles(e.dataTransfer.files);
        });

        fileInput.addEventListener('change', () => {
            uploadFiles(fileInput.files);
            fileInput.value = '';
        });
    }

    function uploadFiles(files) {
        const allowed = ['application/pdf', 'image/jpeg', 'image/png'];
        const validFiles = [];
        for (const file of files) {
            if (!allowed.includes(file.type)) continue;
            if (file.size > 10 * 1024 * 1024) continue;
            validFiles.push(file);
        }

        if (validFiles.length === 0) return;

        dropZone.style.display = 'none';
        uploadProcessing.style.display = 'block';
        const noun = validFiles.length === 1 ? 'doklad' : (validFiles.length < 5 ? 'doklady' : 'dokladů');
        uploadStatus.textContent = `Zpracovávám ${validFiles.length} ${noun}...`;

        const formData = new FormData();
        formData.append('_token', '{{ csrf_token() }}');
        validFiles.forEach(file => formData.append('documents[]', file));

        fetch('{{ route("invoices.store") }}', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
        }).then(response => response.json()).then(results => {
            uploadProcessing.style.display = 'none';
            dropZone.style.display = 'block';

            results.forEach(r => addToast(r.message, r.status));

            // Reload table after short delay so toasts are visible
            setTimeout(() => window.location.href = '{{ route("doklady.index") }}', 1500);
        }).catch(() => {
            dropZone.style.display = 'block';
            uploadProcessing.style.display = 'none';
            addToast('Chyba při odesílání. Zkuste to znovu.', 'error');
        });
    }

    // Search on Enter
    document.querySelector('.search-input')?.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') this.closest('form').submit();
    });

    // Preview
    function openPreview(url, ext) {
        const content = document.getElementById('previewContent');
        const overlay = document.getElementById('previewOverlay');

        if (ext === 'pdf') {
            content.innerHTML = '<iframe src="' + url + '"></iframe>';
        } else {
            content.innerHTML = '<img src="' + url + '" alt="Náhled dokladu">';
        }

        overlay.classList.add('active');
    }

    function closePreview() {
        const overlay = document.getElementById('previewOverlay');
        const content = document.getElementById('previewContent');
        overlay.classList.remove('active');
        content.innerHTML = '';
    }

    document.getElementById('previewOverlay').addEventListener('click', function(e) {
        if (e.target === this) closePreview();
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closePreview();
    });
</script>
@endsection
