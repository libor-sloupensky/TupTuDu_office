<?php

namespace App\Http\Controllers;

use App\Mail\PozvankaDoFirmy;
use App\Models\Firma;
use App\Models\Kategorie;
use App\Models\Pozvani;
use App\Models\UcetniVazba;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class FirmaController extends Controller
{
    public function nastaveni()
    {
        $firma = auth()->user()->aktivniFirma();
        $user = auth()->user();

        $vazby = collect();
        if ($firma && ($user->maRoli('firma') || $user->maRoli('dodavatel'))) {
            $vazby = UcetniVazba::where('klient_ico', $firma->ico)
                ->with('ucetniFirma')
                ->orderByRaw("FIELD(stav, 'ceka_na_firmu', 'schvaleno', 'zamitnuto')")
                ->get();
        }

        $jeUcetni = $firma ? $firma->je_ucetni : false;
        $toggleDisabledReason = null;

        if ($firma) {
            $maUcetnihoJakoKlient = UcetniVazba::where('klient_ico', $firma->ico)
                ->whereIn('stav', ['ceka_na_firmu', 'schvaleno'])
                ->exists();
            $maKlientyJakoUcetni = UcetniVazba::where('ucetni_ico', $firma->ico)
                ->whereIn('stav', ['ceka_na_firmu', 'schvaleno'])
                ->exists();

            if ($jeUcetni && $maKlientyJakoUcetni) {
                $toggleDisabledReason = 'Nejprve odeberte všechny klienty.';
            } elseif (!$jeUcetni && $maUcetnihoJakoKlient) {
                $toggleDisabledReason = 'Máte přiřazeného účetního — nelze se stát účetní firmou.';
            }
        }

        $kategorie = $firma ? $firma->kategorie()->orderBy('poradi')->get() : collect();

        $jeSuperadmin = $firma && $user->jeSuperadmin($firma->ico);
        $uzivatele = $firma ? $firma->users()->withPivot('role', 'interni_role')->get() : collect();
        $pozvani = $firma ? Pozvani::where('firma_ico', $firma->ico)->whereNull('accepted_at')->where('expires_at', '>', now())->get() : collect();

        return view('firma.nastaveni', compact('firma', 'vazby', 'jeUcetni', 'toggleDisabledReason', 'kategorie', 'jeSuperadmin', 'uzivatele', 'pozvani'));
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

        if (!$firma->email_doklady) {
            $firma->update(['email_doklady' => $request->ico . '@tuptudu.cz']);
        }

        if ($firma->kategorie()->count() === 0) {
            Firma::seedDefaultKategorie($firma->ico);
        }

        $user->firmy()->attach($firma->ico, ['role' => 'firma']);

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

    public function toggleUcetni(Request $request)
    {
        $firma = auth()->user()->aktivniFirma();
        $user = auth()->user();

        if (!$firma) {
            return response()->json(['ok' => false, 'error' => 'Žádná aktivní firma.'], 400);
        }

        $request->validate(['je_ucetni' => 'required|boolean']);
        $chceZapnout = (bool) $request->je_ucetni;

        if ($chceZapnout) {
            $maUcetniho = UcetniVazba::where('klient_ico', $firma->ico)
                ->whereIn('stav', ['ceka_na_firmu', 'schvaleno'])
                ->exists();
            if ($maUcetniho) {
                return response()->json(['ok' => false, 'error' => 'Máte přiřazeného účetního — nelze se stát účetní firmou.'], 422);
            }
            $firma->update(['je_ucetni' => true]);
            $user->firmy()->updateExistingPivot($firma->ico, ['role' => 'ucetni']);
        } else {
            $maKlienty = UcetniVazba::where('ucetni_ico', $firma->ico)
                ->whereIn('stav', ['ceka_na_firmu', 'schvaleno'])
                ->exists();
            if ($maKlienty) {
                return response()->json(['ok' => false, 'error' => 'Nejprve odeberte všechny klienty.'], 422);
            }
            $firma->update(['je_ucetni' => false]);
            $user->firmy()->updateExistingPivot($firma->ico, ['role' => 'firma']);
        }

        return response()->json(['ok' => true, 'je_ucetni' => $firma->je_ucetni]);
    }

    public function ulozitKategorie(Request $request)
    {
        $firma = auth()->user()->aktivniFirma();

        if (!$firma) {
            return response()->json(['ok' => false, 'error' => 'Žádná aktivní firma.'], 400);
        }

        $request->validate([
            'kategorie' => 'required|array|min:1',
            'kategorie.*.id' => 'nullable|integer',
            'kategorie.*.nazev' => 'required|string|max:100',
            'kategorie.*.popis' => 'nullable|string|max:500',
        ]);

        $existingIds = $firma->kategorie()->pluck('id')->toArray();
        $submittedIds = [];

        foreach ($request->kategorie as $index => $kat) {
            if (!empty($kat['id'])) {
                $kategorie = Kategorie::where('id', $kat['id'])
                    ->where('firma_ico', $firma->ico)
                    ->first();
                if ($kategorie) {
                    $kategorie->update([
                        'nazev' => $kat['nazev'],
                        'popis' => $kat['popis'] ?? null,
                        'poradi' => $kat['poradi'] ?? ($index + 1),
                    ]);
                    $submittedIds[] = $kategorie->id;
                }
            } else {
                $nova = Kategorie::create([
                    'firma_ico' => $firma->ico,
                    'nazev' => $kat['nazev'],
                    'popis' => $kat['popis'] ?? null,
                    'poradi' => $kat['poradi'] ?? ($index + 1),
                ]);
                $submittedIds[] = $nova->id;
            }
        }

        $toDelete = array_diff($existingIds, $submittedIds);
        if (!empty($toDelete)) {
            Kategorie::whereIn('id', $toDelete)->where('firma_ico', $firma->ico)->delete();
        }

        return response()->json(['ok' => true, 'ids' => $submittedIds]);
    }

    public function smazatKategorii(int $id)
    {
        $firma = auth()->user()->aktivniFirma();

        if (!$firma) {
            return response()->json(['ok' => false, 'error' => 'Žádná aktivní firma.'], 400);
        }

        $kategorie = Kategorie::where('id', $id)->where('firma_ico', $firma->ico)->first();

        if (!$kategorie) {
            return response()->json(['ok' => false, 'error' => 'Kategorie nenalezena.'], 404);
        }

        $kategorie->delete();

        return response()->json(['ok' => true]);
    }

    public function pridatUzivatele(Request $request)
    {
        $firma = auth()->user()->aktivniFirma();
        $user = auth()->user();

        if (!$firma || !$user->jeSuperadmin($firma->ico)) {
            abort(403, 'Nemáte oprávnění.');
        }

        $request->validate([
            'jmeno' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'interni_role' => 'required|in:superadmin,spravce',
        ]);

        if (User::where('email', $request->email)->exists()) {
            return back()->withErrors(['email' => 'Tento email je již registrován.'])->withInput();
        }

        $existujici = Pozvani::where('firma_ico', $firma->ico)
            ->where('email', $request->email)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->first();

        if ($existujici) {
            return back()->withErrors(['email' => 'Pozvánka na tento email již existuje.'])->withInput();
        }

        $token = bin2hex(random_bytes(32));

        $pozvani = Pozvani::create([
            'firma_ico' => $firma->ico,
            'jmeno' => $request->jmeno,
            'email' => $request->email,
            'interni_role' => $request->interni_role,
            'token' => $token,
            'expires_at' => now()->addDays(7),
        ]);

        try {
            Mail::to($request->email)->send(new PozvankaDoFirmy($pozvani, $firma));
        } catch (\Throwable $e) {
            Log::error('Chyba odeslání pozvánky: ' . $e->getMessage());
            return back()->withErrors(['email' => 'Nepodařilo se odeslat pozvánku emailem.'])->withInput();
        }

        return redirect()->route('firma.nastaveni')->with('success', "Pozvánka odeslána na {$request->email}.");
    }

    public function odebratUzivatele(int $userId)
    {
        $firma = auth()->user()->aktivniFirma();
        $user = auth()->user();

        if (!$firma || !$user->jeSuperadmin($firma->ico)) {
            abort(403, 'Nemáte oprávnění.');
        }

        if ($userId === $user->id) {
            return back()->withErrors(['user' => 'Nemůžete odebrat sami sebe.']);
        }

        $firma->users()->detach($userId);

        return redirect()->route('firma.nastaveni')->with('success', 'Uživatel byl odebrán.');
    }
}
