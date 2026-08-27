<?php

namespace App\Http\Middleware;

use App\Models\Partner;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Přihlášení partnera přes API klíč v hlavičce `Authorization: Bearer …`.
 *
 * Partnerské API je vědomě oddělené od webu — nemá session ani uživatele,
 * takže se přes něj nedá dostat na nic, co web nabízí přihlášenému člověku.
 * Partner tím pádem nemá jak sáhnout na doklady: takové routy tu prostě nejsou
 * a webové routy jeho klíč neznají.
 */
class AuthenticatePartner
{
    public function handle(Request $request, Closure $next): Response
    {
        $klic = $request->bearerToken() ?? '';
        $partner = Partner::podleApiKlice($klic);

        if (!$partner) {
            return response()->json([
                'chyba' => 'Neplatný nebo chybějící API klíč.',
            ], 401);
        }

        $request->attributes->set('partner', $partner);

        return $next($request);
    }
}
