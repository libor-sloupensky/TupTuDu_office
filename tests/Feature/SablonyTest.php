<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * Každá Blade šablona musí jít zkompilovat do platného PHP.
 *
 * Vzniklo poté, co se v mobilním skeneru objevil název komponenty ikony
 * doslova v JavaScriptovém komentáři. Blade ho zkompiloval i tam, výsledné PHP
 * mělo nespárované `if`/`endif` a celá stránka vracela 500 — aniž by o tom dalo
 * cokoli vědět, protože ostatní stránky fungovaly dál.
 */
class SablonyTest extends TestCase
{
    public function test_vsechny_sablony_se_zkompiluji_do_platneho_php(): void
    {
        $korenViews = str_replace(DIRECTORY_SEPARATOR, '/', resource_path('views')) . '/';
        $rozbite = [];

        foreach ($this->sablony() as $cesta) {
            $docasny = tempnam(sys_get_temp_dir(), 'blade_');
            file_put_contents($docasny, Blade::compileString(file_get_contents($cesta)));

            $vystup = [];
            exec('php -l ' . escapeshellarg($docasny) . ' 2>&1', $vystup, $navrat);
            @unlink($docasny);

            if ($navrat !== 0) {
                $nazev = str_replace($korenViews, '', str_replace(DIRECTORY_SEPARATOR, '/', $cesta));
                $rozbite[] = $nazev . ' — ' . ($vystup[0] ?? 'neznámá chyba');
            }
        }

        $this->assertSame([], $rozbite, "Nezkompilovaly se tyto šablony:" . PHP_EOL . implode(PHP_EOL, $rozbite));
    }

    /** @return string[] */
    private function sablony(): array
    {
        $soubory = [];

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($it as $soubor) {
            if (str_ends_with($soubor->getFilename(), '.blade.php')) {
                $soubory[] = $soubor->getPathname();
            }
        }

        sort($soubory);

        return $soubory;
    }
}
