<?php

namespace App\Http\Controllers;

use App\Services\SmazaniUctu;
use App\Support\AktivniFirma;
use App\Support\OsobniProstor;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

/**
 * Nastavení uživatelského účtu — na rozdíl od nastavení firmy se týká člověka,
 * ne organizace.
 */
class UcetController extends Controller
{
    public function nastaveni()
    {
        $user = auth()->user();

        return view('ucet.nastaveni', [
            'user' => $user,
            'osobni' => OsobniProstor::zajisti($user),
            'prehledSmazani' => (new SmazaniUctu())->prehled($user),
        ]);
    }

    /**
     * Smaže účet i s daty, která k němu patří.
     *
     * Potvrzuje se opsáním e-mailu — je to nevratné a dělá se hned. Co přesně
     * zmizí, popisuje SmazaniUctu.
     */
    public function smazat(Request $request)
    {
        $user = auth()->user();

        $request->validate(['potvrzeni' => 'required|string']);

        if (mb_strtolower(trim($request->input('potvrzeni'))) !== mb_strtolower($user->email)) {
            return response()->json([
                'ok' => false,
                'error' => 'Pro potvrzení opište svůj e-mail přesně tak, jak je uvedený výš.',
            ], 422);
        }

        (new SmazaniUctu())->smaz($user);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        AktivniFirma::zapomen();

        return response()->json(['ok' => true, 'presmerovat' => route('login')]);
    }

    /**
     * Zapne nebo vypne osobní prostor.
     *
     * Vypnutím se prostor jen přestane nabízet — doklady v něm zůstávají a po
     * opětovném zapnutí jsou zase k dispozici. Mazat je tudy nejde záměrně.
     */
    public function prepnoutOsobni(Request $request)
    {
        $request->validate(['zapnuto' => 'required|boolean']);

        $osobni = OsobniProstor::prepni(auth()->user(), $request->boolean('zapnuto'));

        return response()->json([
            'ok' => true,
            'zapnuto' => $osobni->osobni_aktivni,
        ]);
    }
}
