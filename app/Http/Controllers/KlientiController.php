<?php

namespace App\Http\Controllers;

use App\Models\Firma;
use App\Models\UcetniVazba;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class KlientiController extends Controller
{
    public function index()
    {
        $firma = auth()->user()->aktivniFirma();

        $vazby = UcetniVazba::where('ucetni_ico', $firma->ico)
            ->with('klientFirma')
            ->orderByRaw("FIELD(stav, 'schvaleno', 'ceka_na_firmu', 'zamitnuto')")
            ->get();

        return view('klienti.index', compact('firma', 'vazby'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'klient_ico' => 'required|string|regex:/^\d{8}$/',
        ]);

        $firma = auth()->user()->aktivniFirma();
        $klientIco = $request->klient_ico;

        if ($klientIco === $firma->ico) {
            return back()->withErrors(['klient_ico' => 'Nemůžete přidat sami sebe jako klienta.'])->withInput();
        }

        $existuje = UcetniVazba::where('ucetni_ico', $firma->ico)
            ->where('klient_ico', $klientIco)
            ->first();

        if ($existuje) {
            return back()->withErrors(['klient_ico' => 'Tato firma je již ve vašem seznamu klientů.'])->withInput();
        }

        $jinyUcetni = UcetniVazba::where('klient_ico', $klientIco)
            ->whereIn('stav', ['ceka_na_firmu', 'schvaleno'])
            ->where('ucetni_ico', '!=', $firma->ico)
            ->exists();

        if ($jinyUcetni) {
            return back()->withErrors(['klient_ico' => 'Tato firma již má přiřazeného účetního.'])->withInput();
        }

        $klientFirma = Firma::find($klientIco);
        if (!$klientFirma) {
            $aresResponse = Http::timeout(10)->get(
                "https://ares.gov.cz/ekonomicke-subjekty-v-be/rest/ekonomicke-subjekty/{$klientIco}"
            );

            if ($aresResponse->successful()) {
                $data = $aresResponse->json();
                $sidlo = $data['sidlo'] ?? [];
                $ulice = $sidlo['nazevUlice'] ?? ($sidlo['textovaAdresa'] ?? null);

                $klientFirma = Firma::create([
                    'ico' => $klientIco,
                    'nazev' => $data['obchodniJmeno'] ?? 'Neznámá firma',
                    'dic' => $data['dic'] ?? null,
                    'ulice' => $ulice,
                    'mesto' => $sidlo['nazevObce'] ?? null,
                    'psc' => isset($sidlo['psc']) ? (string) $sidlo['psc'] : null,
                ]);
            } else {
                $klientFirma = Firma::create([
                    'ico' => $klientIco,
                    'nazev' => 'IČO ' . $klientIco,
                ]);
            }
        }

        if ($klientFirma->kategorie()->count() === 0) {
            Firma::seedDefaultKategorie($klientIco);
        }

        UcetniVazba::create([
            'ucetni_ico' => $firma->ico,
            'klient_ico' => $klientIco,
            'stav' => 'ceka_na_firmu',
        ]);

        return redirect()->route('klienti.index')->with('flash', "Žádost odeslána firmě {$klientFirma->nazev}.");
    }

    public function destroy(string $klientIco)
    {
        $firma = auth()->user()->aktivniFirma();

        UcetniVazba::where('ucetni_ico', $firma->ico)
            ->where('klient_ico', $klientIco)
            ->delete();

        return redirect()->route('klienti.index')->with('flash', 'Klient byl odebrán.');
    }
}
