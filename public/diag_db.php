<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/plain; charset=utf-8');

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

echo "=== DB Tables Check ===\n\n";

$tables = \Illuminate\Support\Facades\DB::select('SHOW TABLES');
$key = array_keys((array)$tables[0])[0];
echo "All tables:\n";
foreach ($tables as $t) {
    echo "  - " . $t->$key . "\n";
}

echo "\n--- sys_users ---\n";
try {
    $users = \Illuminate\Support\Facades\DB::select('SELECT id, jmeno, prijmeni, email, email_verified_at FROM sys_users LIMIT 5');
    echo "Count: " . count($users) . "\n";
    foreach ($users as $u) {
        echo "  #{$u->id} {$u->jmeno} {$u->prijmeni} <{$u->email}> verified=" . ($u->email_verified_at ?? 'null') . "\n";
    }
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "\n--- sys_user_firma ---\n";
try {
    $rows = \Illuminate\Support\Facades\DB::select('SELECT * FROM sys_user_firma LIMIT 10');
    echo "Count: " . count($rows) . "\n";
    foreach ($rows as $r) {
        echo "  user_id={$r->user_id} firma_ico={$r->firma_ico} role={$r->role}\n";
    }
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "\n--- sys_firmy ---\n";
try {
    $firms = \Illuminate\Support\Facades\DB::select('SELECT ico, nazev FROM sys_firmy LIMIT 5');
    echo "Count: " . count($firms) . "\n";
    foreach ($firms as $f) {
        echo "  ICO={$f->ico} {$f->nazev}\n";
    }
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "\n--- fak_doklady (last 3) ---\n";
try {
    $docs = \Illuminate\Support\Facades\DB::select('SELECT id, firma_ico, nazev_souboru, stav, created_at FROM fak_doklady ORDER BY id DESC LIMIT 3');
    echo "Count: " . count($docs) . "\n";
    foreach ($docs as $d) {
        echo "  #{$d->id} [{$d->firma_ico}] {$d->nazev_souboru} stav={$d->stav} {$d->created_at}\n";
    }
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "\n=== Done ===\n";
