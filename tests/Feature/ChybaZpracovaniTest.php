<?php

namespace Tests\Feature;

use App\Support\ChybaZpracovani;
use Tests\TestCase;

/**
 * Technická chyba se uživateli ukazuje jako věta, ne jako odpověď API.
 */
class ChybaZpracovaniTest extends TestCase
{
    public function test_vycerpany_kredit_rekne_co_se_deje(): void
    {
        $syrova = 'Claude Vision API chyba (HTTP 400): {"type":"error","error":{"type":'
            . '"invalid_request_error","message":"Your credit balance is too low to access '
            . 'the Anthropic API. Please go to Plans & Billing to upgrade or purchase credits."}}';

        $popis = ChybaZpracovani::popis($syrova);

        $this->assertStringContainsString('kredit', $popis);
        $this->assertStringContainsString('uložený', $popis);

        // Nic z technického zápisu se k uživateli dostat nesmí.
        foreach (['HTTP', 'Anthropic', 'invalid_request_error', '{', 'API'] as $zbytek) {
            $this->assertStringNotContainsString($zbytek, $popis);
        }
    }

    public function test_pretizeni_a_timeout_maji_vlastni_vetu(): void
    {
        $this->assertStringContainsString(
            'přetížená',
            ChybaZpracovani::popis('Claude Vision API chyba (HTTP 429): rate_limit_error'),
        );
        $this->assertStringContainsString(
            'příliš dlouho',
            ChybaZpracovani::popis('cURL error 28: Operation timed out'),
        );
    }

    public function test_neznama_chyba_nevypisuje_technicky_text(): void
    {
        $popis = ChybaZpracovani::popis(new \RuntimeException('Segmentation fault at 0xDEADBEEF'));

        $this->assertStringNotContainsString('0xDEADBEEF', $popis);
        $this->assertStringContainsString('nepodařilo', $popis);
    }
}
