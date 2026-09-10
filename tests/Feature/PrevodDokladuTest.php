<?php

namespace Tests\Feature;

use App\Models\Doklad;
use App\Models\Firma;
use App\Models\Kategorie;
use App\Models\User;
use App\Support\OsobniProstor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Přesun dokladu mezi účty téhož člověka.
 *
 * Vytěžená data zůstávají; přepočítat se musí to, co záviselo na firmě.
 */
class PrevodDokladuTest extends TestCase
{
    use RefreshDatabase;

    private Firma $prvni;
    private Firma $druha;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('s3');

        $this->prvni = Firma::create(['ico' => '10000001', 'nazev' => 'Prvni s.r.o.']);
        $this->druha = Firma::create(['ico' => '20000002', 'nazev' => 'Druha s.r.o.']);

        $this->user = User::create([
            'jmeno' => 'Jan', 'prijmeni' => 'Novak',
            'email' => 'jan@example.com', 'password' => 'heslo12345',
        ]);
        $this->user->markEmailAsVerified();
        $this->user->firmy()->attach($this->prvni->ico, ['role' => 'firma', 'interni_role' => 'superadmin']);
        $this->user->firmy()->attach($this->druha->ico, ['role' => 'firma', 'interni_role' => 'superadmin']);
    }

    private function doklad(array $navic = []): Doklad
    {
        $cesta = 'doklady/' . $this->prvni->ico . '/2026-09/2026-09-01_1.pdf';
        Storage::disk('s3')->put($cesta, 'obsah');

        return Doklad::create(array_merge([
            'firma_ico' => $this->prvni->ico,
            'nazev_souboru' => 'faktura.pdf',
            'cesta_souboru' => $cesta,
            'hash_souboru' => hash('sha256', uniqid('', true)),
            'stav' => 'dokonceno',
            'nahral' => $this->user->email,
            'dodavatel_nazev' => 'Alza',
            'castka_celkem' => 1210.50,
        ], $navic));
    }

    private function prihlasen()
    {
        return $this->actingAs($this->user)->withSession(['aktivni_firma_ico' => $this->prvni->ico]);
    }

    public function test_prevod_zachova_vytezena_data(): void
    {
        $doklad = $this->doklad(['cislo_dokladu' => 'FV-1', 'raw_text' => 'nějaký text']);

        $this->prihlasen()
            ->postJson("/doklady/{$doklad->id}/prevest", ['firma_ico' => $this->druha->ico])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $po = $doklad->fresh();

        $this->assertSame($this->druha->ico, $po->firma_ico);
        $this->assertSame('Alza', $po->dodavatel_nazev);
        $this->assertSame('FV-1', $po->cislo_dokladu);
        $this->assertSame('nějaký text', $po->raw_text);
        $this->assertSame('1210.50', $po->castka_celkem);
    }

    public function test_soubor_se_presune_pod_cilovou_firmu(): void
    {
        $doklad = $this->doklad();
        $puvodni = $doklad->cesta_souboru;

        $this->prihlasen()
            ->postJson("/doklady/{$doklad->id}/prevest", ['firma_ico' => $this->druha->ico])
            ->assertOk();

        $nova = $doklad->fresh()->cesta_souboru;

        $this->assertStringContainsString('doklady/' . $this->druha->ico . '/', $nova);
        Storage::disk('s3')->assertExists($nova);
        Storage::disk('s3')->assertMissing($puvodni);
    }

    public function test_adresat_se_posoudi_znovu(): void
    {
        // Doklad vystavený první firmě nesmí u druhé zůstat jako ověřený.
        $doklad = $this->doklad([
            'odberatel_ico' => $this->prvni->ico,
            'adresni' => true,
            'overeno_adresat' => true,
        ]);

        $this->prihlasen()
            ->postJson("/doklady/{$doklad->id}/prevest", ['firma_ico' => $this->druha->ico])
            ->assertOk();

        $this->assertFalse((bool) $doklad->fresh()->overeno_adresat);
    }

    public function test_neznama_kategorie_se_zahodi_a_znama_zustane(): void
    {
        Kategorie::create(['firma_ico' => $this->druha->ico, 'nazev' => 'Doprava', 'poradi' => 1]);

        $zustane = $this->doklad(['kategorie' => 'Doprava']);
        $zmizi = $this->doklad(['kategorie' => 'Reklama']);

        $this->prihlasen()->postJson("/doklady/{$zustane->id}/prevest", ['firma_ico' => $this->druha->ico])->assertOk();
        $this->prihlasen()->postJson("/doklady/{$zmizi->id}/prevest", ['firma_ico' => $this->druha->ico])->assertOk();

        $this->assertSame('Doprava', $zustane->fresh()->kategorie);
        $this->assertNull($zmizi->fresh()->kategorie);
    }

    public function test_zaloha_na_disk_se_spusti_znovu(): void
    {
        $doklad = $this->doklad([
            'google_drive_nahrano_at' => now(),
            'google_drive_file_id' => 'abc123',
        ]);

        $this->prihlasen()
            ->postJson("/doklady/{$doklad->id}/prevest", ['firma_ico' => $this->druha->ico])
            ->assertOk();

        $po = $doklad->fresh();

        $this->assertNull($po->google_drive_nahrano_at);
        $this->assertNull($po->google_drive_file_id);
    }

    public function test_cizi_doklad_prevest_nejde(): void
    {
        $doklad = $this->doklad(['nahral' => 'nekdo.jiny@example.com']);

        $this->prihlasen()
            ->postJson("/doklady/{$doklad->id}/prevest", ['firma_ico' => $this->druha->ico])
            ->assertForbidden();

        $this->assertSame($this->prvni->ico, $doklad->fresh()->firma_ico);
    }

    public function test_na_cizi_ucet_prevest_nejde(): void
    {
        Firma::create(['ico' => '30000003', 'nazev' => 'Cizi s.r.o.']);
        $doklad = $this->doklad();

        $this->prihlasen()
            ->postJson("/doklady/{$doklad->id}/prevest", ['firma_ico' => '30000003'])
            ->assertForbidden();

        $this->assertSame($this->prvni->ico, $doklad->fresh()->firma_ico);
    }

    public function test_prevod_do_osobnich_dokladu(): void
    {
        $osobni = OsobniProstor::zajisti($this->user);
        $doklad = $this->doklad();

        $this->prihlasen()
            ->postJson("/doklady/{$doklad->id}/prevest", ['firma_ico' => $osobni->ico])
            ->assertOk();

        $this->assertSame($osobni->ico, $doklad->fresh()->firma_ico);
    }

    public function test_prevod_na_tutez_firmu_se_odmitne(): void
    {
        $doklad = $this->doklad();

        $this->prihlasen()
            ->postJson("/doklady/{$doklad->id}/prevest", ['firma_ico' => $this->prvni->ico])
            ->assertStatus(422);
    }
}
