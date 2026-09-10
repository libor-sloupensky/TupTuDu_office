<?php

namespace Tests\Feature;

use App\Models\Doklad;
use App\Models\Firma;
use App\Models\Partner;
use App\Models\User;
use App\Support\OsobniProstor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Osobní prostor pro soukromé doklady.
 *
 * Uvnitř je to řádek v `sys_firmy` s vlastním kódem místo IČO. Testy hlídají
 * hlavně to, že se nechová jako firma tam, kde by neměl — nedá se napojit na
 * partnera, nedá se přidat účetní jako klient a nevidí ho nikdo než vlastník.
 */
class OsobniProstorTest extends TestCase
{
    use RefreshDatabase;

    private function uzivatel(string $email = 'jan@example.com'): User
    {
        $user = User::create([
            'jmeno' => 'Jan', 'prijmeni' => 'Novak',
            'email' => $email, 'password' => 'heslo12345',
        ]);
        $user->markEmailAsVerified();

        return $user;
    }

    public function test_kod_se_nemuze_potkat_s_icem(): void
    {
        $kod = OsobniProstor::vygenerujKod();

        $this->assertSame(10, strlen($kod), 'IČO má osm míst, kód musí být jinak dlouhý.');
        $this->assertTrue(OsobniProstor::jeKod($kod));
        $this->assertFalse(OsobniProstor::jeKod('69710198'));
        $this->assertStringStartsWith('OS', $kod);
    }

    public function test_zalozi_se_jednou_a_je_zdarma(): void
    {
        $user = $this->uzivatel();

        $prvni = OsobniProstor::zajisti($user);
        $druhy = OsobniProstor::zajisti($user);

        $this->assertSame($prvni->ico, $druhy->ico);
        $this->assertSame(1, Firma::where('je_osobni', true)->count());
        $this->assertTrue($prvni->jeOsobni());
        $this->assertSame('vycteni', $prvni->uroven_zpracovani);
        $this->assertSame($user->id, $prvni->vlastnik_user_id);
    }

    public function test_ma_vlastni_adresu_pro_doklady_a_kategorie(): void
    {
        $prostor = OsobniProstor::zajisti($this->uzivatel());

        $this->assertSame($prostor->ico . '@tuptudu.cz', $prostor->email_doklady);
        $this->assertSame(15, $prostor->kategorie()->count());
    }

    public function test_vlastnik_k_nemu_ma_pristup_a_nikdo_jiny(): void
    {
        $vlastnik = $this->uzivatel();
        $cizi = $this->uzivatel('petr@example.com');

        $prostor = OsobniProstor::zajisti($vlastnik);

        $this->assertContains($prostor->ico, $vlastnik->dostupneIco());
        $this->assertNotContains($prostor->ico, $cizi->dostupneIco());
    }

    public function test_vypnuty_prostor_zmizi_z_nabidky_ale_doklady_zustanou(): void
    {
        $user = $this->uzivatel();
        $prostor = OsobniProstor::zajisti($user);

        Doklad::create([
            'firma_ico' => $prostor->ico,
            'nazev_souboru' => 'uctenka.pdf',
            'cesta_souboru' => 'doklady/uctenka.pdf',
            'hash_souboru' => hash('sha256', 'x'),
            'stav' => 'ulozeno',
        ]);

        OsobniProstor::prepni($user, false);

        $this->assertNotContains($prostor->ico, $user->fresh()->dostupneIco());
        $this->assertSame(1, Doklad::where('firma_ico', $prostor->ico)->count());

        OsobniProstor::prepni($user, true);
        $this->assertContains($prostor->ico, $user->fresh()->dostupneIco());
    }

    public function test_po_prihlaseni_se_upredostatni_skutecna_firma(): void
    {
        $user = $this->uzivatel();
        $firma = Firma::create(['ico' => '10000001', 'nazev' => 'Klient s.r.o.']);
        $user->firmy()->attach($firma->ico, ['role' => 'firma', 'interni_role' => 'superadmin']);
        OsobniProstor::zajisti($user);

        // Bez vybrané firmy má middleware sáhnout po firemní, ne po osobní.
        $this->actingAs($user)->get('/doklady')->assertOk();

        $this->assertSame($firma->ico, $user->fresh()->aktivniFirma()->ico);
    }

    public function test_uzivatel_bez_firmy_se_dostane_ke_svym_dokladum(): void
    {
        $user = $this->uzivatel();
        OsobniProstor::zajisti($user);

        $this->actingAs($user)->get('/doklady')->assertOk();
    }

    public function test_partner_si_osobni_prostor_nemuze_napojit(): void
    {
        $partner = new Partner(['nazev' => 'Portál', 'aktivni' => true]);
        $partner->api_klic_hash = str_repeat('0', 64);
        $partner->save();
        $klic = $partner->vygenerujApiKlic();

        $prostor = OsobniProstor::zajisti($this->uzivatel());

        $this->postJson('/api/partner/firmy', ['ico' => $prostor->ico], [
            'Authorization' => 'Bearer ' . $klic,
        ])->assertStatus(422);
    }

    public function test_stranka_uctu_ukaze_adresu_a_prepinac(): void
    {
        $user = $this->uzivatel();

        $odpoved = $this->actingAs($user)->get('/ucet')->assertOk();
        $prostor = OsobniProstor::proUzivatele($user);

        $this->assertNotNull($prostor, 'Osobní prostor se má založit při prvním zobrazení účtu.');
        $odpoved->assertSee($prostor->email_doklady);
    }

    public function test_prepinac_v_uctu_funguje(): void
    {
        $user = $this->uzivatel();

        $this->actingAs($user)
            ->postJson('/ucet/osobni', ['zapnuto' => 0])
            ->assertOk()
            ->assertJsonPath('zapnuto', false);

        $this->assertFalse(OsobniProstor::proUzivatele($user)->osobni_aktivni);
    }
}
