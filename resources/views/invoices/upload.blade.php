@extends('layouts.app')

@section('title', 'Nahrát doklady')

@section('styles')
<style>
    .upload-zone {
        border: 3px dashed #bdc3c7;
        border-radius: 12px;
        padding: 3rem;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s;
        margin-bottom: 1.5rem;
    }
    .upload-zone:hover, .upload-zone.dragover {
        border-color: #3498db;
        background: #ebf5fb;
    }
    .upload-zone .icon { font-size: 3rem; margin-bottom: 1rem; }
    .upload-zone p { color: #7f8c8d; margin-bottom: 0.5rem; }
    .upload-zone .formats { font-size: 0.85rem; color: #95a5a6; }
    .file-list { margin: 1rem 0; }
    .file-item {
        display: flex; align-items: center; justify-content: space-between;
        padding: 0.5rem 0.75rem; background: #eaf2f8; border-radius: 6px;
        margin-bottom: 0.4rem; font-size: 0.9rem;
    }
    .file-item .file-info { display: flex; align-items: center; gap: 0.5rem; }
    .file-item .file-size { color: #7f8c8d; font-size: 0.8rem; }
    .file-item .remove-btn {
        background: none; border: none; color: #e74c3c; cursor: pointer;
        font-size: 1.1rem; padding: 0 0.3rem; line-height: 1;
    }
    .file-item .remove-btn:hover { color: #c0392b; }
    .file-count { font-size: 0.85rem; color: #555; margin-bottom: 0.75rem; }
    .btn { display: inline-block; padding: 0.75rem 2rem; background: #2c3e50; color: white; border: none; border-radius: 6px; font-size: 1rem; cursor: pointer; }
    .btn:hover { background: #34495e; }
    .btn:disabled { background: #bdc3c7; cursor: not-allowed; }
    .error { background: #fce4e4; border: 1px solid #e74c3c; color: #c0392b; padding: 1rem; border-radius: 6px; margin-bottom: 1rem; }
    .processing { display: none; text-align: center; padding: 2rem; color: #555; }
    .processing .spinner { display: inline-block; width: 24px; height: 24px; border: 3px solid #bdc3c7; border-top-color: #3498db; border-radius: 50%; animation: spin 0.8s linear infinite; margin-right: 0.5rem; vertical-align: middle; }
    @keyframes spin { to { transform: rotate(360deg); } }
</style>
@endsection

@section('content')
<div class="card">
    <h2 style="margin-bottom: 1.5rem;">Nahrát doklady</h2>

    @if ($errors->any())
        <div class="error">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <form action="{{ route('invoices.store') }}" method="POST" enctype="multipart/form-data" id="uploadForm">
        @csrf

        <div class="upload-zone" id="dropZone">
            <div class="icon">📄</div>
            <p>Přetáhněte soubory sem nebo klikněte pro výběr</p>
            <p class="formats">PDF, JPG, PNG (max 10 MB na soubor)</p>
        </div>

        <input type="file" name="documents[]" id="fileInput" accept=".pdf,.jpg,.jpeg,.png" multiple style="display: none;">

        <div class="file-list" id="fileList"></div>
        <div class="file-count" id="fileCount" style="display: none;"></div>

        <div class="processing" id="processing">
            <span class="spinner"></span> Zpracovávám doklady, prosím čekejte...
        </div>

        <button type="submit" class="btn" id="submitBtn" disabled>Zpracovat doklady</button>
    </form>
</div>
@endsection

@section('scripts')
<script>
    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('fileInput');
    const fileList = document.getElementById('fileList');
    const fileCount = document.getElementById('fileCount');
    const submitBtn = document.getElementById('submitBtn');
    const form = document.getElementById('uploadForm');
    const processing = document.getElementById('processing');

    let selectedFiles = [];

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
        addFiles(e.dataTransfer.files);
    });

    fileInput.addEventListener('change', () => {
        addFiles(fileInput.files);
        fileInput.value = '';
    });

    function addFiles(files) {
        const allowed = ['application/pdf', 'image/jpeg', 'image/png'];
        for (const file of files) {
            if (!allowed.includes(file.type)) continue;
            if (file.size > 10 * 1024 * 1024) continue;
            if (selectedFiles.some(f => f.name === file.name && f.size === file.size)) continue;
            selectedFiles.push(file);
        }
        renderFileList();
    }

    function removeFile(index) {
        selectedFiles.splice(index, 1);
        renderFileList();
    }

    function renderFileList() {
        fileList.innerHTML = '';
        selectedFiles.forEach((file, i) => {
            const sizeMB = (file.size / 1024 / 1024).toFixed(2);
            const div = document.createElement('div');
            div.className = 'file-item';
            div.innerHTML = `
                <span class="file-info">
                    <span>${file.name}</span>
                    <span class="file-size">(${sizeMB} MB)</span>
                </span>
                <button type="button" class="remove-btn" onclick="removeFile(${i})">&times;</button>
            `;
            fileList.appendChild(div);
        });

        if (selectedFiles.length > 0) {
            fileCount.textContent = `Vybráno: ${selectedFiles.length} ${selectedFiles.length === 1 ? 'soubor' : selectedFiles.length < 5 ? 'soubory' : 'souborů'}`;
            fileCount.style.display = 'block';
            submitBtn.disabled = false;
            submitBtn.textContent = selectedFiles.length === 1 ? 'Zpracovat doklad' : `Zpracovat ${selectedFiles.length} dokladů`;
        } else {
            fileCount.style.display = 'none';
            submitBtn.disabled = true;
            submitBtn.textContent = 'Zpracovat doklady';
        }
    }

    form.addEventListener('submit', (e) => {
        e.preventDefault();
        if (selectedFiles.length === 0) return;

        // Build FormData with selected files
        const formData = new FormData(form);
        // Remove any existing documents[] entries
        formData.delete('documents[]');
        selectedFiles.forEach(file => formData.append('documents[]', file));

        // Show processing state
        submitBtn.disabled = true;
        dropZone.style.display = 'none';
        fileList.style.display = 'none';
        fileCount.style.display = 'none';
        submitBtn.style.display = 'none';
        processing.style.display = 'block';

        // Submit via fetch then redirect
        fetch(form.action, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
            redirect: 'follow',
        }).then(response => {
            if (response.redirected) {
                window.location.href = response.url;
            } else {
                return response.text().then(html => {
                    document.open();
                    document.write(html);
                    document.close();
                });
            }
        }).catch(() => {
            processing.style.display = 'none';
            submitBtn.style.display = 'inline-block';
            dropZone.style.display = 'block';
            fileList.style.display = 'block';
            fileCount.style.display = 'block';
            submitBtn.disabled = false;
            alert('Chyba při odesílání. Zkuste to znovu.');
        });
    });
</script>
@endsection
