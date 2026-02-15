<?php
/**
 * Tests the EXACT same code path as index.php (HTTP kernel + handleRequest)
 * but with timing at every step to find where the upload gets stuck.
 *
 * This script creates a synthetic Request to /upload and dispatches it
 * through the full HTTP kernel (middleware, routing, controller).
 *
 * GET  → shows info
 * POST → forwards to Laravel HTTP kernel as POST /upload
 */

$phpStartTime = microtime(true);
$phpStartDate = date('H:i:s') . '.' . sprintf('%03d', ($phpStartTime * 1000) % 1000);

$SECRET = 'tuptudu-diag-2026-xK9m';
if (($_GET['token'] ?? '') !== $SECRET) {
    http_response_code(403);
    die('Forbidden');
}

set_time_limit(300);
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Detect base path
if (file_exists(__DIR__.'/../laravel-office/vendor/autoload.php')) {
    $basePath = __DIR__.'/../laravel-office';
} elseif (file_exists(__DIR__.'/../../laravel-office/vendor/autoload.php')) {
    $basePath = __DIR__.'/../../laravel-office';
} else {
    $basePath = __DIR__.'/..';
}

$logFile = $basePath . '/storage/logs/upload_timing.log';
$tLog = function(string $msg) use ($phpStartTime, $logFile) {
    $elapsed = round((microtime(true) - $phpStartTime) * 1000);
    $line = date('H:i:s') . " +{$elapsed}ms [httpkernel] $msg";
    @file_put_contents($logFile, "$line\n", FILE_APPEND);
    echo "$line\n";
    @ob_flush(); @flush();
};

header('Content-Type: text/plain; charset=utf-8');
$tLog("=== REQUEST RECEIVED by PHP ===");
$tLog("Method: {$_SERVER['REQUEST_METHOD']}");
$tLog("PHP version: " . PHP_VERSION . ", SAPI: " . php_sapi_name());
$tLog("max_execution_time: " . ini_get('max_execution_time'));
$tLog("max_input_time: " . ini_get('max_input_time'));

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $tLog("GET request - showing info only");
    echo "\nThis script tests the full HTTP kernel pipeline.\n";
    echo "POST a file with documents[] to test upload through full Laravel stack.\n";
    echo "\nTo test, use the form at diag_httptest.php or curl:\n";
    echo "  curl -X POST -F 'documents[]=@file.pdf' 'URL/diag_httpkernel.php?token=$SECRET'\n";
    exit;
}

// POST: Log file info
$tLog("POST received, Content-Length: " . ($_SERVER['CONTENT_LENGTH'] ?? '?'));
if (!empty($_FILES)) {
    foreach ($_FILES as $key => $f) {
        if (is_array($f['name'])) {
            for ($i = 0; $i < count($f['name']); $i++) {
                $tLog("File: {$key}[$i] = {$f['name'][$i]} (" . round($f['size'][$i]/1024, 1) . "KB) error={$f['error'][$i]}");
            }
        } else {
            $tLog("File: $key = {$f['name']} (" . round($f['size']/1024, 1) . "KB) error={$f['error']}");
        }
    }
} else {
    $tLog("WARNING: No \$_FILES! This means the file wasn't uploaded properly.");
}

// Step 1: Autoload
$tLog("Loading autoload...");
define('LARAVEL_START', microtime(true));
require $basePath.'/vendor/autoload.php';
$tLog("Autoload loaded");

// Step 2: Create app
$tLog("Creating app...");
$app = require_once $basePath.'/bootstrap/app.php';
$tLog("App created");

// Step 3: Create HTTP kernel
$tLog("Creating HTTP kernel...");
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
$tLog("HTTP kernel created");

// Step 4: Capture request
$tLog("Capturing request...");
$request = \Illuminate\Http\Request::capture();
$tLog("Request captured: {$request->method()} {$request->path()}");
$tLog("Request has files: " . ($request->hasFile('documents') ? 'YES' : 'NO'));

// Step 5: Handle through kernel
$tLog("=== Calling kernel->handle() - this goes through ALL middleware ===");
$handleStart = microtime(true);

try {
    $response = $kernel->handle($request);
    $handleMs = round((microtime(true) - $handleStart) * 1000);
    $tLog("kernel->handle() completed in {$handleMs}ms");
    $tLog("Response status: " . $response->getStatusCode());

    // Show response info
    $content = $response->getContent();
    $tLog("Response length: " . strlen($content) . " bytes");

    // Check if it's a redirect (auth redirect?)
    if ($response->isRedirection()) {
        $tLog("REDIRECT to: " . $response->headers->get('Location'));
        echo "\n*** REDIRECT DETECTED ***\n";
        echo "Location: " . $response->headers->get('Location') . "\n";
        echo "This likely means auth middleware is redirecting to login!\n";
    } else {
        // Show first 2000 chars of response
        echo "\n=== Response (first 2000 chars) ===\n";
        echo substr($content, 0, 2000) . "\n";
    }
} catch (\Throwable $e) {
    $handleMs = round((microtime(true) - $handleStart) * 1000);
    $tLog("kernel->handle() FAILED after {$handleMs}ms: {$e->getMessage()}");
    echo "\n*** ERROR ***\n";
    echo $e->getMessage() . "\n";
    echo $e->getFile() . ":" . $e->getLine() . "\n";
}

$totalMs = round((microtime(true) - $phpStartTime) * 1000);
$tLog("=== TOTAL: {$totalMs}ms ===");

// Terminate
try {
    $kernel->terminate($request, $response ?? new \Illuminate\Http\Response());
} catch (\Throwable $e) {}
