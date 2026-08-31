<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'verified' => \App\Http\Middleware\EnsureEmailIsVerified::class,
            'firma' => \App\Http\Middleware\EnsureFirmaSelected::class,
            'role' => \App\Http\Middleware\CheckRole::class,
            'partner' => \App\Http\Middleware\AuthenticatePartner::class,
        ]);

        // Partnerské API si limit řeší samo v routes/api.php, výchozí skupina
        // ho tedy mít nemusí — a bez definovaného limiteru `api` by stejně
        // spadla na "Rate limiter [api] is not defined".
        $middleware->group('api', [
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'upload',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Vypršelou session neukazujeme jako holou stránku "419 Page Expired".
        // Session platí 120 minut a mobilní aplikace bývá otevřená déle, takže
        // se to trefí běžně — typicky při odhlášení nebo přepnutí firmy po
        // delší pauze. Uživatel má místo toho skončit na přihlášení a vědět,
        // co se stalo.
        //
        // Chytá se podle stavového kódu, ne podle TokenMismatchException:
        // Laravel ji na HttpException 419 převede dřív, než se dostane ke slovu
        // tenhle callback, takže na původní typ by se nikdy netrefil.
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if ($e->getStatusCode() !== 419) {
                return null; // ostatní chyby si Laravel vyřídí sám
            }

            $zprava = 'Platnost přihlášení vypršela. Přihlaste se prosím znovu.';

            if ($request->expectsJson()) {
                return response()->json(['chyba' => $zprava], 419);
            }

            // Kdo je pořád přihlášený, toho `guest` middleware z přihlašovací
            // stránky vrátí zpátky do aplikace — o nic tedy nepřijde.
            return redirect()
                ->route($request->is('mobile/*') ? 'mobile.prihlaseni' : 'login')
                ->withErrors(['session' => $zprava]);
        });
    })->create();
