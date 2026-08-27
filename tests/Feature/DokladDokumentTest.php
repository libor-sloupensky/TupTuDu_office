<?php

namespace Tests\Feature;

use App\Models\Doklad;
use App\Models\Firma;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Doklad se vytěžuje, dokument se jen uloží — a vytěžit ho jde dodatečně.
 */
class DokladDokumentTest extends TestCase
{
    use RefreshDatabase;

    private Firma $firma;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('s3');

        $this->firma = Firma::create(['ico' => '10000001', 'nazev' => 'Klient s.r.o.']);
        $this->user = User::create([
            'jmeno' => 'Jan', 'prijmeni' => 'Novák',
            'email' => 'jan@example.com', 'password' => 'heslo12345',
        ]);
        $this->user->markEmailAsVerified();
        $this->user->firmy()->attach($this->firma->ico, ['role' => 'firma', 'interni_role' => 'superadmin']);
    }

    private function prihlasen()
    {
        return $this->actingAs($this->user)->withSession(['aktivni_firma_ico' => $this->firma->ico]);
    }

    public function test_nahrany_dokument_se_ulozi_a_nevytezuje(): void
    {
        // Controller rozlišuje AJAX podle X-Requested-With, ne podle Accept —
        // stejně jako ho volá prohlížeč.
        $this->prihlasen()
            ->postJson('/upload', [
                'druh' => 'dokument',
                'documents' => [UploadedFile::fake()->create('smlouva.pdf', 20, 'application/pdf')],
            ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertJsonPath('results.0.status', 'ok');

        $zaznam = Doklad::firstOrFail();

        $this->assertSame('dokument', $zaznam->druh);
        $this->assertSame('ulozeno', $zaznam->stav);
        $this->assertTrue($zaznam->jeNevytezeny());
        $this->assertNotNull($zaznam->cesta_souboru);
        Storage::disk('s3')->assertExists($zaznam->cesta_souboru);

        // Nic se nevytěžilo — pole zůstala prázdná.
        $this->assertNull($zaznam->dodavatel_nazev);
        $this->assertNull($zaznam->castka_celkem);
        $this->assertNull($zaznam->raw_ai_odpoved);
    }

    public function test_nevytezeny_zaznam_jde_vytezit_a_hotovy_uz_ne(): void
    {
        $ulozeny = Doklad::create([
            'firma_ico' => $this->firma->ico,
            'nazev_souboru' => 'smlouva.pdf',
            'cesta_souboru' => 'doklady/10000001/1_smlouva.pdf',
            'hash_souboru' => hash('sha256', 'a'),
            'stav' => 'ulozeno',
            'druh' => 'dokument',
        ]);
        $this->assertTrue($ulozeny->lzeVytezit());

        $hotovy = Doklad::create([
            'firma_ico' => $this->firma->ico,
            'nazev_souboru' => 'faktura.pdf',
            'cesta_souboru' => 'doklady/10000001/2_faktura.pdf',
            'hash_souboru' => hash('sha256', 'b'),
            'stav' => 'dokonceno',
            'druh' => 'doklad',
        ]);
        $this->assertFalse($hotovy->lzeVytezit());

        $this->prihlasen()
            ->postJson("/doklady/{$hotovy->id}/vytezit")
            ->assertStatus(422);
    }

    public function test_vytezeni_ciziho_zaznamu_neprojde(): void
    {
        $cizi = Firma::create(['ico' => '30000003', 'nazev' => 'Cizí s.r.o.']);
        $doklad = Doklad::create([
            'firma_ico' => $cizi->ico,
            'nazev_souboru' => 'smlouva.pdf',
            'cesta_souboru' => 'doklady/30000003/1_smlouva.pdf',
            'hash_souboru' => hash('sha256', 'c'),
            'stav' => 'ulozeno',
            'druh' => 'dokument',
        ]);

        $this->prihlasen()
            ->postJson("/doklady/{$doklad->id}/vytezit")
            ->assertForbidden();
    }

    public function test_chybejici_soubor_skonci_chybou_a_zaznam_zustane(): void
    {
        $doklad = Doklad::create([
            'firma_ico' => $this->firma->ico,
            'nazev_souboru' => 'smlouva.pdf',
            'cesta_souboru' => 'doklady/10000001/1_neexistuje.pdf',
            'hash_souboru' => hash('sha256', 'd'),
            'stav' => 'ulozeno',
            'druh' => 'dokument',
        ]);

        $this->prihlasen()
            ->postJson("/doklady/{$doklad->id}/vytezit")
            ->assertStatus(500)
            ->assertJsonPath('ok', false);

        $this->assertNotNull($doklad->fresh());
        $this->assertSame('ulozeno', $doklad->fresh()->stav);
    }

    public function test_bez_prepinace_se_nahrava_jako_dosud(): void
    {
        // Nahrávání bez `druh` musí zůstat dokladem — jinak by se změnilo
        // chování mobilní aplikace i e-mailového příjmu.
        $zaznam = Doklad::create([
            'firma_ico' => $this->firma->ico,
            'nazev_souboru' => 'x.pdf',
            'cesta_souboru' => 'doklady/10000001/x.pdf',
            'hash_souboru' => hash('sha256', 'e'),
            'stav' => 'ulozeno',
        ]);

        $this->assertSame('doklad', $zaznam->fresh()->druh);
    }
}
