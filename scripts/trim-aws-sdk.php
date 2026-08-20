<?php

/**
 * Ořeže datové definice AWS SDK jen na služby, které aplikace používá.
 *
 * aws/aws-sdk-php veze popisy všech ~420 služeb — 43 MB v 1677 souborech.
 * Používáme z toho S3 (úložiště dokladů) a Textract (OCR), zbytek jen
 * prodlužuje SFTP přenos při deployi. Spouští se z composeru
 * (post-autoload-dump), takže se ořez zopakuje po každé instalaci.
 *
 * Přidáváš další AWS službu? Dopiš její adresář do $ponechat, jinak
 * SDK při prvním volání spadne na chybějícím popisu API.
 */

$ponechat = [
    's3',        // úložiště dokladů
    'textract',  // OCR
    'sts',       // dočasné credentials
    'sso',
    'ssooidc',
];

$dataDir = __DIR__ . '/../vendor/aws/aws-sdk-php/src/data';

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

$smazano = 0;

foreach (new DirectoryIterator($dataDir) as $polozka) {
    if ($polozka->isDot() || !$polozka->isDir()) {
        continue;
    }

    if (in_array($polozka->getFilename(), $ponechat, true)) {
        continue;
    }

    smazatRekurzivne($polozka->getPathname());
    $smazano++;
}

echo "trim-aws-sdk: odstraněno {$smazano} nepoužívaných služeb AWS SDK\n";
