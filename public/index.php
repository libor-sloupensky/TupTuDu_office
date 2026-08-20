<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Detect base path — adresář `laravel-office` leží mimo webroot a podle
// hostingu může být o 1 až několik úrovní výš, případně v podadresáři pro
// data (Český hosting nedovolí zakládat adresáře v kořeni účtu, takže tam
// Laravel sídlí v /data). Hledáme ho směrem nahoru, aby index.php fungoval
// na jakékoli struktuře (fallback = lokální vývoj).
$basePath = __DIR__.'/..';
$probe = __DIR__;

for ($i = 0; $i < 6; $i++) {
    $probe = dirname($probe);

    foreach (['/laravel-office', '/data/laravel-office'] as $kandidat) {
        if (file_exists($probe.$kandidat.'/vendor/autoload.php')) {
            $basePath = $probe.$kandidat;
            break 2;
        }
    }
}

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = $basePath.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require $basePath.'/vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once $basePath.'/bootstrap/app.php';

$app->handleRequest(Request::capture());
