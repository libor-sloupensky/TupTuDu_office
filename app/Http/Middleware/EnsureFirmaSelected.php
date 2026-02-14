<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFirmaSelected
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || $user->firmy()->count() === 0) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Nemáte přiřazenou žádnou firmu.'], 403);
            }
            return redirect()->route('firma.pridat');
        }

        if (!session('aktivni_firma_ico') || !$user->firmy()->where('ico', session('aktivni_firma_ico'))->exists()) {
            $prvniFirma = $user->firmy()->first();
            session(['aktivni_firma_ico' => $prvniFirma->ico]);
        }

        return $next($request);
    }
}
