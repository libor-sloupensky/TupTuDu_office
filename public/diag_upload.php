<?php
/**
 * Test full upload pipeline (Laravel bootstrapped).
 * Tests: S3 upload, Claude Vision API with full prompt, DB write.
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(120);
header('Content-Type: text/plain; charset=utf-8');

echo "=== Full Upload Pipeline Test ===\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n\n";

// Bootstrap Laravel (WITHOUT handling request to avoid auth redirect)
if (file_exists(__DIR__.'/../laravel-office/vendor/autoload.php')) {
    $basePath = __DIR__.'/../laravel-office';
} elseif (file_exists(__DIR__.'/../../laravel-office/vendor/autoload.php')) {
    $basePath = __DIR__.'/../../laravel-office';
} else {
    $basePath = __DIR__.'/..';
}

require $basePath.'/vendor/autoload.php';

// Boot the application without handling HTTP request
$app = require_once $basePath.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Laravel booted from: " . realpath($basePath) . "\n";
echo "PHP: " . PHP_VERSION . "\n\n";

// Step 1: Check DB connection
echo "--- Step 1: DB Connection ---\n";
$start = microtime(true);
try {
    $firma = \App\Models\Firma::first();
    echo "OK - Firma: " . ($firma ? $firma->nazev . " (ICO: {$firma->ico})" : 'NONE') . "\n";
    echo "Time: " . round((microtime(true) - $start) * 1000) . "ms\n";
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit;
}
echo "\n";

if (!$firma) { echo "No firma found, cannot test.\n"; exit; }

// Step 2: S3 Upload test
echo "--- Step 2: S3 Upload ---\n";
$start = microtime(true);
try {
    $testData = 'diagnostic test ' . date('Y-m-d H:i:s');
    \Illuminate\Support\Facades\Storage::disk('s3')->put('_diag/test.txt', $testData);
    echo "OK - S3 upload successful\n";
    echo "Time: " . round((microtime(true) - $start) * 1000) . "ms\n";
    \Illuminate\Support\Facades\Storage::disk('s3')->delete('_diag/test.txt');
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
echo "\n";

// Step 3: Claude Vision API with full system prompt + generated test image
echo "--- Step 3: Claude Vision API (full prompt) ---\n";
$start = microtime(true);
try {
    // Create a test image that looks like an invoice
    $img = imagecreatetruecolor(400, 200);
    $white = imagecolorallocate($img, 255, 255, 255);
    $black = imagecolorallocate($img, 0, 0, 0);
    imagefill($img, 0, 0, $white);
    imagestring($img, 5, 10, 10, 'FAKTURA c. 2024-0042', $black);
    imagestring($img, 4, 10, 35, 'Dodavatel: Test Company s.r.o.', $black);
    imagestring($img, 4, 10, 55, 'ICO: 12345678  DIC: CZ12345678', $black);
    imagestring($img, 4, 10, 80, 'Odberatel: ' . ($firma->nazev ?? 'Firma'), $black);
    imagestring($img, 4, 10, 100, 'ICO: ' . $firma->ico, $black);
    imagestring($img, 5, 10, 130, 'Celkem: 1 234,00 CZK', $black);
    imagestring($img, 4, 10, 155, 'Datum: 15.02.2026  Splatnost: 01.03.2026', $black);
    imagestring($img, 4, 10, 175, 'DUZP: 15.02.2026', $black);
    ob_start(); imagepng($img); $imgBytes = ob_get_clean();

    echo "Test image: " . strlen($imgBytes) . " bytes\n";

    // Call DokladProcessor's analyzeWithVision via reflection (private method)
    $processor = new \App\Services\DokladProcessor();
    $method = new \ReflectionMethod($processor, 'analyzeWithVision');
    $method->setAccessible(true);

    $result = $method->invoke($processor, $imgBytes, 'png', $firma);
    $elapsed = round((microtime(true) - $start) * 1000);

    echo "OK - API responded\n";
    echo "Time: {$elapsed}ms\n";
    echo "Tokens: input=" . ($result['_tokens']['input'] ?? '?') . ", output=" . ($result['_tokens']['output'] ?? '?') . "\n";
    $docs = $result['dokumenty'] ?? [];
    echo "Documents found: " . count($docs) . "\n";
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

// Step 4: Full DokladProcessor pipeline
echo "--- Step 4: Full DokladProcessor Pipeline ---\n";
$start = microtime(true);
try {
    $tmpFile = tempnam(sys_get_temp_dir(), 'diag_') . '.png';
    file_put_contents($tmpFile, $imgBytes);
    $hash = hash('sha256', $imgBytes . '_diag_' . time());

    $doklady = $processor->process($tmpFile, 'diag_test.png', $firma, $hash, 'upload');
    $elapsed = round((microtime(true) - $start) * 1000);

    echo "OK - Pipeline completed\n";
    echo "Time: {$elapsed}ms\n";
    echo "Doklady created: " . count($doklady) . "\n";
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
    @unlink($tmpFile);
    echo "Cleanup: done\n";
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Time: " . round((microtime(true) - $start) * 1000) . "ms\n";
}

echo "\n=== Done ===\n";
