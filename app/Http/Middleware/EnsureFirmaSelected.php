<?php

namespace App\Http\Middleware;

use Closure;
use App\Support\AktivniFirma;
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
            return redirect()->route('firma.zadna');
        }

        // Firma z cookie má přednost, ale jen když na ni uživatel opravdu má
        // právo — cookie může zůstat po odebrání přístupu nebo z jiného účtu
        // ve stejném prohlížeči. Teprve když neprojde ani ona, ani záložní
        // hodnota v session, vybere se první firma uživatele.
        $kandidati = array_filter([AktivniFirma::ico(), session('aktivni_firma_ico')]);
        $aktivniIco = null;

        foreach ($kandidati as $ico) {
            if ($user->firmy()->where('ico', $ico)->exists() || $user->jeKlientFirma($ico)) {
                $aktivniIco = $ico;
                break;
            }
        }

        if ($aktivniIco === null) {
            // Přednost má skutečná firma. Osobní prostor je záloha pro
            // toho, kdo žádnou firmu nemá — jinak by po přihlášení skončil
            // ve svých soukromých dokladech místo ve firemních.
            $aktivniIco = $user->firmy()->viditelne()->orderBy('je_osobni')->first()->ico;
        }

        if ($aktivniIco !== AktivniFirma::ico()) {
            AktivniFirma::nastav($aktivniIco);
        }

        return $next($request);
    }
}
