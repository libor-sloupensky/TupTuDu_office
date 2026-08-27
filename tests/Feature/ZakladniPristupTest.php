<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ověří, že aplikace nabootuje a že se nepřihlášený člověk nikam nedostane.
 */
class ZakladniPristupTest extends TestCase
{
    use RefreshDatabase;

    public function test_nepřihlaseny_navstevnik_konci_na_prihlaseni(): void
    {
        $this->get('/')->assertRedirect('/prihlaseni');
        $this->get('/doklady')->assertRedirect('/prihlaseni');
        $this->get('/nastaveni')->assertRedirect('/prihlaseni');
    }

    public function test_verejne_stranky_jsou_dostupne(): void
    {
        $this->get('/prihlaseni')->assertOk();
        $this->get('/registrace')->assertOk();
        $this->get('/privacy')->assertOk();
    }

    public function test_servisni_routy_bez_platneho_tokenu_neexistuji(): void
    {
        config(['services.servisni_token' => 'spravny-token']);

        $this->get('/cron/spatny-token')->assertNotFound();
        $this->get('/cron-drive/spatny-token')->assertNotFound();
        $this->get('/deploy-migrace/spatny-token')->assertNotFound();
    }

    public function test_bez_nastaveneho_tokenu_se_servisni_routy_nedaji_zavolat(): void
    {
        // Prázdný token v konfiguraci nesmí znamenat „projde cokoli".
        config(['services.servisni_token' => '']);

        $this->get('/cron/')->assertNotFound();
        $this->get('/cron/cokoli')->assertNotFound();
    }
}
