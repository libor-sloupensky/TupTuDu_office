<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

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
        //
    })->create();
