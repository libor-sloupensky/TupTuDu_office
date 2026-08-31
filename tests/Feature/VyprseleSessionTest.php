<?php

namespace Tests\Feature;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Tests\TestCase;

/**
 * Vypršelá session nesmí skončit holou stránkou „419 Page Expired".
 *
 * Session platí 120 minut a appka bývá otevřená déle, takže se to trefí běžně —
 * při odhlášení, přepnutí firmy nebo nahrávání po delší pauze.
 */
class VyprseleSessionTest extends TestCase
{
    private function odpovedNa(Request $request)
    {
        return app(ExceptionHandler::class)->render($request, new TokenMismatchException());
    }

    public function test_z_webu_vede_na_prihlaseni(): void
    {
        $odpoved = $this->odpovedNa(Request::create('/odhlaseni', 'POST'));

        $this->assertSame(302, $odpoved->getStatusCode());
        $this->assertSame(route('login'), $odpoved->headers->get('Location'));
    }

    public function test_z_mobilu_vede_na_mobilni_prihlaseni(): void
    {
        $odpoved = $this->odpovedNa(Request::create('/mobile/odhlaseni', 'POST'));

        $this->assertSame(302, $odpoved->getStatusCode());
        $this->assertSame(route('mobile.prihlaseni'), $odpoved->headers->get('Location'));
    }

    public function test_pro_json_vrati_srozumitelnou_hlasku(): void
    {
        $request = Request::create('/doklady/1', 'PATCH');
        $request->headers->set('Accept', 'application/json');

        $odpoved = $this->odpovedNa($request);

        $this->assertSame(419, $odpoved->getStatusCode());
        $telo = json_decode($odpoved->getContent(), true);
        $this->assertStringContainsString('vypršela', $telo['chyba'] ?? '');
    }

    public function test_uzivatel_dostane_vysvetleni_a_ne_holou_chybu(): void
    {
        $odpoved = $this->odpovedNa(Request::create('/odhlaseni', 'POST'));

        $chyby = session('errors');

        $this->assertNotNull($chyby, 'Hláška se nepředala na přihlašovací stránku.');
        $this->assertStringContainsString('Platnost přihlášení vypršela', $chyby->first());
    }
}
