<?php

namespace Tests\Feature;

use App\Models\Firma;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Mobilní skener — stránka se musí vykreslit a nahrávání projít i bez
 * přepínače druhu, který appka neposílá.
 */
class MobilniAplikaceTest extends TestCase
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

    public function test_skenovaci_stranka_se_vykresli(): void
    {
        $this->prihlasen()->get('/mobile/skenovat')->assertOk();
    }

    public function test_posledni_doklady_vrati_json(): void
    {
        $this->prihlasen()->getJson('/doklady/posledni')->assertOk();
    }

    public function test_prepnuti_firmy_projde(): void
    {
        $this->prihlasen()->post('/mobile/prepnout-firmu/' . $this->firma->ico)->assertRedirect();
    }
}
