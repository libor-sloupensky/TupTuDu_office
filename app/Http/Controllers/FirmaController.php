<?php

namespace App\Http\Controllers;

use App\Models\Firma;
use Illuminate\Http\Request;

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
            'email' => 'nullable|email|max:255',
            'telefon' => 'nullable|string|max:20',
            'email_doklady_heslo' => 'nullable|string|max:255',
        ]);

        $data = $request->only(['email', 'telefon']);

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
        ]);

        $user = auth()->user();

        if ($user->firmy()->where('ico', $request->ico)->exists()) {
            return back()->withErrors(['ico' => 'Tuto firmu již máte přiřazenou.'])->withInput();
        }

        $ares = AresController::fetchAres($request->ico);

        if (!$ares || !$ares['nazev']) {
            return back()->withErrors(['ico' => 'IČO nebylo nalezeno v ARES.'])->withInput();
        }

        $firma = Firma::firstOrCreate(
            ['ico' => $request->ico],
            [
                'nazev' => $ares['nazev'],
                'dic' => $ares['dic'],
                'ulice' => $ares['ulice'],
                'mesto' => $ares['mesto'],
                'psc' => $ares['psc'],
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

        $ares = AresController::fetchAres($firma->ico);

        if (!$ares) {
            return back()->withErrors(['ico' => 'Subjekt nenalezen v ARES.']);
        }

        $firma->update([
            'nazev' => $ares['nazev'] ?? $firma->nazev,
            'dic' => $ares['dic'] ?? $firma->dic,
            'ulice' => $ares['ulice'] ?? $firma->ulice,
            'mesto' => $ares['mesto'] ?? $firma->mesto,
            'psc' => $ares['psc'] ?? $firma->psc,
        ]);

        return redirect()->route('firma.nastaveni')->with('success', 'Data obnovena z ARES.');
    }
}
