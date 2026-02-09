@extends('layouts.app')

@section('title', 'Nahrát doklad')

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
    .file-name { margin: 1rem 0; padding: 0.75rem; background: #eaf2f8; border-radius: 6px; display: none; }
    .btn { display: inline-block; padding: 0.75rem 2rem; background: #2c3e50; color: white; border: none; border-radius: 6px; font-size: 1rem; cursor: pointer; }
    .btn:hover { background: #34495e; }
    .btn:disabled { background: #bdc3c7; cursor: not-allowed; }
    .error { background: #fce4e4; border: 1px solid #e74c3c; color: #c0392b; padding: 1rem; border-radius: 6px; margin-bottom: 1rem; }
</style>
@endsection

@section('content')
<div class="card">
    <h2 style="margin-bottom: 1.5rem;">Nahrát doklad</h2>

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
            <p>Přetáhněte soubor sem nebo klikněte pro výběr</p>
            <p class="formats">PDF, JPG, PNG (max 10 MB)</p>
        </div>

        <input type="file" name="document" id="fileInput" accept=".pdf,.jpg,.jpeg,.png" style="display: none;">

        <div class="file-name" id="fileName"></div>

        <button type="submit" class="btn" id="submitBtn" disabled>Zpracovat doklad</button>
    </form>
</div>
@endsection

@section('scripts')
<script>
    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('fileInput');
    const fileName = document.getElementById('fileName');
    const submitBtn = document.getElementById('submitBtn');

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
        if (e.dataTransfer.files.length) {
            fileInput.files = e.dataTransfer.files;
            updateFileName();
        }
    });

    fileInput.addEventListener('change', updateFileName);

    function updateFileName() {
        if (fileInput.files.length) {
            const file = fileInput.files[0];
            const sizeMB = (file.size / 1024 / 1024).toFixed(2);
            fileName.textContent = file.name + ' (' + sizeMB + ' MB)';
            fileName.style.display = 'block';
            submitBtn.disabled = false;
        }
    }
</script>
@endsection
