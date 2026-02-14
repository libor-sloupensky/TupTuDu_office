<?php
/**
 * Diagnostika uploadu dokladů - dočasný soubor, po otestování smazat!
 * ?mode=upload  - simuluje kompletní upload procesor (přiložit soubor přes formulář)
 * bez parametru - základní diagnostika
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Detekce base path (stejně jako index.php)
if (file_exists(__DIR__.'/../laravel-office/vendor/autoload.php')) {
    $basePath = __DIR__.'/../laravel-office';
} elseif (file_exists(__DIR__.'/../../laravel-office/vendor/autoload.php')) {
    $basePath = __DIR__.'/../../laravel-office';
} else {
    $basePath = __DIR__.'/..';
}

require $basePath . '/vendor/autoload.php';
$app = require_once $basePath . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$mode = $_GET['mode'] ?? 'info';

// === LOG MODE - čtení upload debug logu ===
if ($mode === 'log') {
    header('Content-Type: text/plain; charset=utf-8');
    $logFile = $basePath . '/storage/logs/upload_debug.log';
    if (file_exists($logFile)) {
        echo file_get_contents($logFile);
    } else {
        echo "(žádný log - upload_debug.log neexistuje)";
    }
    exit;
}

// === CLEAR LOG MODE ===
if ($mode === 'clearlog') {
    $logFile = $basePath . '/storage/logs/upload_debug.log';
    if (file_exists($logFile)) {
        unlink($logFile);
        echo "Log smazán.";
    } else {
        echo "Log neexistuje.";
    }
    exit;
}

// === PROCESSOR TEST MODE - volá DokladProcessor přímo ===
if ($mode === 'processor' && $_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['testfile'])) {
    header('Content-Type: application/json; charset=utf-8');
    $log = [];
    $log[] = 'Start: ' . date('H:i:s');

    try {
        $file = $_FILES['testfile'];
        $log[] = "Soubor: {$file['name']} ({$file['size']} bytes)";

        if ($file['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['ok' => false, 'log' => $log, 'error' => 'Upload error: ' . $file['error']]);
            exit;
        }

        // Potřebujeme firmu - vezmeme první existující
        $firma = App\Models\Firma::first();
        if (!$firma) {
            echo json_encode(['ok' => false, 'log' => $log, 'error' => 'Žádná firma v DB']);
            exit;
        }
        $log[] = "Firma: {$firma->nazev} (IČO: {$firma->ico})";

        $tempPath = $file['tmp_name'];
        $fileHash = hash_file('sha256', $tempPath);
        $originalName = $file['name'];
        $log[] = "Hash: " . substr($fileHash, 0, 16) . '...';

        $processor = new App\Services\DokladProcessor();

        // Kontrola duplicity
        $existujici = $processor->isDuplicate($fileHash, $firma->ico);
        if ($existujici) {
            $log[] = "DUPLICITA: #{$existujici->id} ({$existujici->cislo_dokladu})";
            echo json_encode(['ok' => false, 'log' => $log, 'error' => 'Duplicita'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $log[] = 'Duplicita: ne';

        // Volání DokladProcessor::process()
        $log[] = 'Volám DokladProcessor::process()...';
        $start = microtime(true);
        $doklady = $processor->process($tempPath, $originalName, $firma, $fileHash, 'upload');
        $elapsed = round(microtime(true) - $start, 2);

        $log[] = "DokladProcessor::process() OK ({$elapsed}s)";
        $log[] = "Počet dokladů: " . count($doklady);

        foreach ($doklady as $i => $doklad) {
            $log[] = "--- Doklad #{$doklad->id} ---";
            $log[] = "  Stav: {$doklad->stav}";
            $log[] = "  Typ: {$doklad->typ_dokladu}";
            $log[] = "  Kvalita: {$doklad->kvalita}";
            $log[] = "  Dodavatel: {$doklad->dodavatel_nazev} ({$doklad->dodavatel_ico})";
            $log[] = "  Číslo: {$doklad->cislo_dokladu}";
            $log[] = "  Částka: {$doklad->castka_celkem} {$doklad->mena}";
            $log[] = "  S3: {$doklad->cesta_souboru}";
            if ($doklad->chybova_zprava) {
                $log[] = "  CHYBA: {$doklad->chybova_zprava}";
            }
        }

        $log[] = 'Konec: ' . date('H:i:s');
        echo json_encode(['ok' => true, 'log' => $log], JSON_UNESCAPED_UNICODE);

    } catch (\Throwable $e) {
        $log[] = 'VÝJIMKA: ' . $e->getMessage();
        $log[] = 'Soubor: ' . $e->getFile() . ':' . $e->getLine();
        $log[] = 'Trace: ' . substr($e->getTraceAsString(), 0, 500);
        echo json_encode(['ok' => false, 'log' => $log, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

if ($mode === 'processor') {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><title>Processor Test</title></head><body>';
    echo '<h2>Test DokladProcessor (kompletní pipeline)</h2>';
    echo '<p>Tento test volá DokladProcessor::process() přímo - stejný kód jako InvoiceController.</p>';
    echo '<form method="POST" enctype="multipart/form-data">';
    echo '<input type="file" name="testfile" accept=".pdf,.jpg,.jpeg,.png" required>';
    echo '<button type="submit">Zpracovat doklad</button>';
    echo '</form>';
    echo '<div id="result" style="margin-top:1rem; white-space:pre-wrap; font-family:monospace;"></div>';
    echo '<script>
    document.querySelector("form").addEventListener("submit", function(e) {
        e.preventDefault();
        var fd = new FormData(this);
        var result = document.getElementById("result");
        result.textContent = "Zpracovávám přes DokladProcessor...";
        var start = Date.now();
        fetch("?mode=processor", {method:"POST", body:fd})
            .then(r => {
                result.textContent += "\\nHTTP " + r.status + " (" + ((Date.now()-start)/1000).toFixed(1) + "s)\\n";
                return r.text();
            })
            .then(t => {
                try { var j = JSON.parse(t); result.textContent = JSON.stringify(j, null, 2); } catch(e) { result.textContent += t; }
            })
            .catch(err => { result.textContent += "\\nFetch error: " + err.message; });
    });
    </script>';
    echo '</body></html>';
    exit;
}

// === UPLOAD TEST MODE ===
if ($mode === 'upload' && $_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['testfile'])) {
    header('Content-Type: application/json; charset=utf-8');
    $log = [];
    $log[] = 'Start: ' . date('H:i:s');

    try {
        $file = $_FILES['testfile'];
        $log[] = "Soubor: {$file['name']} ({$file['size']} bytes, type: {$file['type']})";
        $log[] = "Temp: {$file['tmp_name']}";

        if ($file['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['ok' => false, 'log' => $log, 'error' => 'Upload error: ' . $file['error']]);
            exit;
        }

        $fileBytes = file_get_contents($file['tmp_name']);
        $log[] = 'file_get_contents: OK (' . strlen($fileBytes) . ' bytes)';

        // Test S3 upload
        $log[] = 'S3 PUT...';
        $testPath = '_diagnostika/upload_test_' . time() . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
        Illuminate\Support\Facades\Storage::disk('s3')->put($testPath, $fileBytes);
        $log[] = 'S3 PUT: OK';

        // Test Claude Vision
        $log[] = 'Claude Vision API...';
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $isPdf = $ext === 'pdf' || str_starts_with($fileBytes, '%PDF');
        $base64 = base64_encode($fileBytes);
        $mediaType = $isPdf ? 'application/pdf' : 'image/jpeg';

        $contentBlock = $isPdf
            ? ['type' => 'document', 'source' => ['type' => 'base64', 'media_type' => $mediaType, 'data' => $base64]]
            : ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $mediaType, 'data' => $base64]];

        $start = microtime(true);
        $response = Illuminate\Support\Facades\Http::timeout(120)->withHeaders([
            'x-api-key' => config('services.anthropic.key'),
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->post('https://api.anthropic.com/v1/messages', [
            'model' => 'claude-haiku-4-5-20251001',
            'max_tokens' => 100,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        $contentBlock,
                        ['type' => 'text', 'text' => 'Co je na tomto dokumentu? Odpověz jednou větou.'],
                    ],
                ],
            ],
        ]);
        $elapsed = round(microtime(true) - $start, 2);

        if ($response->successful()) {
            $body = $response->json();
            $text = $body['content'][0]['text'] ?? '';
            $log[] = "Claude Vision: OK ({$elapsed}s) - " . substr($text, 0, 200);
        } else {
            $log[] = "Claude Vision CHYBA: HTTP {$response->status()} - " . substr($response->body(), 0, 300);
        }

        // Cleanup
        Illuminate\Support\Facades\Storage::disk('s3')->delete($testPath);
        $log[] = 'S3 cleanup: OK';
        $log[] = 'Konec: ' . date('H:i:s');

        echo json_encode(['ok' => true, 'log' => $log], JSON_UNESCAPED_UNICODE);

    } catch (\Throwable $e) {
        $log[] = 'VÝJIMKA: ' . $e->getMessage();
        echo json_encode(['ok' => false, 'log' => $log, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// === UPLOAD TEST FORM ===
if ($mode === 'upload') {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><title>Upload Test</title></head><body>';
    echo '<h2>Test uploadu dokladu</h2>';
    echo '<form method="POST" enctype="multipart/form-data">';
    echo '<input type="file" name="testfile" accept=".pdf,.jpg,.jpeg,.png" required>';
    echo '<button type="submit">Odeslat</button>';
    echo '</form>';
    echo '<div id="result" style="margin-top:1rem; white-space:pre-wrap; font-family:monospace;"></div>';
    echo '<script>
    document.querySelector("form").addEventListener("submit", function(e) {
        e.preventDefault();
        var fd = new FormData(this);
        var result = document.getElementById("result");
        result.textContent = "Zpracovávám...";
        var start = Date.now();
        fetch("?mode=upload", {method:"POST", body:fd})
            .then(r => {
                result.textContent += "\\nHTTP " + r.status + " (" + ((Date.now()-start)/1000).toFixed(1) + "s)\\n";
                return r.text();
            })
            .then(t => {
                result.textContent += t;
                try { var j = JSON.parse(t); result.textContent = JSON.stringify(j, null, 2); } catch(e) {}
            })
            .catch(err => { result.textContent += "\\nFetch error: " + err.message; });
    });
    </script>';
    echo '</body></html>';
    exit;
}

// === INFO MODE (default) ===
header('Content-Type: text/plain; charset=utf-8');
function out($msg) { echo $msg; ob_flush(); flush(); }

out("=== TupTuDu Diagnostika ===\n\n");

out("--- PHP Limity ---\n");
out("max_execution_time: " . ini_get('max_execution_time') . "s\n");
out("upload_max_filesize: " . ini_get('upload_max_filesize') . "\n");
out("post_max_size: " . ini_get('post_max_size') . "\n");
out("memory_limit: " . ini_get('memory_limit') . "\n");
out("PHP verze: " . phpversion() . "\n");
out("Base path: " . realpath($basePath) . "\n\n");

out("--- Konfigurace ---\n");
out("AWS_BUCKET: " . config('filesystems.disks.s3.bucket') . "\n");
out("AWS_REGION: " . config('filesystems.disks.s3.region') . "\n");
out("AWS_KEY: " . (config('filesystems.disks.s3.key') ? substr(config('filesystems.disks.s3.key'), 0, 8) . '...' : 'CHYBÍ') . "\n");
out("S3 throw: " . (config('filesystems.disks.s3.throw') ? 'true' : 'false') . "\n");
out("ANTHROPIC_KEY: " . (config('services.anthropic.key') ? substr(config('services.anthropic.key'), 0, 10) . '...' : 'CHYBÍ') . "\n\n");

out("--- Test S3 ---\n");
try {
    $disk = Illuminate\Support\Facades\Storage::disk('s3');
    $testPath = '_diagnostika/test_' . time() . '.txt';
    $disk->put($testPath, 'test');
    out("S3: OK\n");
    $disk->delete($testPath);
} catch (\Throwable $e) {
    out("S3 CHYBA: " . $e->getMessage() . "\n");
}

out("\n--- Test Claude API ---\n");
try {
    $response = Illuminate\Support\Facades\Http::timeout(30)->withHeaders([
        'x-api-key' => config('services.anthropic.key'),
        'anthropic-version' => '2023-06-01',
        'content-type' => 'application/json',
    ])->post('https://api.anthropic.com/v1/messages', [
        'model' => 'claude-haiku-4-5-20251001',
        'max_tokens' => 50,
        'messages' => [['role' => 'user', 'content' => 'Řekni: OK']],
    ]);
    $text = $response->json()['content'][0]['text'] ?? '';
    out("Claude API: OK - '{$text}'\n");
} catch (\Throwable $e) {
    out("Claude API CHYBA: " . $e->getMessage() . "\n");
}

out("\n--- Poslední doklady ---\n");
$posledni = App\Models\Doklad::orderBy('id', 'desc')->take(5)->get(['id', 'nazev_souboru', 'stav', 'chybova_zprava', 'created_at']);
foreach ($posledni as $d) {
    out("#{$d->id} | {$d->stav} | {$d->nazev_souboru} | {$d->created_at}\n");
    if ($d->chybova_zprava) out("  Chyba: " . substr($d->chybova_zprava, 0, 200) . "\n");
}
out("Celkem: " . App\Models\Doklad::count() . "\n");

out("\n--- Upload test ---\n");
out("Otevři: https://office.tuptudu.cz/diagnose.php?mode=upload\n");
out("\n=== HOTOVO ===\n");
