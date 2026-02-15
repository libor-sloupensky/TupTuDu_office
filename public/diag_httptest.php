<?php
/**
 * Secure HTTP pipeline diagnostic - goes through the SAME Laravel HTTP kernel as index.php
 * but with timing at every stage. Protected by secret token.
 *
 * Usage:
 *   GET  /diag_httptest.php?token=SECRET           → shows upload form + ping test
 *   POST /diag_httptest.php?token=SECRET&mode=ping  → just measures HTTP kernel overhead
 *   POST /diag_httptest.php?token=SECRET&mode=upload → full upload pipeline (same as /upload)
 */

// === ABSOLUTE FIRST THING: record when PHP received the request ===
$phpStartTime = microtime(true);
$phpStartDate = date('H:i:s') . '.' . sprintf('%03d', ($phpStartTime * 1000) % 1000);

$SECRET = 'tuptudu-diag-2026-xK9m';

// Token check BEFORE any Laravel bootstrap
if (($_GET['token'] ?? $_POST['token'] ?? '') !== $SECRET) {
    http_response_code(403);
    die('Forbidden');
}

set_time_limit(300);
error_reporting(E_ALL);

// Logging helper - writes to a log file accessible via diag_timing.php
$logFile = null; // set after basePath detected
$logLines = [];
$tLog = function(string $msg) use ($phpStartTime, &$logLines, &$logFile) {
    $elapsed = round((microtime(true) - $phpStartTime) * 1000);
    $line = date('H:i:s') . " +{$elapsed}ms $msg";
    $logLines[] = $line;
    if ($logFile) {
        @file_put_contents($logFile, "$line\n", FILE_APPEND);
    }
};

$tLog("=== PHP RECEIVED REQUEST ===");
$tLog("Method: {$_SERVER['REQUEST_METHOD']}");
$tLog("Mode: " . ($_GET['mode'] ?? $_POST['mode'] ?? 'page'));
$tLog("Files: " . (empty($_FILES) ? 'none' : implode(', ', array_map(function($f) {
    return $f['name'] . ' (' . round($f['size']/1024, 1) . 'KB)';
}, $_FILES))));
$tLog("Content-Length: " . ($_SERVER['CONTENT_LENGTH'] ?? '?'));

// === GET: show test page ===
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: text/html; charset=utf-8');
    $token = htmlspecialchars($SECRET);
    echo <<<HTML
<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>HTTP Pipeline Test</title>
<style>
body { font-family: monospace; max-width: 800px; margin: 20px auto; background: #1a1a2e; color: #e0e0e0; }
h2 { color: #64ffda; }
.result { background: #0d1117; border: 1px solid #333; padding: 15px; margin: 10px 0; white-space: pre-wrap; font-size: 13px; }
button { background: #64ffda; color: #1a1a2e; border: none; padding: 10px 20px; cursor: pointer; font-weight: bold; margin: 5px; }
button:hover { background: #4dd9b5; }
.timing { color: #ffd700; }
.ok { color: #64ffda; }
.err { color: #ff6b6b; }
</style>
</head><body>
<h2>HTTP Pipeline Diagnostic</h2>
<p>PHP start: {$phpStartDate}</p>

<h3>1. Ping Test (HTTP kernel overhead only)</h3>
<button onclick="runPing()">Run Ping</button>
<div id="ping-result" class="result">Click to test...</div>

<h3>2. Upload Test (full pipeline, same as /upload)</h3>
<form id="upload-form">
    <input type="file" id="upload-file" name="document" accept=".pdf,.jpg,.jpeg,.png">
    <button type="submit">Upload & Process</button>
</form>
<div id="upload-result" class="result">Select a file and upload...</div>

<h3>3. Previous Log</h3>
<div id="log-result" class="result">Loading...</div>

<script>
const TOKEN = '{$token}';

async function runPing() {
    const el = document.getElementById('ping-result');
    const start = Date.now();
    el.textContent = 'Sending ping...';
    try {
        const form = new FormData();
        form.append('token', TOKEN);
        const r = await fetch('diag_httptest.php?token=' + TOKEN + '&mode=ping', {
            method: 'POST', body: form
        });
        const text = await r.text();
        const elapsed = Date.now() - start;
        el.innerHTML = '<span class="timing">Browser→Server roundtrip: ' + elapsed + 'ms</span>\\n\\n' + text;
    } catch(e) {
        el.innerHTML = '<span class="err">ERROR: ' + e.message + '</span>';
    }
}

document.getElementById('upload-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    const el = document.getElementById('upload-result');
    const file = document.getElementById('upload-file').files[0];
    if (!file) { el.textContent = 'No file selected'; return; }

    const start = Date.now();
    el.textContent = 'Uploading ' + file.name + ' (' + Math.round(file.size/1024) + 'KB)...';

    try {
        const form = new FormData();
        form.append('token', TOKEN);
        form.append('documents[]', file);
        const r = await fetch('diag_httptest.php?token=' + TOKEN + '&mode=upload', {
            method: 'POST', body: form
        });
        const text = await r.text();
        const elapsed = Date.now() - start;
        el.innerHTML = '<span class="timing">Browser total: ' + elapsed + 'ms (' + (elapsed/1000).toFixed(1) + 's)</span>\\n\\n' + text;
    } catch(e) {
        el.innerHTML = '<span class="err">ERROR: ' + e.message + ' (after ' + ((Date.now()-start)/1000).toFixed(1) + 's)</span>';
    }
});

// Load previous log
fetch('diag_timing.php').then(r => r.text()).then(t => {
    document.getElementById('log-result').textContent = t;
});
</script>
</body></html>
HTML;
    exit;
}

// === POST handlers ===
header('Content-Type: text/plain; charset=utf-8');

// Detect base path (same as index.php)
if (file_exists(__DIR__.'/../laravel-office/vendor/autoload.php')) {
    $basePath = __DIR__.'/../laravel-office';
} elseif (file_exists(__DIR__.'/../../laravel-office/vendor/autoload.php')) {
    $basePath = __DIR__.'/../../laravel-office';
} else {
    $basePath = __DIR__.'/..';
}

// Now set log file path
$logFile = $basePath . '/storage/logs/upload_timing.log';
$tLog("basePath: $basePath");

// === Step 1: Autoload ===
$tLog("Step 1: require autoload");
require $basePath.'/vendor/autoload.php';
$tLog("Step 1 done: autoload loaded");

// === Step 2: Create app ===
$tLog("Step 2: bootstrap app");
$app = require_once $basePath.'/bootstrap/app.php';
$tLog("Step 2 done: app created");

// === Step 3: Bootstrap kernel ===
$tLog("Step 3: bootstrap HTTP kernel");
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
$tLog("Step 3 done: kernel created");

$mode = $_GET['mode'] ?? $_POST['mode'] ?? 'ping';

if ($mode === 'ping') {
    // === PING: just measure kernel overhead ===
    $tLog("PING mode: bootstrapping only");

    // Bootstrap the app (middleware, providers, etc.)
    $request = \Illuminate\Http\Request::capture();
    $tLog("Request captured, URI: " . $request->getRequestUri());

    // Just boot the app without handling a route
    $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $tLog("App bootstrapped via Console kernel");

    // Test DB connection
    $dbStart = microtime(true);
    $firma = \App\Models\Firma::first();
    $dbMs = round((microtime(true) - $dbStart) * 1000);
    $tLog("DB query: {$dbMs}ms - firma={$firma->nazev}");

    // Test auth
    $tLog("Session driver: " . config('session.driver'));
    $tLog("Cache store: " . config('cache.default'));

    echo "=== PING RESULT ===\n";
    echo "PHP received at: $phpStartDate\n";
    echo "Total elapsed: " . round((microtime(true) - $phpStartTime) * 1000) . "ms\n\n";
    echo "Breakdown:\n";
    foreach ($logLines as $line) echo "  $line\n";
    exit;
}

if ($mode === 'upload') {
    // === UPLOAD: full pipeline, same as InvoiceController::store() ===
    $tLog("UPLOAD mode: full pipeline");

    // Bootstrap via Console kernel (same as diag_upload.php which works fast)
    $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $tLog("App bootstrapped");

    if (empty($_FILES['documents']) && empty($_FILES['document'])) {
        echo "ERROR: No file uploaded\n";
        echo "FILES: " . print_r($_FILES, true) . "\n";
        exit;
    }

    // Handle both documents[] (multi) and document (single)
    $files = [];
    if (!empty($_FILES['documents'])) {
        // documents[] array format
        if (is_array($_FILES['documents']['name'])) {
            for ($i = 0; $i < count($_FILES['documents']['name']); $i++) {
                $files[] = [
                    'name' => $_FILES['documents']['name'][$i],
                    'tmp_name' => $_FILES['documents']['tmp_name'][$i],
                    'size' => $_FILES['documents']['size'][$i],
                    'error' => $_FILES['documents']['error'][$i],
                ];
            }
        } else {
            $files[] = $_FILES['documents'];
        }
    } else {
        $files[] = $_FILES['document'];
    }

    echo "=== UPLOAD PIPELINE TEST (via HTTP entry point) ===\n";
    echo "PHP received at: $phpStartDate\n";
    echo "Files: " . count($files) . "\n\n";

    foreach ($files as $i => $file) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            echo "File #{$i}: upload error {$file['error']}\n";
            continue;
        }

        echo "--- File: {$file['name']} (" . round($file['size']/1024, 1) . "KB) ---\n";
        $fileStart = microtime(true);

        // Step A: Get firma
        $tLog("File {$file['name']}: get firma");
        $t = microtime(true);
        $firma = \App\Models\Firma::first();
        $firmaMs = round((microtime(true) - $t) * 1000);
        $tLog("File {$file['name']}: firma={$firma->nazev} ({$firmaMs}ms)");
        echo "Firma: {$firma->nazev} (ICO: {$firma->ico}) [{$firmaMs}ms]\n";

        // Step B: Hash
        $t = microtime(true);
        $hash = hash_file('sha256', $file['tmp_name']);
        $hashMs = round((microtime(true) - $t) * 1000);
        echo "Hash: " . substr($hash, 0, 16) . "... [{$hashMs}ms]\n";

        // Step C: Duplicate check
        $t = microtime(true);
        $processor = new \App\Services\DokladProcessor();
        $existing = $processor->isDuplicate($hash, $firma->ico);
        $dupMs = round((microtime(true) - $t) * 1000);
        echo "Duplicate: " . ($existing ? "YES (#{$existing->id})" : "NO") . " [{$dupMs}ms]\n";

        if ($existing) {
            echo "Skipping (duplicate).\n\n";
            continue;
        }

        // Step D: Full pipeline
        $tLog("File {$file['name']}: starting DokladProcessor::process()");
        $t = microtime(true);
        $uniqueHash = hash('sha256', $hash . '_httptest_' . time());
        try {
            $doklady = $processor->process($file['tmp_name'], $file['name'], $firma, $uniqueHash, 'upload');
            $processMs = round((microtime(true) - $t) * 1000);
            $tLog("File {$file['name']}: process done ({$processMs}ms), doklady=" . count($doklady));
            echo "Process: OK [{$processMs}ms] - " . count($doklady) . " doklad(y)\n";

            foreach ($doklady as $d) {
                echo "  #{$d->id} stav={$d->stav} typ={$d->typ_dokladu} dodavatel={$d->dodavatel_nazev} castka={$d->castka_celkem}\n";
            }

            // Cleanup test records
            foreach ($doklady as $d) {
                if ($d->cesta_souboru) {
                    try { \Illuminate\Support\Facades\Storage::disk('s3')->delete($d->cesta_souboru); } catch (\Throwable $e) {}
                }
                $d->delete();
            }
            echo "Cleanup: done\n";
        } catch (\Throwable $e) {
            $processMs = round((microtime(true) - $t) * 1000);
            echo "Process: ERROR [{$processMs}ms] - {$e->getMessage()}\n";
            echo "  at {$e->getFile()}:{$e->getLine()}\n";
            $tLog("File {$file['name']}: ERROR - {$e->getMessage()}");
        }

        $totalFileMs = round((microtime(true) - $fileStart) * 1000);
        echo "File total: {$totalFileMs}ms (" . round($totalFileMs/1000, 1) . "s)\n\n";
    }

    $totalMs = round((microtime(true) - $phpStartTime) * 1000);
    echo "=== TOTAL: {$totalMs}ms (" . round($totalMs/1000, 1) . "s) ===\n\n";
    echo "Timeline:\n";
    foreach ($logLines as $line) echo "  $line\n";
    exit;
}

echo "Unknown mode: $mode\n";
