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

    public function zadnaFirma()
    {
        return view('firma.zadna');
    }

    public function zadostOPristup(Request $request)
    {
        $request->validate([
            'ico' => 'required|string|regex:/^\d{8}$/',
            'jmeno' => 'required|string|max:255',
            'email' => 'required|email|max:255',
        ]);

        $firma = Firma::find($request->ico);
        if (!$firma) {
            return response()->json(['ok' => false, 'error' => 'Firma nenalezena.'], 404);
        }

        $superadmin = $firma->users()->withPivot('interni_role')
            ->wherePivot('interni_role', 'superadmin')->first();

        if (!$superadmin) {
            return response()->json(['ok' => false, 'error' => 'Správce firmy nenalezen.'], 404);
        }

        try {
            Mail::to($superadmin->email)->send(
                new \App\Mail\ZadostOPristup($request->jmeno, $request->email, $firma)
            );
        } catch (\Throwable $e) {
            Log::error('Chyba odeslání žádosti o přístup: ' . $e->getMessage());
            return response()->json(['ok' => false, 'error' => 'Nepodařilo se odeslat žádost.'], 500);
        }

        return response()->json(['ok' => true]);
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
            return response()->json(['ok' => false, 'error' => 'Nemáte oprávnění.'], 403);
        }

        $request->validate([
            'jmeno' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'interni_role' => 'required|in:superadmin,spravce',
        ]);

        // Check if user already exists
        $existujiciUser = User::where('email', $request->email)->first();

        if ($existujiciUser) {
            // Already member of this firma?
            if ($existujiciUser->firmy()->where('ico', $firma->ico)->exists()) {
                return response()->json(['ok' => false, 'error' => 'Uživatel je již členem firmy.']);
            }

            // Attach existing user directly
            $existujiciUser->firmy()->attach($firma->ico, [
                'role' => 'firma',
                'interni_role' => $request->interni_role,
            ]);

            return response()->json(['ok' => true, 'message' => "{$existujiciUser->cele_jmeno} přidán do firmy."]);
        }

        // Check for pending invitation
        $existujiciPozvani = Pozvani::where('firma_ico', $firma->ico)
            ->where('email', $request->email)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->first();

        if ($existujiciPozvani) {
            return response()->json(['ok' => false, 'error' => 'Pozvánka na tento email již existuje.']);
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
            return response()->json(['ok' => false, 'error' => 'Nepodařilo se odeslat pozvánku emailem.']);
        }

        return response()->json(['ok' => true, 'message' => "Pozvánka odeslána na {$request->email}."]);
    }

    public function upravitUzivatele(Request $request, int $userId)
    {
        $firma = auth()->user()->aktivniFirma();
        $user = auth()->user();

        if (!$firma || !$user->jeSuperadmin($firma->ico)) {
            return response()->json(['ok' => false, 'error' => 'Nemáte oprávnění.'], 403);
        }

        $request->validate([
            'jmeno' => 'nullable|string|max:255',
            'interni_role' => 'nullable|in:superadmin,spravce',
        ]);

        $targetUser = User::find($userId);
        if (!$targetUser || !$targetUser->firmy()->where('ico', $firma->ico)->exists()) {
            return response()->json(['ok' => false, 'error' => 'Uživatel nenalezen.'], 404);
        }

        // Update name if provided
        if ($request->filled('jmeno')) {
            $parts = explode(' ', $request->jmeno, 2);
            $targetUser->update([
                'jmeno' => $parts[0],
                'prijmeni' => $parts[1] ?? '',
            ]);
        }

        // Update role if provided
        if ($request->filled('interni_role')) {
            $targetUser->firmy()->updateExistingPivot($firma->ico, [
                'interni_role' => $request->interni_role,
            ]);
        }

        return response()->json(['ok' => true]);
    }

    public function odebratUzivatele(int $userId)
    {
        $firma = auth()->user()->aktivniFirma();
        $user = auth()->user();

        if (!$firma || !$user->jeSuperadmin($firma->ico)) {
            return response()->json(['ok' => false, 'error' => 'Nemáte oprávnění.'], 403);
        }

        if ($userId === $user->id) {
            return response()->json(['ok' => false, 'error' => 'Nemůžete odebrat sami sebe.']);
        }

        $firma->users()->detach($userId);

        return response()->json(['ok' => true]);
    }
}
