<?php

namespace App\Http\Controllers;

use App\Services\SmazaniUctu;
use App\Support\AktivniFirma;
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
            'prehledSmazani' => (new SmazaniUctu())->prehled($user),
        ]);
    }

    /**
     * Uzavře účet a naplánuje jeho smazání.
     *
     * Nemaže se hned — běží lhůta na rozmyšlenou a do té doby jde účet obnovit.
     * Potvrzuje se opsáním e-mailu; co přesně nakonec zmizí, popisuje
     * SmazaniUctu.
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

        (new SmazaniUctu())->pozadej($user);

        // Odhlašovat ho nebudeme — rovnou uvidí, dokdy to jde vzít zpět.
        AktivniFirma::zapomen();

        return response()->json(['ok' => true, 'presmerovat' => route('ucet.obnoveni')]);
    }

    /** Nabídka obnovení pro účet, který čeká na smazání. */
    public function obnoveni()
    {
        $user = auth()->user();

        if (!$user->cekaNaSmazani()) {
            return redirect()->route('ucet.nastaveni');
        }

        return view('ucet.obnoveni', ['user' => $user]);
    }

    public function obnovit()
    {
        $user = auth()->user();

        if ($user->cekaNaSmazani()) {
            (new SmazaniUctu())->obnov($user);
        }

        return redirect()->route('ucet.nastaveni')->with('flash', 'Účet je zase v pořádku.');
    }

    /**
     * Obnovení odkazem z e-mailu, bez přihlášení.
     *
     * O smazání mohl požádat někdo jiný — majitel účtu se musí bránit, i když
     * se zrovna přihlásit nemůže.
     */
    public function obnovitTokenem(string $token)
    {
        $sluzba = new SmazaniUctu();
        $user = $sluzba->podleTokenu($token);

        if (!$user) {
            return view('ucet.obnoveni-vysledek', [
                'povedlo' => false,
                'zprava' => 'Odkaz už neplatí. Účet byl buď obnovený, nebo smazaný.',
            ]);
        }

        $sluzba->obnov($user);

        return view('ucet.obnoveni-vysledek', [
            'povedlo' => true,
            'zprava' => 'Účet je obnovený. Můžete se přihlásit jako dřív.',
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
