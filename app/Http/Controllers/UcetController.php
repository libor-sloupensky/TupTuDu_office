<?php

namespace App\Http\Controllers;

use App\Support\OsobniProstor;
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
        ]);
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
