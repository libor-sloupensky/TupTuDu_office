<?php

/**
 * Vytvoří SQL dump produkční databáze pro přenos na jiný hosting.
 *
 * Použití: php scripts/dump-db.php <host> <databáze> <uživatel> [heslo]
 * Bez hesla se vezme DB_PASSWORD z .env.
 *
 * Výsledek jde do temporary/<databáze>.sql (adresář je v .gitignore —
 * dump obsahuje osobní údaje a hashe hesel, do repozitáře nepatří).
 *
 * Provozní tabulky (cache, sessions, fronty) se přenášejí jen strukturou,
 * jejich obsah je po přesunu bezcenný a jen by nafukoval dump.
 */

$bezDat = ['cache', 'cache_locks', 'sessions', 'sys_sessions', 'jobs', 'job_batches', 'failed_jobs'];

$host = $argv[1] ?? null;
$databaze = $argv[2] ?? null;
$uzivatel = $argv[3] ?? null;
$heslo = $argv[4] ?? null;

if (!$host || !$databaze || !$uzivatel) {
    fwrite(STDERR, "Použití: php scripts/dump-db.php <host> <databáze> <uživatel> [heslo]\n");
    exit(1);
}

if ($heslo === null) {
    foreach (file(__DIR__ . '/../.env') as $radek) {
        if (str_starts_with($radek, 'DB_PASSWORD=')) {
            $heslo = trim(substr($radek, strlen('DB_PASSWORD=')));
            break;
        }
    }
}

$pdo = new PDO(
    "mysql:host={$host};dbname={$databaze};charset=utf8mb4",
    $uzivatel,
    $heslo,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 30]
);

$cil = __DIR__ . '/../temporary/' . $databaze . '.sql';

if (!is_dir(dirname($cil))) {
    mkdir(dirname($cil), 0777, true);
}

$out = fopen($cil, 'w');

fwrite($out, "-- Dump databáze {$databaze} z {$host}\n");
fwrite($out, "-- Vytvořeno: " . date('Y-m-d H:i:s') . "\n\n");
fwrite($out, "SET NAMES utf8mb4;\n");
fwrite($out, "SET FOREIGN_KEY_CHECKS = 0;\n");
fwrite($out, "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n\n");

$tabulky = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

$definice = [];

foreach ($tabulky as $tabulka) {
    $definice[$tabulka] = $pdo->query("SHOW CREATE TABLE `{$tabulka}`")->fetch(PDO::FETCH_NUM)[1];
}

$tabulky = seradPodleZavislosti($definice);
$radkuCelkem = 0;

$cizíKlice = [];

foreach ($tabulky as $tabulka) {
    [$create, $klice] = oddelCiziKlice($definice[$tabulka]);

    foreach ($klice as $klic) {
        $cizíKlice[] = "ALTER TABLE `{$tabulka}` ADD {$klic};";
    }

    fwrite($out, "\n--\n-- Tabulka {$tabulka}\n--\n\n");
    fwrite($out, "DROP TABLE IF EXISTS `{$tabulka}`;\n");
    fwrite($out, $create . ";\n\n");

    if (in_array($tabulka, $bezDat, true)) {
        fwrite($out, "-- (provozní tabulka, data se nepřenášejí)\n");
        echo str_pad($tabulka, 28), "struktura\n";
        continue;
    }

    $stmt = $pdo->query("SELECT * FROM `{$tabulka}`");
    $radku = 0;
    $davka = [];

    // Názvy sloupců si držíme z prvního řádku — po dočtení je $radek false,
    // takže z něj poslední (neúplnou) dávku odvodit nelze.
    $sloupce = [];

    while ($radek = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $sloupce = $sloupce ?: array_keys($radek);

        $hodnoty = array_map(
            fn ($h) => $h === null ? 'NULL' : $pdo->quote((string) $h),
            array_values($radek)
        );

        $davka[] = '(' . implode(',', $hodnoty) . ')';
        $radku++;

        // Po 200 řádcích zapsat, ať dump nedrží celou tabulku v paměti
        if (count($davka) >= 200) {
            zapsatDavku($out, $tabulka, $sloupce, $davka);
            $davka = [];
        }
    }

    if ($davka) {
        zapsatDavku($out, $tabulka, $sloupce, $davka);
    }

    $radkuCelkem += $radku;
    echo str_pad($tabulka, 28), $radku, " řádků\n";
}

// Cizí klíče až nakonec, kdy jsou všechna data na místě
if ($cizíKlice) {
    fwrite($out, "\n--\n-- Cizí klíče\n--\n\n");
    fwrite($out, implode("\n", $cizíKlice) . "\n");
}

fwrite($out, "\nSET FOREIGN_KEY_CHECKS = 1;\n");
fclose($out);

echo "\nHotovo: {$cil}\n";
echo 'Velikost: ' . round(filesize($cil) / 1024, 1) . " kB, řádků celkem: {$radkuCelkem}\n";

/**
 * Vyjme z CREATE TABLE definice cizích klíčů a vrátí je zvlášť.
 *
 * Cizí klíče se pak přidají až úplně na konec dumpu, po nahrání všech dat.
 * Import tím přestane záviset na tom, jestli má importér zapnuté kontroly
 * cizích klíčů — phpMyAdmin si `SET FOREIGN_KEY_CHECKS = 0` přebíjí, takže
 * jinak spadne na vkládání potomka dřív existujícího rodiče (chyba 1452)
 * i na odkazu tabulky samy na sebe (fak_doklady.duplicita_id).
 */
function oddelCiziKlice(string $create): array
{
    $radky = explode("\n", $create);
    $zbyle = [];
    $klice = [];

    foreach ($radky as $radek) {
        if (preg_match('/^\s*CONSTRAINT\s+.*FOREIGN KEY/i', $radek)) {
            $klice[] = rtrim(trim($radek), ',');
            continue;
        }

        $zbyle[] = $radek;
    }

    if (!$klice) {
        return [$create, []];
    }

    // Po odebrání klíčů může poslední řádek definice končit čárkou
    for ($i = count($zbyle) - 1; $i >= 0; $i--) {
        if (str_starts_with(ltrim($zbyle[$i]), ')')) {
            continue;
        }

        $zbyle[$i] = rtrim(rtrim($zbyle[$i]), ',');
        break;
    }

    return [implode("\n", $zbyle), $klice];
}

/**
 * Seřadí tabulky tak, aby odkazovaná tabulka vznikla dřív než ta, která na ni
 * ukazuje cizím klíčem. Bez toho import spadne na "Foreign key constraint is
 * incorrectly formed" všude, kde nejde vypnout kontrolu cizích klíčů
 * (typicky import přes phpMyAdmin, který si nastavení přebíjí).
 */
function seradPodleZavislosti(array $definice): array
{
    $zavislosti = [];

    foreach ($definice as $tabulka => $create) {
        preg_match_all('/REFERENCES\s+`([^`]+)`/i', $create, $shody);

        // Odkaz sám na sebe pořadí neovlivňuje
        $zavislosti[$tabulka] = array_values(array_unique(array_filter(
            $shody[1],
            fn ($cil) => $cil !== $tabulka && isset($definice[$cil])
        )));
    }

    $serazene = [];
    $zpracovavane = [];

    $vloz = function (string $tabulka) use (&$vloz, &$serazene, &$zpracovavane, $zavislosti): void {
        if (isset($serazene[$tabulka]) || isset($zpracovavane[$tabulka])) {
            return; // hotovo, nebo cyklus — ten vyřeší vypnuté kontroly
        }

        $zpracovavane[$tabulka] = true;

        foreach ($zavislosti[$tabulka] as $rodic) {
            $vloz($rodic);
        }

        unset($zpracovavane[$tabulka]);
        $serazene[$tabulka] = true;
    };

    foreach (array_keys($definice) as $tabulka) {
        $vloz($tabulka);
    }

    return array_keys($serazene);
}

function zapsatDavku($out, string $tabulka, array $sloupce, array $davka): void
{
    if (!$sloupce) {
        return;
    }

    $seznam = '`' . implode('`,`', $sloupce) . '`';
    fwrite($out, "INSERT INTO `{$tabulka}` ({$seznam}) VALUES\n" . implode(",\n", $davka) . ";\n");
}
