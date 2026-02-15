<?php
/**
 * Test upload endpoint BEZ auth - simuluje přesně to co dělá InvoiceController::store()
 * POST s souborem → DokladProcessor → výsledek s časováním
 * GET → formulář pro upload
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(120);

// Bootstrap Laravel
if (file_exists(__DIR__.'/../laravel-office/vendor/autoload.php')) {
    $basePath = __DIR__.'/../laravel-office';
} elseif (file_exists(__DIR__.'/../../laravel-office/vendor/autoload.php')) {
    $basePath = __DIR__.'/../../laravel-office';
} else {
    $basePath = __DIR__.'/..';
}
require $basePath.'/vendor/autoload.php';
$app = require_once $basePath.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><body>
    <h2>Upload Test (no auth)</h2>
    <form method="POST" enctype="multipart/form-data">
        <input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png"><br><br>
        <button type="submit">Upload & Process</button>
    </form></body></html>';
    exit;
}

// POST handler
header('Content-Type: text/plain; charset=utf-8');
$totalStart = microtime(true);

echo "=== Upload Pipeline Test ===\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n";
echo "PHP: " . PHP_VERSION . ", max_execution_time: " . ini_get('max_execution_time') . "\n\n";

if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
    echo "ERROR: No file uploaded or upload error: " . ($_FILES['document']['error'] ?? 'missing') . "\n";
    exit;
}

$file = $_FILES['document'];
echo "File: {$file['name']}\n";
echo "Size: " . round($file['size'] / 1024, 1) . " KB\n";
echo "Type: {$file['type']}\n";
echo "Tmp: {$file['tmp_name']}\n\n";

// Step 1: Get firma
echo "--- Step 1: Get Firma ---\n";
$start = microtime(true);
$firma = \App\Models\Firma::first();
echo "Firma: {$firma->nazev} (ICO: {$firma->ico})\n";
echo "Time: " . round((microtime(true) - $start) * 1000) . "ms\n\n";

// Step 2: Hash
echo "--- Step 2: Hash ---\n";
$start = microtime(true);
$hash = hash_file('sha256', $file['tmp_name']);
echo "Hash: " . substr($hash, 0, 16) . "...\n";
echo "Time: " . round((microtime(true) - $start) * 1000) . "ms\n\n";

// Step 3: Duplicate check
echo "--- Step 3: Duplicate Check ---\n";
$start = microtime(true);
$processor = new \App\Services\DokladProcessor();
$existing = $processor->isDuplicate($hash, $firma->ico);
echo "Duplicate: " . ($existing ? "YES (#{$existing->id})" : "NO") . "\n";
echo "Time: " . round((microtime(true) - $start) * 1000) . "ms\n\n";

if ($existing) {
    echo "Skipping (duplicate). Total: " . round((microtime(true) - $totalStart) * 1000) . "ms\n";
    exit;
}

// Step 4: S3 upload
echo "--- Step 4: S3 Upload ---\n";
$start = microtime(true);
$fileBytes = file_get_contents($file['tmp_name']);
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) ?: 'pdf';
$tempS3Path = "doklady/{$firma->ico}/_tmp/" . time() . "_{$hash}.{$ext}";
try {
    \Illuminate\Support\Facades\Storage::disk('s3')->put($tempS3Path, $fileBytes);
    echo "OK - uploaded to S3: {$tempS3Path}\n";
    echo "Time: " . round((microtime(true) - $start) * 1000) . "ms\n\n";
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Time: " . round((microtime(true) - $start) * 1000) . "ms\n\n";
}

// Step 5: Claude Vision API
echo "--- Step 5: Claude Vision API ---\n";
$start = microtime(true);
try {
    $method = new \ReflectionMethod($processor, 'analyzeWithVision');
    $method->setAccessible(true);
    $result = $method->invoke($processor, $fileBytes, $ext, $firma);
    $elapsed = round((microtime(true) - $start) * 1000);
    echo "OK - API responded\n";
    echo "Time: {$elapsed}ms\n";
    echo "Tokens: input=" . ($result['_tokens']['input'] ?? '?') . ", output=" . ($result['_tokens']['output'] ?? '?') . "\n";
    $docs = $result['dokumenty'] ?? [];
    echo "Documents: " . count($docs) . "\n";
    if (!empty($docs[0])) {
        echo "  Type: " . ($docs[0]['typ_dokladu'] ?? '?') . "\n";
        echo "  Supplier: " . ($docs[0]['dodavatel_nazev'] ?? '?') . "\n";
        echo "  Amount: " . ($docs[0]['castka_celkem'] ?? '?') . "\n";
    }
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Time: " . round((microtime(true) - $start) * 1000) . "ms\n";
}
echo "\n";

// Step 6: Full pipeline (creates + cleans up DB records)
echo "--- Step 6: Full DokladProcessor::process() ---\n";
$start = microtime(true);
// Clean up S3 temp from step 4
try { \Illuminate\Support\Facades\Storage::disk('s3')->delete($tempS3Path); } catch (\Throwable $e) {}
// Use a unique hash so it's not detected as duplicate from step 3 result
$uniqueHash = hash('sha256', $hash . '_diag_' . time());
try {
    $doklady = $processor->process($file['tmp_name'], $file['name'], $firma, $uniqueHash, 'upload');
    $elapsed = round((microtime(true) - $start) * 1000);
    echo "OK - Pipeline completed\n";
    echo "Time: {$elapsed}ms\n";
    echo "Doklady: " . count($doklady) . "\n";
    foreach ($doklady as $d) {
        echo "  #{$d->id} stav={$d->stav} typ={$d->typ_dokladu} dodavatel={$d->dodavatel_nazev} castka={$d->castka_celkem}\n";
    }

    // Cleanup
    foreach ($doklady as $d) {
        if ($d->cesta_souboru) {
            try { \Illuminate\Support\Facades\Storage::disk('s3')->delete($d->cesta_souboru); } catch (\Throwable $e) {}
        }
        $d->delete();
    }
    echo "Cleanup: done\n";
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Time: " . round((microtime(true) - $start) * 1000) . "ms\n";
}
echo "\n";

echo "=== TOTAL: " . round((microtime(true) - $totalStart) * 1000) . "ms ===\n";
