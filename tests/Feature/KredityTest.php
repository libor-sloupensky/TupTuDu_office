<?php

namespace Tests\Feature;

use App\Models\AiVolani;
use App\Models\Doklad;
use App\Models\Firma;
use App\Services\Kredity;
use App\Services\NakladyAi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Kredity za stránku, pád na úroveň Uložení a měření skutečných nákladů.
 */
class KredityTest extends TestCase
{
    use RefreshDatabase;

    private Kredity $kredity;

    protected function setUp(): void
    {
        parent::setUp();
        $this->kredity = new Kredity();
    }

    private function firma(?int $kredity = null, string $uroven = 'vycteni'): Firma
    {
        return Firma::create([
            'ico' => '10000001',
            'nazev' => 'Klient s.r.o.',
            'uroven_zpracovani' => $uroven,
            'kredity' => $kredity,
        ]);
    }

    public function test_firma_bez_prideleneho_zustatku_neni_omezena(): void
    {
        // Výchozí stav všech stávajících firem — nic se jim nesmí změnit.
        $firma = $this->firma(kredity: null);

        $this->assertNull($firma->kredity);
        $this->assertTrue($this->kredity->lzeVytezit($firma, 1));
        $this->assertTrue($this->kredity->lzeVytezit($firma, 500));

        $this->kredity->odecti($firma, 10);
        $this->assertNull($firma->fresh()->kredity);
        $this->assertSame(0, DB::table('sys_kredity_pohyby')->count());
    }

    public function test_uroven_ulozeni_nevytezuje_ani_s_kredity(): void
    {
        $firma = $this->firma(kredity: 1000, uroven: 'ulozeni');

        $this->assertFalse($this->kredity->lzeVytezit($firma, 1));
    }

    public function test_kredity_se_odecitaji_za_stranku(): void
    {
        $firma = $this->firma(kredity: 10);

        $this->kredity->odecti($firma, 3);

        $this->assertSame(7, $firma->fresh()->kredity);

        $pohyb = DB::table('sys_kredity_pohyby')->first();
        $this->assertSame(-3, $pohyb->zmena);
        $this->assertSame(7, $pohyb->zustatek_po);
        $this->assertSame('vycteni', $pohyb->duvod);
    }

    public function test_pri_nedostatku_kreditu_se_nevytezuje(): void
    {
        $firma = $this->firma(kredity: 2);

        $this->assertTrue($this->kredity->lzeVytezit($firma, 2));
        $this->assertFalse($this->kredity->lzeVytezit($firma, 3));
    }

    public function test_zustatek_nikdy_neklesne_pod_nulu(): void
    {
        $firma = $this->firma(kredity: 1);

        $this->kredity->odecti($firma, 5);

        $this->assertSame(0, $firma->fresh()->kredity);
    }

    public function test_pripis_zvysi_zustatek_a_zapise_pohyb(): void
    {
        $firma = $this->firma(kredity: 5);

        $this->kredity->pripis($firma, 200, 'partner');

        $this->assertSame(205, $firma->fresh()->kredity);
        $this->assertSame(200, (int) DB::table('sys_kredity_pohyby')->where('duvod', 'partner')->value('zmena'));
    }

    public function test_pripis_neomezene_firme_ji_zacne_omezovat(): void
    {
        $firma = $this->firma(kredity: null);

        $this->kredity->pripis($firma, 100);

        $this->assertSame(100, $firma->fresh()->kredity);
        $this->assertFalse($this->kredity->lzeVytezit($firma->fresh(), 101));
    }

    public function test_naklad_na_claude_se_spocita_podle_ceniku(): void
    {
        $firma = $this->firma();
        $doklad = Doklad::create([
            'firma_ico' => $firma->ico,
            'nazev_souboru' => 'f.pdf',
            'cesta_souboru' => 'doklady/x.pdf',
            'hash_souboru' => hash('sha256', 'x'),
            'stav' => 'dokonceno',
        ]);

        (new NakladyAi())->zalogujClaude(
            'claude-haiku-4-5-20251001',
            ['input_tokens' => 5000, 'output_tokens' => 1000],
            $firma->ico,
            $doklad->id,
            true,
            200,
            1234,
        );

        $volani = AiVolani::firstOrFail();

        // 5000 vstupních po $1/mil. + 1000 výstupních po $5/mil. = $0,010
        $this->assertSame('0.010000', $volani->cena_usd);
        $this->assertSame($firma->ico, $volani->firma_ico);
        $this->assertSame($doklad->id, $volani->doklad_id);
    }

    public function test_neuspesne_volani_se_zaznamena_bez_ceny(): void
    {
        (new NakladyAi())->zalogujClaude(
            'claude-haiku-4-5-20251001',
            ['input_tokens' => 5000, 'output_tokens' => 0],
            '10000001',
            null,
            false,
            529,
            800,
            'overloaded',
        );

        $volani = AiVolani::firstOrFail();

        $this->assertSame('0.000000', $volani->cena_usd);
        $this->assertFalse($volani->uspesne);
        $this->assertSame(529, $volani->http_status);
    }

    public function test_textract_se_uctuje_po_strankach(): void
    {
        (new NakladyAi())->zalogujTextract(4, '10000001', null, true, 500);

        $this->assertSame('0.006000', AiVolani::firstOrFail()->cena_usd);
    }
}
