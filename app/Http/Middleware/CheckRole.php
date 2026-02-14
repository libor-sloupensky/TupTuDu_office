<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        $ico = session('aktivni_firma_ico');

        if (!$user || !$ico) {
            abort(403, 'Nemáte oprávnění k této akci.');
        }

        $userRole = $user->firmy()->where('ico', $ico)->first()?->pivot?->role;

        if (!$userRole || !in_array($userRole, $roles)) {
            abort(403, 'Nemáte oprávnění k této akci.');
        }

        return $next($request);
    }
}
