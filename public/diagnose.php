<?php
/**
 * Diagnostika uploadu dokladů - dočasný soubor, po otestování smazat!
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Content-Type: text/plain; charset=utf-8');

function out($msg) { echo $msg; ob_flush(); flush(); }

out("=== TupTuDu Diagnostika ===\n\n");

// 1. PHP limity
out("--- PHP Limity ---\n");
out("max_execution_time: " . ini_get('max_execution_time') . "s\n");
out("upload_max_filesize: " . ini_get('upload_max_filesize') . "\n");
out("post_max_size: " . ini_get('post_max_size') . "\n");
out("memory_limit: " . ini_get('memory_limit') . "\n");
out("PHP verze: " . phpversion() . "\n\n");

// Bootstrap Laravel
out("Bootstrap Laravel...\n");
try {
    require __DIR__ . '/../vendor/autoload.php';
    $app = require_once __DIR__ . '/../bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    out("Bootstrap: OK\n\n");
} catch (\Throwable $e) {
    out("Bootstrap CHYBA: " . $e->getMessage() . "\n");
    exit;
}

// 2. Konfigurace (před testy, aby bylo vidět i při pádu)
out("--- Konfigurace ---\n");
out("FILESYSTEM_DISK: " . config('filesystems.default') . "\n");
out("AWS_BUCKET: " . config('filesystems.disks.s3.bucket') . "\n");
out("AWS_REGION: " . config('filesystems.disks.s3.region') . "\n");
out("AWS_KEY: " . (config('filesystems.disks.s3.key') ? substr(config('filesystems.disks.s3.key'), 0, 8) . '...' : 'CHYBÍ') . "\n");
out("AWS_SECRET: " . (config('filesystems.disks.s3.secret') ? 'nastaveno' : 'CHYBÍ') . "\n");
out("S3 throw: " . (config('filesystems.disks.s3.throw') ? 'true' : 'false') . "\n");
out("ANTHROPIC_KEY: " . (config('services.anthropic.key') ? substr(config('services.anthropic.key'), 0, 10) . '...' : 'CHYBÍ') . "\n\n");

// 3. Test S3
out("--- Test S3 ---\n");
try {
    out("Vytvářím S3 disk...\n");
    $disk = Illuminate\Support\Facades\Storage::disk('s3');
    out("S3 disk vytvořen.\n");

    $testPath = '_diagnostika/test_' . time() . '.txt';
    out("Zkouším PUT: $testPath ...\n");
    $disk->put($testPath, 'TupTuDu test ' . date('Y-m-d H:i:s'));
    out("S3 PUT: OK\n");

    out("Zkouším EXISTS...\n");
    $exists = $disk->exists($testPath);
    out("S3 EXISTS: " . ($exists ? 'OK' : 'FAIL') . "\n");

    out("Zkouším DELETE...\n");
    $disk->delete($testPath);
    out("S3 DELETE: OK\n");
} catch (\Throwable $e) {
    out("S3 CHYBA: " . $e->getMessage() . "\n");
    out("Třída: " . get_class($e) . "\n");
    out("Trace: " . substr($e->getTraceAsString(), 0, 500) . "\n");
}
out("\n");

// 4. Test Claude API
out("--- Test Claude API ---\n");
$apiKey = config('services.anthropic.key');
if (empty($apiKey)) {
    out("CHYBA: Anthropic API klíč není nastaven!\n");
} else {
    out("API klíč: " . substr($apiKey, 0, 10) . "...\n");
    try {
        out("Odesílám testovací request...\n");
        $start = microtime(true);
        $response = Illuminate\Support\Facades\Http::timeout(30)->withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->post('https://api.anthropic.com/v1/messages', [
            'model' => 'claude-haiku-4-5-20251001',
            'max_tokens' => 50,
            'messages' => [
                ['role' => 'user', 'content' => 'Řekni jedním slovem: funguje?'],
            ],
        ]);
        $elapsed = round(microtime(true) - $start, 2);

        if ($response->successful()) {
            $body = $response->json();
            $text = $body['content'][0]['text'] ?? '(prázdná odpověď)';
            out("Claude API: OK ({$elapsed}s) - '{$text}'\n");
        } else {
            out("Claude API CHYBA: HTTP " . $response->status() . "\n");
            out("Body: " . substr($response->body(), 0, 500) . "\n");
        }
    } catch (\Throwable $e) {
        out("Claude API VÝJIMKA: " . $e->getMessage() . "\n");
    }
}
out("\n");

// 5. Poslední doklady
out("--- Poslední doklady ---\n");
try {
    $posledni = App\Models\Doklad::orderBy('id', 'desc')->take(5)->get(['id', 'nazev_souboru', 'stav', 'chybova_zprava', 'kvalita', 'typ_dokladu', 'created_at']);
    foreach ($posledni as $d) {
        out("#{$d->id} | {$d->stav} | {$d->typ_dokladu} | {$d->kvalita} | {$d->nazev_souboru} | {$d->created_at}\n");
        if ($d->chybova_zprava) out("  Chyba: " . substr($d->chybova_zprava, 0, 200) . "\n");
    }
    out("\nCelkem dokladů: " . App\Models\Doklad::count() . "\n");
} catch (\Throwable $e) {
    out("DB CHYBA: " . $e->getMessage() . "\n");
}

out("\n=== HOTOVO ===\n");
