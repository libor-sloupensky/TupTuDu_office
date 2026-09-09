<?php

namespace Tests\Feature;

use App\Models\Doklad;
use App\Models\Firma;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Hlídá, že seznam dokladů patří firmě, kterou má uživatel na obrazovce —
 * ne té, která je zrovna v session. Session může přepsat odpověď pomalejšího
 * požadavku (nahrávání s vytěžením), a pak se pod hlavičkou jedné firmy
 * ukázaly doklady druhé. Přepnutá firma proto žije ve vlastní cookie
 * a JSON dotazy ji posílají výslovně.
 */
class AktivniFirmaTest extends TestCase
{
    use RefreshDatabase;

    private const XHR = ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'];

    private Firma $grig;
    private Firma $wormup;
    private Firma $cizi;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->grig = Firma::create(['ico' => '10000001', 'nazev' => 'Grig s.r.o.']);
        $this->wormup = Firma::create(['ico' => '20000002', 'nazev' => 'WormUP s.r.o.']);
        $this->cizi = Firma::create(['ico' => '30000003', 'nazev' => 'Cizí s.r.o.']);

        $this->user = User::create([
            'jmeno' => 'Test',
            'prijmeni' => 'Uživatel',
            'email' => 'test@example.com',
            'password' => 'heslo12345',
        ]);
        $this->user->markEmailAsVerified();
        foreach ([$this->grig, $this->wormup] as $firma) {
            $this->user->firmy()->attach($firma->ico, ['role' => 'firma', 'interni_role' => 'superadmin']);
        }

        $this->doklad($this->grig, 'Dodavatel GRIG');
        $this->doklad($this->wormup, 'Dodavatel WORMUP');
    }

    private function doklad(Firma $firma, string $dodavatel): Doklad
    {
        return Doklad::create([
            'firma_ico' => $firma->ico,
            'dodavatel_nazev' => $dodavatel,
            'nazev_souboru' => 'faktura.pdf',
            'cesta_souboru' => 'doklady/' . $firma->ico . '.pdf',
            'hash_souboru' => hash('sha256', $firma->ico),
            'stav' => 'dokonceno',
            'zdroj' => 'web',
        ]);
    }

    public function test_json_seznam_vraci_firmu_z_obrazovky_i_kdyz_session_ukazuje_jinam(): void
    {
        $this->actingAs($this->user)
            ->withSession(['aktivni_firma_ico' => $this->wormup->ico])
            ->get('/doklady?firma_ico=' . $this->grig->ico, self::XHR)
            ->assertOk()
            ->assertSee('Dodavatel GRIG')
            ->assertDontSee('Dodavatel WORMUP');
    }

    public function test_json_seznam_odmitne_firmu_bez_pristupu(): void
    {
        $this->actingAs($this->user)
            ->withSession(['aktivni_firma_ico' => $this->grig->ico])
            ->get('/doklady?firma_ico=' . $this->cizi->ico, self::XHR)
            ->assertForbidden();
    }

    public function test_posledni_doklady_respektuji_firmu_z_parametru(): void
    {
        $this->actingAs($this->user)
            ->withSession(['aktivni_firma_ico' => $this->wormup->ico])
            ->get('/doklady/posledni?firma_ico=' . $this->grig->ico, self::XHR)
            ->assertOk()
            ->assertJsonFragment(['nazev' => 'Dodavatel GRIG'])
            ->assertJsonMissing(['nazev' => 'Dodavatel WORMUP']);
    }

    public function test_prepnuti_firmy_ulozi_cookie(): void
    {
        $this->actingAs($this->user)
            ->withSession(['aktivni_firma_ico' => $this->grig->ico])
            ->post('/firma/prepnout/' . $this->wormup->ico)
            ->assertRedirect()
            ->assertCookie('aktivni_firma_ico', $this->wormup->ico);
    }

    public function test_cookie_ma_prednost_pred_session(): void
    {
        // Session ukazuje na WormUP (třeba ji přepsal pomalejší požadavek),
        // cookie drží GRIG — platí cookie.
        $this->actingAs($this->user)
            ->withSession(['aktivni_firma_ico' => $this->wormup->ico])
            ->withCookie('aktivni_firma_ico', $this->grig->ico)
            ->get('/doklady', self::XHR)
            ->assertOk()
            ->assertSee('Dodavatel GRIG')
            ->assertDontSee('Dodavatel WORMUP');

        $this->actingAs($this->user)
            ->withSession(['aktivni_firma_ico' => $this->wormup->ico])
            ->withCookie('aktivni_firma_ico', $this->grig->ico)
            ->get('/doklady')
            ->assertOk()
            ->assertSee('Grig s.r.o.');
    }

    public function test_cookie_na_firmu_bez_pristupu_se_ignoruje(): void
    {
        $this->actingAs($this->user)
            ->withSession(['aktivni_firma_ico' => $this->wormup->ico])
            ->withCookie('aktivni_firma_ico', $this->cizi->ico)
            ->get('/doklady', self::XHR)
            ->assertOk()
            ->assertDontSee('Dodavatel GRIG')
            ->assertSee('Dodavatel WORMUP');
    }
}
