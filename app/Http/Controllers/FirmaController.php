<?php

namespace App\Http\Controllers;

use App\Models\Firma;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class FirmaController extends Controller
{
    public function nastaveni()
    {
        $firma = auth()->user()->aktivniFirma();
        return view('firma.nastaveni', compact('firma'));
    }

    public function ulozit(Request $request)
    {
        $firma = auth()->user()->aktivniFirma();

        if (!$firma) {
            return back()->withErrors(['ico' => 'Žádná aktivní firma.']);
        }

        $request->validate([
            'nazev' => 'required|string|max:255',
            'dic' => 'nullable|string|max:20',
            'ulice' => 'nullable|string|max:255',
            'mesto' => 'nullable|string|max:255',
            'psc' => 'nullable|string|max:10',
            'email' => 'nullable|email|max:255',
            'telefon' => 'nullable|string|max:20',
            'email_doklady_heslo' => 'nullable|string|max:255',
        ]);

        $data = $request->only(['nazev', 'dic', 'ulice', 'mesto', 'psc', 'email', 'telefon']);

        if ($request->filled('email_doklady_heslo')) {
            $data['email_doklady_heslo'] = $request->email_doklady_heslo;
        }

        $firma->update($data);

        return redirect()->route('firma.nastaveni')->with('success', 'Nastavení uloženo.');
    }

    public function pridatFirmu()
    {
        return view('firma.pridat');
    }

    public function ulozitNovou(Request $request)
    {
        $request->validate([
            'ico' => 'required|string|regex:/^\d{8}$/',
            'role' => 'required|in:ucetni,firma,dodavatel',
            'nazev' => 'required|string|max:255',
            'dic' => 'nullable|string|max:20',
            'ulice' => 'nullable|string|max:255',
            'mesto' => 'nullable|string|max:255',
            'psc' => 'nullable|string|max:10',
        ]);

        $user = auth()->user();

        if ($user->firmy()->where('ico', $request->ico)->exists()) {
            return back()->withErrors(['ico' => 'Tuto firmu již máte přiřazenou.'])->withInput();
        }

        $firma = Firma::firstOrCreate(
            ['ico' => $request->ico],
            [
                'nazev' => $request->nazev,
                'dic' => $request->dic,
                'ulice' => $request->ulice,
                'mesto' => $request->mesto,
                'psc' => $request->psc,
            ]
        );

        if ($request->role === 'ucetni') {
            $firma->update(['je_ucetni' => true]);
        }

        if (!$firma->email_doklady) {
            $firma->update(['email_doklady' => $request->ico . '@tuptudu.cz']);
        }

        $user->firmy()->attach($firma->ico, ['role' => $request->role]);

        session(['aktivni_firma_ico' => $firma->ico]);

        return redirect()->route('doklady.index')->with('flash', "Firma {$firma->nazev} byla přidána.");
    }

    public function prepnout(string $ico)
    {
        $user = auth()->user();

        if (!$user->firmy()->where('ico', $ico)->exists()) {
            abort(403, 'Nemáte přístup k této firmě.');
        }

        session(['aktivni_firma_ico' => $ico]);

        return redirect()->back()->with('flash', 'Firma přepnuta.');
    }

    public function obnovitAres()
    {
        $firma = auth()->user()->aktivniFirma();

        if (!$firma) {
            return back()->withErrors(['ico' => 'Žádná aktivní firma.']);
        }

        $response = Http::timeout(10)->get(
            "https://ares.gov.cz/ekonomicke-subjekty-v-be/rest/ekonomicke-subjekty/{$firma->ico}"
        );

        if ($response->failed()) {
            return back()->withErrors(['ico' => 'Subjekt nenalezen v ARES.']);
        }

        $data = $response->json();
        $sidlo = $data['sidlo'] ?? [];

        $ulice = null;
        if (isset($sidlo['nazevUlice'])) {
            $ulice = $sidlo['nazevUlice'];
            if (isset($sidlo['cisloDomovni'])) {
                $ulice .= ' ' . $sidlo['cisloDomovni'];
                if (isset($sidlo['cisloOrientacni'])) {
                    $ulice .= '/' . $sidlo['cisloOrientacni'];
                }
            }
        } elseif (isset($sidlo['textovaAdresa'])) {
            $ulice = $sidlo['textovaAdresa'];
        }

        $firma->update([
            'nazev' => $data['obchodniJmeno'] ?? $firma->nazev,
            'dic' => $data['dic'] ?? $firma->dic,
            'ulice' => $ulice ?? $firma->ulice,
            'mesto' => $sidlo['nazevObce'] ?? $firma->mesto,
            'psc' => isset($sidlo['psc']) ? (string) $sidlo['psc'] : $firma->psc,
        ]);

        return redirect()->route('firma.nastaveni')->with('success', 'Data obnovena z ARES.');
    }
}
