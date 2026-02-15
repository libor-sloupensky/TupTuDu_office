<?php
// Dočasná diagnostika - smazat po testu
header('Content-Type: text/plain; charset=utf-8');

echo "=== API Diagnostika ===\n";
echo "PHP version: " . PHP_VERSION . "\n";
echo "max_execution_time: " . ini_get('max_execution_time') . "s\n";
echo "memory_limit: " . ini_get('memory_limit') . "\n";
echo "allow_url_fopen: " . ini_get('allow_url_fopen') . "\n";

// Test DNS
echo "\n--- DNS Test ---\n";
$start = microtime(true);
$ip = gethostbyname('api.anthropic.com');
echo "api.anthropic.com -> {$ip} (" . round((microtime(true) - $start) * 1000) . "ms)\n";

// Test HTTPS connection to Anthropic
echo "\n--- HTTPS Connection Test ---\n";
$start = microtime(true);

$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_HTTPHEADER => [
        'x-api-key: test-key',
        'anthropic-version: 2023-06-01',
        'content-type: application/json',
    ],
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode([
        'model' => 'claude-haiku-4-5-20251001',
        'max_tokens' => 10,
        'messages' => [['role' => 'user', 'content' => 'test']],
    ]),
]);
$result = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
$errno = curl_errno($ch);
curl_close($ch);

$elapsed = round((microtime(true) - $start) * 1000);
echo "HTTP {$httpCode} ({$elapsed}ms)\n";
if ($error) echo "cURL error [{$errno}]: {$error}\n";
echo "Response: " . substr($result, 0, 200) . "\n";

// Test with real API key from .env
echo "\n--- Laravel .env API Key Test ---\n";
$envPath = dirname(__DIR__) . '/../laravel-office/.env';
if (!file_exists($envPath)) {
    // Try relative to document root
    $envPath = dirname(__DIR__) . '/.env';
}
$apiKey = null;
if (file_exists($envPath)) {
    $env = file_get_contents($envPath);
    if (preg_match('/ANTHROPIC_API_KEY=(.+)/', $env, $m)) {
        $apiKey = trim($m[1]);
        echo "API key found: " . substr($apiKey, 0, 10) . "...\n";
    } else {
        echo "ANTHROPIC_API_KEY not found in .env\n";
    }
} else {
    echo ".env not found at: {$envPath}\n";
    // Try to find it
    $possiblePaths = [
        dirname(__DIR__) . '/../laravel-office/.env',
        '/home/multi_833363/tuptudu.cz/laravel-office/.env',
        dirname(dirname(__DIR__)) . '/laravel-office/.env',
    ];
    foreach ($possiblePaths as $p) {
        echo "  Trying: {$p} -> " . (file_exists($p) ? "EXISTS" : "not found") . "\n";
    }
}

if ($apiKey) {
    echo "\n--- Real API Call (tiny) ---\n";
    $start = microtime(true);
    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER => [
            'x-api-key: ' . $apiKey,
            'anthropic-version: 2023-06-01',
            'content-type: application/json',
        ],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'model' => 'claude-haiku-4-5-20251001',
            'max_tokens' => 20,
            'messages' => [['role' => 'user', 'content' => 'Reply with just "OK"']],
        ]),
    ]);
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    $elapsed = round((microtime(true) - $start) * 1000);
    echo "HTTP {$httpCode} ({$elapsed}ms)\n";
    if ($error) echo "cURL error: {$error}\n";
    echo "Response: " . substr($result, 0, 300) . "\n";
}

echo "\n=== Done ===\n";
