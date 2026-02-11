<?php
echo "<pre>";
echo "Document root: " . $_SERVER['DOCUMENT_ROOT'] . "\n";
echo "__DIR__: " . __DIR__ . "\n";
echo "Script: " . $_SERVER['SCRIPT_FILENAME'] . "\n\n";

$laravelPath = __DIR__ . '/../../laravel-office';
echo "Laravel path check: " . realpath($laravelPath) . "\n";
echo "Autoload exists: " . (file_exists($laravelPath . '/vendor/autoload.php') ? 'YES' : 'NO') . "\n";
echo ".env exists: " . (file_exists($laravelPath . '/.env') ? 'YES' : 'NO') . "\n";
echo "bootstrap/app.php exists: " . (file_exists($laravelPath . '/bootstrap/app.php') ? 'YES' : 'NO') . "\n\n";

echo "Parent path check: " . realpath(__DIR__ . '/..') . "\n";
echo "Parent autoload: " . (file_exists(__DIR__ . '/../vendor/autoload.php') ? 'YES' : 'NO') . "\n";

echo "\nDirectory listing of " . realpath($laravelPath ?: __DIR__ . '/../..') . ":\n";
if (is_dir(__DIR__ . '/../..')) {
    foreach (scandir(__DIR__ . '/../..') as $f) {
        if ($f !== '.' && $f !== '..') echo "  $f\n";
    }
}
echo "</pre>";
