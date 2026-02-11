<?php

namespace App\Http\Controllers;

use App\Models\Firma;
use Illuminate\Http\Request;

class FirmaController extends Controller
{
    public function nastaveni()
    {
        $firma = Firma::first();
        return view('firma.nastaveni', compact('firma'));
    }

    public function ulozit(Request $request)
    {
        $request->validate([
            'ico' => 'required|string|max:20',
            'nazev' => 'required|string|max:255',
            'dic' => 'nullable|string|max:20',
            'ulice' => 'nullable|string|max:255',
            'mesto' => 'nullable|string|max:255',
            'psc' => 'nullable|string|max:10',
            'email' => 'nullable|email|max:255',
            'telefon' => 'nullable|string|max:20',
        ]);

        Firma::updateOrCreate(
            ['ico' => $request->ico],
            $request->only(['nazev', 'dic', 'ulice', 'mesto', 'psc', 'email', 'telefon'])
        );

        return redirect()->route('firma.nastaveni')->with('success', 'Nastavení uloženo.');
    }
}
