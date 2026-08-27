<?php

namespace Tests\Feature;

use App\Models\Doklad;
use App\Models\Firma;
use App\Models\UcetniVazba;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Hlídá, že se uživatel nedostane k dokladům, které mu nepatří, a že se
 * oprávnění účetní vyhodnocují podle firmy, které doklad patří — ne podle
 * toho, kterou firmu má člověk zrovna přepnutou.
 */
class PristupKDokladumTest extends TestCase
{
    use RefreshDatabase;

    private Firma $klient;
    private Firma $ucetniFirma;
    private Firma $cizi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->klient = Firma::create(['ico' => '10000001', 'nazev' => 'Klient s.r.o.']);
        $this->ucetniFirma = Firma::create(['ico' => '20000002', 'nazev' => 'Účetní s.r.o.', 'je_ucetni' => true]);
        $this->cizi = Firma::create(['ico' => '30000003', 'nazev' => 'Cizí s.r.o.']);
    }

    private function doklad(Firma $firma): Doklad
    {
        return Doklad::create([
            'firma_ico' => $firma->ico,
            'nazev_souboru' => 'faktura.pdf',
            'cesta_souboru' => 'doklady/faktura.pdf',
            'hash_souboru' => hash('sha256', $firma->ico),
            'stav' => 'dokonceno',
            'zdroj' => 'web',
            'poznamka' => 'původní',
        ]);
    }

    private function uzivatel(Firma $firma, string $role = 'firma'): User
    {
        $user = User::create([
            'jmeno' => 'Test',
            'prijmeni' => 'Uživatel',
            'email' => 'u' . $firma->ico . '@example.com',
            'password' => 'heslo12345',
        ]);
        // email_verified_at není ve $fillable, jinak by ho create() zahodil
        // a middleware `verified` by test odklonil na ověřovací stránku.
        $user->markEmailAsVerified();
        $user->firmy()->attach($firma->ico, ['role' => $role, 'interni_role' => 'superadmin']);

        return $user;
    }

    private function vazba(bool $upravovat, bool $mazat): UcetniVazba
    {
        return UcetniVazba::create([
            'ucetni_ico' => $this->ucetniFirma->ico,
            'klient_ico' => $this->klient->ico,
            'stav' => 'schvaleno',
            'perm_vkladat' => true,
            'perm_upravovat' => $upravovat,
            'perm_mazat' => $mazat,
        ]);
    }

    public function test_uzivatel_nevidi_doklad_ciziho_ica(): void
    {
        $user = $this->uzivatel($this->klient);
        $doklad = $this->doklad($this->cizi);

        $this->actingAs($user)
            ->withSession(['aktivni_firma_ico' => $this->klient->ico])
            ->get("/doklady/{$doklad->id}")
            ->assertForbidden();
    }

    public function test_vlastnik_svuj_doklad_upravit_muze(): void
    {
        $user = $this->uzivatel($this->klient);
        $doklad = $this->doklad($this->klient);

        $this->actingAs($user)
            ->withSession(['aktivni_firma_ico' => $this->klient->ico])
            ->patch("/doklady/{$doklad->id}", ['field' => 'poznamka', 'value' => 'změněno'])
            ->assertOk();

        $this->assertSame('změněno', $doklad->fresh()->poznamka);
    }

    public function test_ucetni_bez_opravneni_doklad_klienta_neupravi(): void
    {
        $this->vazba(upravovat: false, mazat: false);
        $user = $this->uzivatel($this->ucetniFirma, 'ucetni');
        $doklad = $this->doklad($this->klient);

        $this->actingAs($user)
            ->withSession(['aktivni_firma_ico' => $this->klient->ico])
            ->patch("/doklady/{$doklad->id}", ['field' => 'poznamka', 'value' => 'změněno'])
            ->assertForbidden();

        $this->assertSame('původní', $doklad->fresh()->poznamka);
    }

    /**
     * Jádro problému: oprávnění se dřív odvozovala od přepnuté firmy. Účetní,
     * která zůstala na své vlastní firmě, tak prošla bez jakékoli kontroly —
     * stačilo znát ID dokladu.
     */
    public function test_ucetni_neobejde_opravneni_tim_ze_zustane_na_sve_firme(): void
    {
        $this->vazba(upravovat: false, mazat: false);
        $user = $this->uzivatel($this->ucetniFirma, 'ucetni');
        $doklad = $this->doklad($this->klient);

        $this->actingAs($user)
            ->withSession(['aktivni_firma_ico' => $this->ucetniFirma->ico])
            ->patch("/doklady/{$doklad->id}", ['field' => 'poznamka', 'value' => 'změněno'])
            ->assertForbidden();

        $this->assertSame('původní', $doklad->fresh()->poznamka);
    }

    public function test_ucetni_nesmaze_doklad_klienta_z_vlastni_firmy(): void
    {
        $this->vazba(upravovat: true, mazat: false);
        $user = $this->uzivatel($this->ucetniFirma, 'ucetni');
        $doklad = $this->doklad($this->klient);

        $this->actingAs($user)
            ->withSession(['aktivni_firma_ico' => $this->ucetniFirma->ico])
            ->delete("/doklady/{$doklad->id}")
            ->assertForbidden();

        $this->assertNotNull($doklad->fresh());
    }

    public function test_ucetni_s_opravnenim_doklad_klienta_upravi(): void
    {
        $this->vazba(upravovat: true, mazat: false);
        $user = $this->uzivatel($this->ucetniFirma, 'ucetni');
        $doklad = $this->doklad($this->klient);

        $this->actingAs($user)
            ->withSession(['aktivni_firma_ico' => $this->klient->ico])
            ->patch("/doklady/{$doklad->id}", ['field' => 'poznamka', 'value' => 'změněno'])
            ->assertOk();

        $this->assertSame('změněno', $doklad->fresh()->poznamka);
    }
}
