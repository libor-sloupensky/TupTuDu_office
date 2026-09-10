<?php

namespace Tests\Feature;

use App\Models\Doklad;
use App\Models\Firma;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Tests\TestCase;

/**
 * Hledání v přepisu dokladu.
 *
 * Nejede přes RefreshDatabase: ta drží každý test v transakci a InnoDB doplňuje
 * fulltextový index až při commitu, takže by MATCH nezacommitované řádky
 * neviděl. Data se proto po sobě uklízejí ručně — kdyby zůstala, rozbila by
 * ostatní testy, které čekají prázdnou databázi.
 */
class HledaniTest extends TestCase
{
    use DatabaseTruncation;

    private Firma $firma;

    protected function setUp(): void
    {
        parent::setUp();
        $this->firma = Firma::create(['ico' => '10000001', 'nazev' => 'Klient s.r.o.']);
    }

    protected function tearDown(): void
    {
        Doklad::query()->delete();
        Firma::query()->delete();

        parent::tearDown();
    }

    private function doklad(string $prepis, array $navic = []): Doklad
    {
        return Doklad::create(array_merge([
            'firma_ico' => $this->firma->ico,
            'nazev_souboru' => 'doklad.pdf',
            'cesta_souboru' => 'doklady/' . uniqid() . '.pdf',
            'hash_souboru' => hash('sha256', uniqid('', true)),
            'stav' => 'ulozeno',
            'raw_text' => $prepis,
        ], $navic));
    }

    private function najdi(string $vyraz, bool $iUprostred = false): array
    {
        return Doklad::where('firma_ico', $this->firma->ico)
            ->hledej($vyraz, $iUprostred)
            ->pluck('id')
            ->all();
    }

    public function test_najde_doklad_podle_slova_z_prepisu(): void
    {
        $hledany = $this->doklad('Pneuservis Brno, výměna letních pneumatik, celkem 4200 Kč');
        $this->doklad('Restaurace U Lípy, obědy pro zaměstnance');

        $this->assertSame([$hledany->id], $this->najdi('pneumatik'));
    }

    public function test_hleda_i_od_zacatku_slova(): void
    {
        $hledany = $this->doklad('Pneuservis Brno, výměna pneumatik');

        $this->assertSame([$hledany->id], $this->najdi('pneu'));
    }

    public function test_vice_slov_musi_byt_vsechna(): void
    {
        $oba = $this->doklad('Pneuservis Brno, výměna pneumatik');
        $this->doklad('Pneuservis Ostrava, oprava disku');

        $this->assertSame([$oba->id], $this->najdi('pneuservis výměna'));
    }

    public function test_kratky_vyraz_projde_pres_like(): void
    {
        // Dvouznakové slovo se do indexu nedostane, hledání ale nesmí selhat.
        $hledany = $this->doklad('Nákup PC sestavy');

        $this->assertSame([$hledany->id], $this->najdi('PC'));
    }

    public function test_cislo_dokladu_se_najde_i_uprostred(): void
    {
        $hledany = $this->doklad('nic zajímavého', ['cislo_dokladu' => 'FV-2026-00123']);

        $this->assertSame([$hledany->id], $this->najdi('00123'));
    }

    public function test_uprostred_slova_najde_az_druhy_pruchod(): void
    {
        $hledany = $this->doklad('Pneuservis Brno, výměna pneumatik');

        // Fulltextový index hledá od začátku slova — sám o sobě nenajde nic.
        $this->assertSame([], $this->najdi('servis'));

        // Druhý průchod přes LIKE už ano; tak to dělá i seznam dokladů, když
        // rychlé hledání skončí naprázdno.
        $this->assertSame([$hledany->id], $this->najdi('servis', iUprostred: true));
    }

    public function test_co_tam_neni_se_nenajde(): void
    {
        $this->doklad('Pneuservis Brno');

        $this->assertSame([], $this->najdi('kancelářské potřeby'));
    }

    public function test_najde_podle_kategorie_i_poznamky(): void
    {
        $podleKategorie = $this->doklad('nic', ['kategorie' => 'Kancelář']);
        $podlePoznamky = $this->doklad('nic', ['poznamka' => 'reklamace u dodavatele']);

        $this->assertSame([$podleKategorie->id], $this->najdi('Kancelář'));
        $this->assertSame([$podlePoznamky->id], $this->najdi('reklamace'));
    }

    public function test_najde_podle_variabilniho_symbolu_a_uctu(): void
    {
        $vs = $this->doklad('nic', ['variabilni_symbol' => '2026000123']);
        $ucet = $this->doklad('nic', ['cislo_uctu' => '19-2000145399/0800']);

        $this->assertSame([$vs->id], $this->najdi('2026000123'));
        $this->assertSame([$ucet->id], $this->najdi('2000145399'));
    }

    public function test_najde_podle_castky(): void
    {
        $hledany = $this->doklad('nic', ['castka_celkem' => 454.00]);
        $this->doklad('nic', ['castka_celkem' => 455.00]);

        $this->assertSame([$hledany->id], $this->najdi('454'));
        $this->assertSame([$hledany->id], $this->najdi('454,00'));
    }

    public function test_najde_podle_data(): void
    {
        $hledany = $this->doklad('nic', ['datum_vystaveni' => '2026-09-01']);
        $podleSplatnosti = $this->doklad('nic', ['datum_splatnosti' => '2026-09-01']);
        $this->doklad('nic', ['datum_vystaveni' => '2026-09-02']);

        // Hledá se ve všech datumových polích naráz.
        $nalezene = $this->najdi('1.9.2026');
        sort($nalezene);

        $this->assertSame([$hledany->id, $podleSplatnosti->id], $nalezene);
        $this->assertSame($nalezene, $this->najdi('2026-09-01'));
    }

    public function test_nesmyslne_datum_se_nebere_jako_datum(): void
    {
        $this->doklad('nic', ['datum_vystaveni' => '2026-09-01']);

        $this->assertSame([], $this->najdi('31.2.2026'));
    }

    public function test_operatory_ve_vyrazu_dotaz_nerozbiji(): void
    {
        $this->doklad('Pneuservis Brno');

        // Znaky s významem v boolean režimu se musí odfiltrovat, ne způsobit chybu.
        $this->assertIsArray($this->najdi('+++ pneu*** ~~~'));
        $this->assertIsArray($this->najdi('"'));
    }
}
