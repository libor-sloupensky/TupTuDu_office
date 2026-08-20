<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Standardní rozložení Laravelu: kořen aplikace je o úroveň nad webrootem.
// Platí i na produkci — DocumentRoot subdomény je posunutý na podadresář
// public, takže .env a vendor/ leží mimo dosah webserveru.
$basePath = __DIR__.'/..';

// Záložní varianta pro hostingy, kde webroot není podadresářem kořene
// aplikace a `laravel-office` leží někde nad ním.
if (!file_exists($basePath.'/vendor/autoload.php')) {
    $probe = __DIR__;

    for ($i = 0; $i < 6; $i++) {
        $probe = dirname($probe);

        if (@file_exists($probe.'/laravel-office/vendor/autoload.php')) {
            $basePath = $probe.'/laravel-office';
            break;
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
