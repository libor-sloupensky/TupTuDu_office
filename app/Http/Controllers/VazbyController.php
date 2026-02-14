<?php

namespace App\Http\Controllers;

use App\Models\UcetniVazba;
use Illuminate\Http\Request;

class VazbyController extends Controller
{
    public function index()
    {
        $firma = auth()->user()->aktivniFirma();

        $vazby = UcetniVazba::where('klient_ico', $firma->ico)
            ->with('ucetniFirma')
            ->orderByRaw("FIELD(stav, 'ceka_na_firmu', 'schvaleno', 'zamitnuto')")
            ->get();

        return view('vazby.index', compact('firma', 'vazby'));
    }

    public function approve(int $id)
    {
        $firma = auth()->user()->aktivniFirma();

        $vazba = UcetniVazba::where('id', $id)
            ->where('klient_ico', $firma->ico)
            ->where('stav', 'ceka_na_firmu')
            ->firstOrFail();

        $vazba->update(['stav' => 'schvaleno']);

        return redirect()->route('vazby.index')->with('flash', "Účetní firma {$vazba->ucetniFirma->nazev} byla schválena.");
    }

    public function reject(int $id)
    {
        $firma = auth()->user()->aktivniFirma();

        $vazba = UcetniVazba::where('id', $id)
            ->where('klient_ico', $firma->ico)
            ->where('stav', 'ceka_na_firmu')
            ->firstOrFail();

        $vazba->update(['stav' => 'zamitnuto']);

        return redirect()->route('vazby.index')->with('flash', 'Žádost byla zamítnuta.');
    }
}
