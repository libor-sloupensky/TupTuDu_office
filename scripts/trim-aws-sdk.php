<?php

/**
 * Ořeže AWS SDK jen na služby, které aplikace používá.
 *
 * aws/aws-sdk-php veze popisy i klienty všech ~420 služeb — 50 MB v tisících
 * souborů. Používáme z toho S3 (úložiště dokladů) a Textract (OCR), zbytek jen
 * prodlužuje SFTP přenos při deployi (nahrání celého vendoru trvalo 20 minut).
 * Spouští se z composeru (post-autoload-dump), takže se ořez zopakuje po každé
 * instalaci.
 *
 * Přidáváš další AWS službu? Dopiš ji do obou seznamů níž, jinak SDK při prvním
 * volání spadne na chybějícím popisu API nebo na nenalezené třídě klienta.
 */

// Adresáře v src/data/ — popisy API (malá písmena)
$ponechatData = [
    's3',        // úložiště dokladů
    'textract',  // OCR
    'sts',       // dočasné credentials
    'sso',
    'ssooidc',
];

// Jmenné prostory v src/ — třídy klientů
$ponechatKlienty = [
    'S3',
    'Textract',
    'Sts',
    'SSO',
    'SSOOIDC',
];

$srcDir = __DIR__ . '/../vendor/aws/aws-sdk-php/src';
$dataDir = $srcDir . '/data';

if (!is_dir($dataDir)) {
    return;
}

function smazatRekurzivne(string $cesta): void
{
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($cesta, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($it as $polozka) {
        $polozka->isDir() ? @rmdir($polozka->getPathname()) : @unlink($polozka->getPathname());
    }

    @rmdir($cesta);
}

/**
 * Adresář služby poznáme podle toho, že obsahuje třídu klienta. Adresáře
 * infrastruktury SDK (Api, Credentials, Signature, …) ji nemají a musí zůstat.
 */
function jeSluzba(string $cesta): bool
{
    foreach (glob($cesta . '/*Client.php') ?: [] as $_) {
        return true;
    }

    return false;
}

$smazanoDat = 0;

foreach (new DirectoryIterator($dataDir) as $polozka) {
    if ($polozka->isDot() || !$polozka->isDir()) {
        continue;
    }

    if (in_array($polozka->getFilename(), $ponechatData, true)) {
        continue;
    }

    smazatRekurzivne($polozka->getPathname());
    $smazanoDat++;
}

$smazanoKlientu = 0;

foreach (new DirectoryIterator($srcDir) as $polozka) {
    if ($polozka->isDot() || !$polozka->isDir() || $polozka->getFilename() === 'data') {
        continue;
    }

    if (in_array($polozka->getFilename(), $ponechatKlienty, true)) {
        continue;
    }

    if (!jeSluzba($polozka->getPathname())) {
        continue;
    }

    smazatRekurzivne($polozka->getPathname());
    $smazanoKlientu++;
}

echo "trim-aws-sdk: odstraněno {$smazanoDat} popisů API a {$smazanoKlientu} klientů nepoužívaných služeb\n";
