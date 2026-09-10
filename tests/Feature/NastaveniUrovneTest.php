<?php

namespace Tests\Feature;

use App\Models\Firma;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Přepnutí úrovně zpracování v nastavení firmy.
 *
 * Vzniklo poté, co ukládání na produkci hlásilo „Uložení se nepodařilo." —
 * sloupec `uroven_zpracovani` tam kvůli spadlé migraci vůbec nebyl.
 */
class NastaveniUrovneTest extends TestCase
{
    use RefreshDatabase;

    private Firma $firma;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->firma = Firma::create(['ico' => '10000001', 'nazev' => 'Klient s.r.o.']);
        $this->user = User::create([
            'jmeno' => 'Jan', 'prijmeni' => 'Novak',
            'email' => 'jan@example.com', 'password' => 'heslo12345',
        ]);
        $this->user->markEmailAsVerified();
        $this->user->firmy()->attach($this->firma->ico, ['role' => 'firma', 'interni_role' => 'superadmin']);
    }

    private function prihlasen()
    {
        return $this->actingAs($this->user)->withSession(['aktivni_firma_ico' => $this->firma->ico]);
    }

    public function test_vsechny_tri_urovne_jde_ulozit(): void
    {
        foreach (['ulozeni', 'vycteni', 'rozpoznani'] as $uroven) {
            $this->prihlasen()
                ->postJson('/nastaveni/uroven', ['uroven' => $uroven])
                ->assertOk()
                ->assertJsonPath('ok', true);

            $this->assertSame($uroven, $this->firma->fresh()->uroven_zpracovani);
        }
    }

    public function test_nesmyslna_uroven_se_odmitne(): void
    {
        $this->prihlasen()
            ->postJson('/nastaveni/uroven', ['uroven' => 'prepis'])
            ->assertStatus(422);

        $this->assertSame('rozpoznani', $this->firma->fresh()->uroven_zpracovani);
    }

    public function test_sloupec_v_databazi_zna_vsechny_tri_hodnoty(): void
    {
        // Enum se zužuje migrací; kdyby se rozešel s validací v kontroleru,
        // ukládání by padalo až na produkci.
        foreach (['ulozeni', 'vycteni', 'rozpoznani'] as $uroven) {
            $this->firma->update(['uroven_zpracovani' => $uroven]);
            $this->assertSame($uroven, $this->firma->fresh()->uroven_zpracovani);
        }
    }
}
