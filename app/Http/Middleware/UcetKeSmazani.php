<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Uzavřený účet do aplikace nepustí.
 *
 * Dokud běží lhůta na rozmyšlenou, uživatel se sice přihlásí, ale místo
 * aplikace uvidí nabídku obnovení. Odhlásit ho rovnou by bylo horší: nedozvěděl
 * by se, proč ho to nepouští dál, ani jak to vrátit.
 */
class UcetKeSmazani
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !$user->cekaNaSmazani()) {
            return $next($request);
        }

        // Stránka obnovení a odhlášení musí zůstat průchozí, jinak by se
        // uživatel zacyklil.
        if ($request->routeIs('ucet.obnoveni', 'ucet.obnovit', 'logout', 'mobile.logout')) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'chyba' => 'Účet je uzavřený a čeká na smazání.',
            ], 403);
        }

        return redirect()->route('ucet.obnoveni');
    }
}
