<?php

namespace Tests\Feature;

use App\Models\Doklad;
use App\Models\Firma;
use App\Models\User;
use App\Services\DokladProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Kde na dokladu leží hledaný výraz — podklad pro zvýraznění v náhledu.
 */
class ZvyrazneniNalezuTest extends TestCase
{
    use RefreshDatabase;

    private Firma $firma;
    private User $user;
    private Doklad $doklad;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('s3');

        $this->firma = Firma::create(['ico' => '10000001', 'nazev' => 'Klient s.r.o.']);
        $this->user = User::create([
            'jmeno' => 'Jan', 'prijmeni' => 'Novak',
            'email' => 'jan@example.com', 'password' => 'heslo12345',
        ]);
        $this->user->markEmailAsVerified();
        $this->user->firmy()->attach($this->firma->ico, ['role' => 'firma', 'interni_role' => 'superadmin']);

        $this->doklad = Doklad::create([
            'firma_ico' => $this->firma->ico,
            'nazev_souboru' => 'faktura.pdf',
            'cesta_souboru' => 'doklady/10000001/faktura.pdf',
            'hash_souboru' => hash('sha256', 'x'),
            'stav' => 'ulozeno',
        ]);
    }

    private function ulozSlova(array $slova): void
    {
        Storage::disk('s3')->put(
            DokladProcessor::cestaSlov($this->doklad->cesta_souboru),
            json_encode($slova, JSON_UNESCAPED_UNICODE),
        );
    }

    private function prihlasen()
    {
        return $this->actingAs($this->user)->withSession(['aktivni_firma_ico' => $this->firma->ico]);
    }

    public function test_vrati_pozice_hledaneho_vyrazu(): void
    {
        $this->ulozSlova([
            ['t' => 'Pneuservis', 's' => 1, 'b' => [0.1, 0.2, 0.3, 0.22]],
            ['t' => 'Brno', 's' => 1, 'b' => [0.35, 0.2, 0.45, 0.22]],
            ['t' => 'pneumatiky', 's' => 2, 'b' => [0.1, 0.5, 0.3, 0.52]],
        ]);

        $this->prihlasen()
            ->getJson("/doklady/{$this->doklad->id}/slova?q=pneu")
            ->assertOk()
            ->assertJsonPath('pocet', 2)
            ->assertJsonPath('slova.0.t', 'Pneuservis')
            ->assertJsonPath('slova.1.s', 2);
    }

    public function test_nezalezi_na_velikosti_pismen_ani_diakritice(): void
    {
        $this->ulozSlova([['t' => 'Doprava', 's' => 1, 'b' => [0.1, 0.2, 0.3, 0.22]]]);

        $this->prihlasen()
            ->getJson("/doklady/{$this->doklad->id}/slova?q=DOPRAVA")
            ->assertJsonPath('pocet', 1);

        $this->ulozSlova([['t' => 'Kancelářské', 's' => 1, 'b' => [0.1, 0.2, 0.3, 0.22]]]);

        $this->prihlasen()
            ->getJson("/doklady/{$this->doklad->id}/slova?q=kancelarske")
            ->assertJsonPath('pocet', 1);
    }

    public function test_bez_ulozenych_souradnic_to_da_najevo(): void
    {
        $this->prihlasen()
            ->getJson("/doklady/{$this->doklad->id}/slova?q=cokoli")
            ->assertOk()
            ->assertJsonPath('pocet', 0)
            ->assertJsonPath('bez_souradnic', true);
    }

    public function test_prazdny_vyraz_nic_nevraci(): void
    {
        $this->ulozSlova([['t' => 'Pneuservis', 's' => 1, 'b' => [0.1, 0.2, 0.3, 0.22]]]);

        $this->prihlasen()
            ->getJson("/doklady/{$this->doklad->id}/slova?q=")
            ->assertOk()
            ->assertJsonPath('pocet', 0);
    }

    public function test_k_cizimu_dokladu_se_souradnice_nedostanou(): void
    {
        $cizi = Firma::create(['ico' => '30000003', 'nazev' => 'Cizi s.r.o.']);
        $cizidoklad = Doklad::create([
            'firma_ico' => $cizi->ico,
            'nazev_souboru' => 'cizi.pdf',
            'cesta_souboru' => 'doklady/30000003/cizi.pdf',
            'hash_souboru' => hash('sha256', 'y'),
            'stav' => 'ulozeno',
        ]);

        $this->prihlasen()
            ->getJson("/doklady/{$cizidoklad->id}/slova?q=cokoli")
            ->assertForbidden();
    }
}
