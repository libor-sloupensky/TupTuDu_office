<?php

namespace Tests\Feature;

use App\Models\Doklad;
use App\Models\Firma;
use App\Models\FirmaPartner;
use App\Models\Partner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Partnerské API: co partner smí, a hlavně co nesmí.
 */
class PartnerApiTest extends TestCase
{
    use RefreshDatabase;

    private Partner $partner;
    private string $klic;

    protected function setUp(): void
    {
        parent::setUp();

        $this->partner = new Partner(['nazev' => 'Účetní portál', 'aktivni' => true]);
        $this->partner->api_klic_hash = str_repeat('0', 64);
        $this->partner->save();
        $this->klic = $this->partner->vygenerujApiKlic();
    }

    private function jakoPartner(?string $klic = null): array
    {
        return ['Authorization' => 'Bearer ' . ($klic ?? $this->klic)];
    }

    private function napojenaFirma(string $ico, ?Partner $partner = null): Firma
    {
        $firma = Firma::create(['ico' => $ico, 'nazev' => 'Firma ' . $ico]);
        FirmaPartner::create([
            'firma_ico' => $ico,
            'partner_id' => ($partner ?? $this->partner)->id,
            'napojeno_at' => now(),
        ]);

        return $firma;
    }

    public function test_bez_klice_api_neodpovi(): void
    {
        $this->getJson('/api/partner/firmy')->assertUnauthorized();
        $this->getJson('/api/partner/firmy', ['Authorization' => 'Bearer nesmysl'])->assertUnauthorized();
    }

    public function test_neaktivni_partner_se_nedostane_dovnitr(): void
    {
        $this->partner->update(['aktivni' => false]);

        $this->getJson('/api/partner/firmy', $this->jakoPartner())->assertUnauthorized();
    }

    public function test_partner_vidi_jen_sve_firmy(): void
    {
        $jiny = new Partner(['nazev' => 'Jiný partner', 'aktivni' => true]);
        $jiny->api_klic_hash = str_repeat('1', 64);
        $jiny->save();

        $this->napojenaFirma('10000001');
        $this->napojenaFirma('20000002', $jiny);

        $this->getJson('/api/partner/firmy', $this->jakoPartner())
            ->assertOk()
            ->assertJsonCount(1, 'firmy')
            ->assertJsonPath('firmy.0.ico', '10000001');
    }

    public function test_detail_cizi_firmy_partner_nedostane(): void
    {
        $jiny = new Partner(['nazev' => 'Jiný partner', 'aktivni' => true]);
        $jiny->api_klic_hash = str_repeat('1', 64);
        $jiny->save();
        $this->napojenaFirma('20000002', $jiny);

        $this->getJson('/api/partner/firmy/20000002', $this->jakoPartner())->assertNotFound();
    }

    public function test_firmu_jineho_partnera_nelze_prevzit(): void
    {
        $jiny = new Partner(['nazev' => 'Jiný partner', 'aktivni' => true]);
        $jiny->api_klic_hash = str_repeat('1', 64);
        $jiny->save();
        $this->napojenaFirma('20000002', $jiny);

        $this->postJson('/api/partner/firmy', ['ico' => '20000002'], $this->jakoPartner())
            ->assertStatus(409);

        // Původní napojení zůstalo nedotčené.
        $this->assertSame(1, FirmaPartner::where('firma_ico', '20000002')->whereNull('odpojeno_at')->count());
        $this->assertSame($jiny->id, FirmaPartner::where('firma_ico', '20000002')->first()->partner_id);
    }

    public function test_opakovane_napojeni_vlastni_firmy_projde_bez_duplicity(): void
    {
        $this->napojenaFirma('10000001');

        $this->postJson('/api/partner/firmy', ['ico' => '10000001'], $this->jakoPartner())
            ->assertOk()
            ->assertJsonPath('ico', '10000001');

        $this->assertSame(1, FirmaPartner::where('firma_ico', '10000001')->count());
    }

    public function test_odpojeni_nechava_firmu_i_historii(): void
    {
        $this->napojenaFirma('10000001');

        $this->deleteJson('/api/partner/firmy/10000001', [], $this->jakoPartner())->assertOk();

        $this->assertNotNull(Firma::find('10000001'));
        $this->assertNotNull(FirmaPartner::where('firma_ico', '10000001')->first()->odpojeno_at);
        $this->getJson('/api/partner/firmy', $this->jakoPartner())->assertJsonCount(0, 'firmy');
    }

    public function test_po_odpojeni_muze_firmu_prevzit_jiny_partner(): void
    {
        $this->napojenaFirma('10000001');
        $this->deleteJson('/api/partner/firmy/10000001', [], $this->jakoPartner())->assertOk();

        $jiny = new Partner(['nazev' => 'Jiný partner', 'aktivni' => true]);
        $jiny->api_klic_hash = str_repeat('1', 64);
        $jiny->save();
        $klicJineho = $jiny->vygenerujApiKlic();

        $this->postJson('/api/partner/firmy', ['ico' => '10000001'], $this->jakoPartner($klicJineho))
            ->assertCreated();

        $this->assertSame(2, FirmaPartner::where('firma_ico', '10000001')->count());
    }

    /**
     * Jádro celého návrhu: partner je obchodní kanál, ne správce dat.
     */
    public function test_partner_se_nedostane_k_dokladum_sve_firmy(): void
    {
        $firma = $this->napojenaFirma('10000001');
        $doklad = Doklad::create([
            'firma_ico' => $firma->ico,
            'nazev_souboru' => 'faktura.pdf',
            'cesta_souboru' => 'doklady/faktura.pdf',
            'hash_souboru' => hash('sha256', 'x'),
            'stav' => 'dokonceno',
            'zdroj' => 'web',
        ]);

        // Webové routy klíč partnera neznají — skončí na přihlášení.
        $this->get("/doklady/{$doklad->id}", $this->jakoPartner())->assertRedirect('/prihlaseni');
        $this->get('/doklady', $this->jakoPartner())->assertRedirect('/prihlaseni');

        // A v partnerském API žádná cesta k dokladům nevede.
        $this->getJson("/api/partner/firmy/{$firma->ico}/doklady", $this->jakoPartner())->assertNotFound();

        // Detail firmy vrací přesně tenhle výčet a nic navíc. Kdyby někdo do
        // odpovědi časem přidal další pole, ať se u toho musí zastavit.
        $odpoved = $this->getJson("/api/partner/firmy/{$firma->ico}", $this->jakoPartner())->assertOk();

        $this->assertSame(
            ['ico', 'nazev', 'email_pro_doklady', 'napojeno_at', 'ma_uzivatele'],
            array_keys($odpoved->json()),
        );
        $this->assertStringNotContainsString('faktura.pdf', $odpoved->getContent());
    }
}
