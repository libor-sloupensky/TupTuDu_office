<?php

/**
 * Kontrola prostředí hostingu — spouští se ručně přes
 * https://office.tuptudu.cz/kontrola.php?key={token}
 *
 * Záměrně nebootuje Laravel, aby fungovala i ve chvíli, kdy je aplikace
 * rozbitá (chybějící rozšíření PHP, špatná cesta k vendoru, nedostupná DB).
 * Používá se hlavně při stěhování hostingu, kdy je potřeba ověřit server
 * ještě před přepnutím DNS.
 */

if (($_GET['key'] ?? '') !== 'f8k2Ld9xQm4vR7nW') {
    http_response_code(404);
    exit;
}

header('Content-Type: text/plain; charset=utf-8');

// Rozšíření, bez kterých aplikace nefunguje
$povinna = [
    'pdo_mysql', 'mbstring', 'openssl', 'curl', 'zip',
    'fileinfo', 'tokenizer', 'ctype', 'json', 'dom',
];

// Volitelná — bez nich funguje vše kromě zmíněné oblasti
$volitelna = [
    'bcmath' => 'přesné výpočty v AWS SDK',
    'intl' => 'formátování čísel a datumů',
    'imap' => 'nepovinné, webklex/php-imap má vlastní implementaci',
    'gd' => 'nepoužíváme, obrázky zpracovává Textract',
];

echo "=== PHP ===\n";
echo 'Verze: ' . PHP_VERSION . "\n";
echo 'SAPI: ' . PHP_SAPI . "\n";
echo 'memory_limit: ' . ini_get('memory_limit') . "\n";
echo 'max_execution_time: ' . ini_get('max_execution_time') . "\n";
echo 'upload_max_filesize: ' . ini_get('upload_max_filesize') . "\n";
echo 'post_max_size: ' . ini_get('post_max_size') . "\n";
echo 'open_basedir: ' . (ini_get('open_basedir') ?: '(bez omezení)') . "\n";

echo "\n=== Povinná rozšíření ===\n";
$chybi = [];

foreach ($povinna as $ext) {
    $ok = extension_loaded($ext);
    echo str_pad($ext, 16), $ok ? 'ANO' : 'CHYBÍ', "\n";
    if (!$ok) {
        $chybi[] = $ext;
    }
}

echo "\n=== Volitelná rozšíření ===\n";
foreach ($volitelna as $ext => $ucel) {
    echo str_pad($ext, 16), str_pad(extension_loaded($ext) ? 'ANO' : 'ne', 6), $ucel, "\n";
}

echo "\n=== Cesty ===\n";
echo 'Webroot: ' . __DIR__ . "\n";

$basePath = is_dir(dirname(__DIR__) . '/app') ? dirname(__DIR__) : null;

if ($basePath === null) {
    $probe = __DIR__;

    for ($i = 0; $i < 6; $i++) {
        $probe = dirname($probe);

        if (@is_dir($probe . '/laravel-office')) {
            $basePath = $probe . '/laravel-office';
            break;
        }
    }
}

echo 'Kořen Laravelu: ' . ($basePath ?: 'NENALEZEN') . "\n";

if ($basePath) {
    echo 'vendor/autoload.php: ' . (file_exists($basePath . '/vendor/autoload.php') ? 'ANO' : 'CHYBÍ') . "\n";
    echo '.env: ' . (is_readable($basePath . '/.env') ? 'ANO' : 'NEČITELNÝ') . "\n";

    echo "\n=== Zapisovatelnost ===\n";
    foreach (['storage/logs', 'storage/framework/cache/data', 'storage/framework/views', 'storage/app', 'bootstrap/cache'] as $dir) {
        $cesta = $basePath . '/' . $dir;
        $stav = !is_dir($cesta) ? 'CHYBÍ' : (is_writable($cesta) ? 'zapisovatelné' : 'JEN PRO ČTENÍ');
        echo str_pad($dir, 34), $stav, "\n";
    }
}

echo "\n=== Databáze ===\n";

$env = [];

if ($basePath && is_readable($basePath . '/.env')) {
    foreach (file($basePath . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $radek) {
        if ($radek === '' || $radek[0] === '#' || !str_contains($radek, '=')) {
            continue;
        }
        [$k, $v] = explode('=', $radek, 2);
        $env[trim($k)] = trim($v, " \t\"'");
    }
}

if (!isset($env['DB_HOST'])) {
    echo "Nelze načíst .env, přeskakuji.\n";
} else {
    echo "Host: {$env['DB_HOST']}, databáze: {$env['DB_DATABASE']}, uživatel: {$env['DB_USERNAME']}\n";

    try {
        $pdo = new PDO(
            "mysql:host={$env['DB_HOST']};dbname={$env['DB_DATABASE']};charset=utf8mb4",
            $env['DB_USERNAME'],
            $env['DB_PASSWORD'] ?? '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 10]
        );
        $tabulky = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        echo 'Připojeno. Tabulek: ' . count($tabulky) . "\n\n";

        foreach ($tabulky as $tabulka) {
            $pocet = $pdo->query("SELECT COUNT(*) FROM `{$tabulka}`")->fetchColumn();
            echo '  ' . str_pad($tabulka, 30), str_pad((string) $pocet, 8, ' ', STR_PAD_LEFT), " řádků\n";
        }

        $klice = $pdo->query("
            SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_TYPE = 'FOREIGN KEY'
        ")->fetchColumn();

        echo "\n  Cizích klíčů: {$klice} (očekáváno 10)\n";
    } catch (Throwable $e) {
        echo 'CHYBA: ' . $e->getMessage() . "\n";
    }
}

echo "\n=== Odchozí spojení ===\n";

// Odchozí SMTP bývá na sdílených hostinzích filtrované, proto zkoušíme
// víc kombinací serveru a portu — ať je jasné, kudy pošta vůbec projde.
$cile = [
    ['smtp.cesky-hosting.cz', 587],
    ['smtp.cesky-hosting.cz', 465],
    ['smtp.cesky-hosting.cz', 25],
    ['mail.cesky-hosting.cz', 587],
    ['mail.cesky-hosting.cz', 465],
    ['mail.cesky-hosting.cz', 993],
    ['localhost', 25],
    ['s3.eu-west-1.amazonaws.com', 443],
    ['api.anthropic.com', 443],
];

if (!empty($env['MAIL_HOST'])) {
    array_unshift($cile, [$env['MAIL_HOST'], (int) ($env['MAIL_PORT'] ?? 587)]);
}

$videno = [];

foreach ($cile as [$hostitel, $port]) {
    $klic = $hostitel . ':' . $port;

    if (isset($videno[$klic])) {
        continue;
    }

    $videno[$klic] = true;
    $ip = gethostbyname($hostitel);
    $spojeni = @fsockopen($hostitel, $port, $errno, $errstr, 8);

    echo str_pad($klic, 34), str_pad($ip === $hostitel ? 'nepřeloženo' : $ip, 18),
        $spojeni ? 'OK' : "CHYBA: {$errstr}", "\n";

    if ($spojeni) {
        // U SMTP ukázat uvítací hlášku, ať je vidět, že tam opravdu sedí server
        if (in_array($port, [25, 465, 587], true)) {
            stream_set_timeout($spojeni, 5);
            $uvitani = trim((string) fgets($spojeni, 256));
            if ($uvitani !== '') {
                echo str_repeat(' ', 34), '→ ', $uvitani, "\n";
            }
        }
        fclose($spojeni);
    }
}

// Volitelný režim: nastartuje Laravel a ověří, že aplikace umí přečíst data
// a sáhnout na doklad v S3. Jen čtení, nic to nemění ani neodesílá.
if (isset($_GET['app']) && $basePath) {
    echo "\n=== Aplikace ===\n";

    try {
        require $basePath . '/vendor/autoload.php';
        $app = require $basePath . '/bootstrap/app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        echo 'Laravel: ' . $app->version() . "\n";
        echo 'APP_URL: ' . config('app.url') . "\n";
        echo 'Mailer: ' . config('mail.default') . ' / doklady: '
            . config('mail.mailers.doklady.transport') . "\n";
        echo 'Doména dokladů: ' . config('mail.doklady_domain') . "\n";

        $firem = App\Models\Firma::count();
        $dokladu = App\Models\Doklad::count();
        echo "Přes Eloquent — firem: {$firem}, dokladů: {$dokladu}\n";

        $doklad = App\Models\Doklad::whereNotNull('cesta_souboru')->latest('id')->first();

        if ($doklad) {
            $disk = Illuminate\Support\Facades\Storage::disk('s3');
            $existuje = $disk->exists($doklad->cesta_souboru);
            echo "S3 — soubor dokladu #{$doklad->id}: "
                . ($existuje ? 'nalezen (' . $disk->size($doklad->cesta_souboru) . ' B)' : 'CHYBÍ') . "\n";
        } else {
            echo "S3 — není doklad se souborem k ověření\n";
        }
    } catch (Throwable $e) {
        echo 'CHYBA: ' . $e->getMessage() . "\n";
        echo '  ' . $e->getFile() . ':' . $e->getLine() . "\n";
    }
}

echo "\n=== Možnosti odesílání pošty ===\n";
echo 'sendmail_path: ' . (ini_get('sendmail_path') ?: '(nenastaveno)') . "\n";
echo 'disable_functions: ' . (ini_get('disable_functions') ?: '(žádné)') . "\n";

foreach (['mail', 'proc_open', 'popen', 'exec'] as $fn) {
    echo str_pad($fn . '()', 16), function_exists($fn) ? 'k dispozici' : 'ZAKÁZÁNO', "\n";
}

echo "\n=== Závěr ===\n";
echo $chybi
    ? 'CHYBÍ POVINNÁ ROZŠÍŘENÍ: ' . implode(', ', $chybi) . "\n"
    : "Povinná rozšíření jsou v pořádku.\n";
