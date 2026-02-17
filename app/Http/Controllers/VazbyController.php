<?php

namespace App\Http\Controllers;

use App\Models\UcetniVazba;
use Illuminate\Http\Request;

class VazbyController extends Controller
{
    public function approve(int $id)
    {
        $firma = auth()->user()->aktivniFirma();

        $vazba = UcetniVazba::where('id', $id)
            ->where('klient_ico', $firma->ico)
            ->where('stav', 'ceka_na_firmu')
            ->firstOrFail();

        $vazba->update(['stav' => 'schvaleno']);

        return redirect()->route('firma.nastaveni')->with('flash', "Účetní firma {$vazba->ucetniFirma->nazev} byla schválena.");
    }

    public function reject(int $id)
    {
        $firma = auth()->user()->aktivniFirma();

        $vazba = UcetniVazba::where('id', $id)
            ->where('klient_ico', $firma->ico)
            ->where('stav', 'ceka_na_firmu')
            ->firstOrFail();

        $vazba->update(['stav' => 'zamitnuto']);

        return redirect()->route('firma.nastaveni')->with('flash', 'Žádost byla zamítnuta.');
    }

    public function disconnect(int $id)
    {
        $firma = auth()->user()->aktivniFirma();

        $vazba = UcetniVazba::where('id', $id)
            ->where('klient_ico', $firma->ico)
            ->where('stav', 'schvaleno')
            ->firstOrFail();

        $nazev = $vazba->ucetniFirma->nazev ?? 'Účetní firma';
        $vazba->delete();

        return redirect()->route('firma.nastaveni')->with('flash', "Napojení na {$nazev} bylo zrušeno.");
    }
}
