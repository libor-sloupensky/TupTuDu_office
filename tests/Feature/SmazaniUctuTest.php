<?php

namespace Tests\Feature;

use App\Models\Doklad;
use App\Models\Firma;
use App\Models\Pozvani;
use App\Models\User;
use App\Services\SmazaniUctu;
use App\Support\OsobniProstor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Smazání účtu — vyžaduje ho Google Play u aplikací se zakládáním účtu.
 *
 * Testy hlídají hranici: co zmizet má, a hlavně co zmizet nesmí. Operace je
 * nevratná, takže chyba na téhle straně je nejdražší, jaká v aplikaci může být.
 */
class SmazaniUctuTest extends TestCase
{
    use RefreshDatabase;

    private function uzivatel(string $email): User
    {
        $user = User::create([
            'jmeno' => 'Jan', 'prijmeni' => 'Novak',
            'email' => $email, 'password' => 'heslo12345',
        ]);
        $user->markEmailAsVerified();

        return $user;
    }

    private function doklad(Firma $firma, string $klic): Doklad
    {
        $cesta = 'doklady/' . $firma->ico . '/' . $klic . '.pdf';
        Storage::disk('s3')->put($cesta, 'obsah');
        Storage::disk('s3')->put($cesta . '.slova.json', '[]');

        return Doklad::create([
            'firma_ico' => $firma->ico,
            'nazev_souboru' => 'f.pdf',
            'cesta_souboru' => $cesta,
            'hash_souboru' => hash('sha256', $klic),
            'stav' => 'dokonceno',
        ]);
    }

    public function test_ucet_i_osobni_doklady_zmizi_vcetne_souboru(): void
    {
        Storage::fake('s3');

        $user = $this->uzivatel('jan@example.com');
        $osobni = OsobniProstor::zajisti($user);
        $doklad = $this->doklad($osobni, 'osobni');

        (new SmazaniUctu())->smaz($user);

        $this->assertNull(User::find($user->id));
        $this->assertNull(Firma::find($osobni->ico));
        $this->assertNull(Doklad::find($doklad->id));
        Storage::disk('s3')->assertMissing($doklad->cesta_souboru);
        Storage::disk('s3')->assertMissing($doklad->cesta_souboru . '.slova.json');
    }

    public function test_firma_s_dalsimi_lidmi_zustane_i_s_doklady(): void
    {
        Storage::fake('s3');

        $odchazi = $this->uzivatel('jan@example.com');
        $zustava = $this->uzivatel('petr@example.com');

        $firma = Firma::create(['ico' => '10000001', 'nazev' => 'Spolecna s.r.o.']);
        $firma->users()->attach($odchazi->id, ['role' => 'firma', 'interni_role' => 'superadmin']);
        $firma->users()->attach($zustava->id, ['role' => 'firma', 'interni_role' => 'spravce']);

        $doklad = $this->doklad($firma, 'firemni');

        (new SmazaniUctu())->smaz($odchazi);

        $this->assertNotNull(Firma::find($firma->ico), 'Firma s dalšími lidmi nesmí zmizet.');
        $this->assertNotNull(Doklad::find($doklad->id));
        Storage::disk('s3')->assertExists($doklad->cesta_souboru);
        $this->assertSame(1, $firma->users()->count());
    }

    public function test_po_odchodu_jedineho_spravce_nekdo_spravu_prevezme(): void
    {
        $odchazi = $this->uzivatel('jan@example.com');
        $zustava = $this->uzivatel('petr@example.com');

        $firma = Firma::create(['ico' => '10000001', 'nazev' => 'Spolecna s.r.o.']);
        $firma->users()->attach($odchazi->id, ['role' => 'firma', 'interni_role' => 'superadmin']);
        $firma->users()->attach($zustava->id, ['role' => 'firma', 'interni_role' => 'spravce']);

        (new SmazaniUctu())->smaz($odchazi);

        $this->assertTrue(
            $zustava->fresh()->jeSuperadmin($firma->ico),
            'Firma nesmí zůstat bez správce.',
        );
    }

    public function test_firma_kde_byl_posledni_zmizi_i_s_doklady(): void
    {
        Storage::fake('s3');

        $user = $this->uzivatel('jan@example.com');
        $firma = Firma::create(['ico' => '10000001', 'nazev' => 'Jen moje s.r.o.']);
        $firma->users()->attach($user->id, ['role' => 'firma', 'interni_role' => 'superadmin']);
        $doklad = $this->doklad($firma, 'sam');

        (new SmazaniUctu())->smaz($user);

        $this->assertNull(Firma::find($firma->ico));
        $this->assertNull(Doklad::find($doklad->id));
        Storage::disk('s3')->assertMissing($doklad->cesta_souboru);
    }

    public function test_pozvanky_na_muj_email_zmizi_cizi_zustanou(): void
    {
        $user = $this->uzivatel('jan@example.com');
        $jiny = $this->uzivatel('petr@example.com');

        $firma = Firma::create(['ico' => '10000001', 'nazev' => 'Spolecna s.r.o.']);
        $firma->users()->attach($jiny->id, ['role' => 'firma', 'interni_role' => 'superadmin']);
        $firma->users()->attach($user->id, ['role' => 'firma', 'interni_role' => 'spravce']);

        Pozvani::create([
            'firma_ico' => $firma->ico, 'email' => 'jan@example.com',
            'token' => str_repeat('a', 64), 'expires_at' => now()->addDays(7),
        ]);
        Pozvani::create([
            'firma_ico' => $firma->ico, 'email' => 'nekdo@example.com',
            'token' => str_repeat('b', 64), 'expires_at' => now()->addDays(7),
        ]);

        (new SmazaniUctu())->smaz($user);

        $this->assertSame(0, Pozvani::where('email', 'jan@example.com')->count());
        $this->assertSame(1, Pozvani::where('email', 'nekdo@example.com')->count());
    }

    public function test_zaznamy_o_nakladech_zustavaji(): void
    {
        $user = $this->uzivatel('jan@example.com');
        $firma = Firma::create(['ico' => '10000001', 'nazev' => 'Jen moje s.r.o.']);
        $firma->users()->attach($user->id, ['role' => 'firma', 'interni_role' => 'superadmin']);

        DB::table('sys_ai_volani')->insert([
            'firma_ico' => $firma->ico, 'sluzba' => 'claude', 'cena_usd' => 0.01, 'vytvoreno' => now(),
        ]);

        (new SmazaniUctu())->smaz($user);

        // Nejsou osobní údaj a bez nich by se rozpadla čísla za uzavřené měsíce.
        $this->assertSame(1, DB::table('sys_ai_volani')->count());
    }

    public function test_prehled_rekne_co_zmizi_a_co_zustane(): void
    {
        $user = $this->uzivatel('jan@example.com');
        $jiny = $this->uzivatel('petr@example.com');

        $sam = Firma::create(['ico' => '10000001', 'nazev' => 'Jen moje s.r.o.']);
        $sam->users()->attach($user->id, ['role' => 'firma', 'interni_role' => 'superadmin']);

        $spolecna = Firma::create(['ico' => '20000002', 'nazev' => 'Spolecna s.r.o.']);
        $spolecna->users()->attach($user->id, ['role' => 'firma', 'interni_role' => 'spravce']);
        $spolecna->users()->attach($jiny->id, ['role' => 'firma', 'interni_role' => 'superadmin']);

        $prehled = (new SmazaniUctu())->prehled($user);

        $this->assertSame(['Jen moje s.r.o.'], array_column($prehled['firmy_zmizi'], 'nazev'));
        $this->assertSame(['Spolecna s.r.o.'], $prehled['firmy_zustanou']);
    }

    public function test_bez_spravneho_emailu_se_nesmaze_nic(): void
    {
        $user = $this->uzivatel('jan@example.com');

        $this->actingAs($user)
            ->postJson('/ucet/smazat', ['potvrzeni' => 'preklep@example.com'])
            ->assertStatus(422);

        $this->assertNotNull(User::find($user->id));
    }

    public function test_pres_aplikaci_smazani_projde_a_odhlasi(): void
    {
        $user = $this->uzivatel('jan@example.com');

        $this->actingAs($user)
            ->postJson('/ucet/smazat', ['potvrzeni' => 'JAN@example.com'])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertNull(User::find($user->id));
        $this->assertGuest();
    }

    public function test_verejna_stranka_je_dostupna_bez_prihlaseni(): void
    {
        // Google Play vyžaduje adresu, kterou otevře kdokoli.
        $this->get('/smazani-uctu')
            ->assertOk()
            ->assertSee('Smazání účtu', false);
    }
}
