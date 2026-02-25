<?php

namespace App\Http\Controllers;

use App\Mail\PozvankaDoFirmy;
use App\Models\Firma;
use App\Services\DrivePathBuilder;
use App\Models\Kategorie;
use App\Models\Pozvani;
use App\Models\UcetniVazba;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Webklex\IMAP\ClientManager;

class FirmaController extends Controller
{
    public function nastaveni(Request $request)
    {
        $user = auth()->user();

        // Účetní prohlížející klienta nemá přístup k nastavení klienta
        if ($user->prohlizimKlienta()) {
            return redirect()->route('doklady.index')->with('flash', 'K nastavení klientské firmy nemáte přístup.');
        }

        $firma = $user->aktivniFirma();

        // Vazby kde tato firma je klient (účetní firmy napojené na nás)
        $vazby = collect();
        if ($firma) {
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

        // Klienti (pokud jsme účetní firma)
        $klientVazby = collect();
        if ($firma && $jeUcetni) {
            $klientVazby = UcetniVazba::where('ucetni_ico', $firma->ico)
                ->with('klientFirma')
                ->orderByRaw("FIELD(stav, 'schvaleno', 'ceka_na_firmu', 'zamitnuto')")
                ->get();
        }

        // Počet čekajících žádostí (pro badge)
        $cekajiciVazby = $vazby->where('stav', 'ceka_na_firmu')->count();

        // Auto-rozbalení sekce z emailu nebo při čekajících žádostech
        $expandUcetni = $request->has('ucetni') || $cekajiciVazby > 0;

        $kategorie = $firma ? $firma->kategorie()->orderBy('poradi')->get() : collect();

        $jeSuperadmin = $firma && $user->jeSuperadmin($firma->ico);
        $uzivatele = $firma ? $firma->users()->withPivot('role', 'interni_role')->get() : collect();
        $pozvani = $firma ? Pozvani::where('firma_ico', $firma->ico)->whereNull('accepted_at')->where('expires_at', '>', now())->get() : collect();

        return view('firma.nastaveni', compact('firma', 'vazby', 'jeUcetni', 'toggleDisabledReason', 'klientVazby', 'cekajiciVazby', 'expandUcetni', 'kategorie', 'jeSuperadmin', 'uzivatele', 'pozvani'));
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

        // Google Drive šablona
        if ($request->has('google_drive_sablona')) {
            $sablona = trim($request->google_drive_sablona);
            if ($sablona === '') {
                $data['google_drive_sablona'] = null; // reset na default
            } else {
                $builder = new DrivePathBuilder();
                $errors = $builder->validate($sablona);
                if (!empty($errors)) {
                    return back()->withErrors(['google_drive_sablona' => implode(' ', $errors)])->withInput();
                }
                $data['google_drive_sablona'] = $sablona;
            }
        }

        $firma->update($data);

        return redirect()->route('firma.nastaveni')->with('success', 'Nastavení uloženo.');
    }

    public function zadnaFirma()
    {
        return view('firma.zadna');
    }

    /**
     * AJAX: Lookup IČO pro stránku "žádná firma" — ARES + systém check.
     */
    public function lookupPristup(Request $request)
    {
        $request->validate(['ico' => 'required|string|regex:/^\d{8}$/']);
        $ico = $request->ico;
        $user = auth()->user();

        // Check zda uživatel už není členem této firmy
        if ($user->firmy()->where('ico', $ico)->exists()) {
            return response()->json(['error' => 'Již jste členem této firmy.']);
        }

        // ARES lookup
        $nazev = null;
        try {
            $ares = Http::timeout(10)->get(
                "https://ares.gov.cz/ekonomicke-subjekty-v-be/rest/ekonomicke-subjekty/{$ico}"
            );
            if ($ares->successful()) {
                $nazev = $ares->json('obchodniJmeno');
            }
        } catch (\Exception $e) {}

        if (!$nazev) {
            return response()->json(['error' => 'IČO nebylo nalezeno v ARES.']);
        }

        // Check zda firma existuje v systému (= má alespoň jednoho uživatele)
        $firma = Firma::find($ico);
        if (!$firma || !$firma->users()->exists()) {
            return response()->json([
                'ok' => true,
                'nazev' => $nazev,
                'v_systemu' => false,
                'can_create' => true,
            ]);
        }

        // Najdi superadminy
        $superadmins = $firma->users()
            ->wherePivot('interni_role', 'superadmin')
            ->get()
            ->map(fn ($u) => [
                'id' => $u->id,
                'masked_email' => User::maskEmail($u->email),
            ]);

        return response()->json([
            'ok' => true,
            'nazev' => $firma->nazev,
            'v_systemu' => true,
            'superadmins' => $superadmins->values(),
        ]);
    }

    /**
     * Vytvoří/přihlásí firmu pro uživatele bez firmy.
     */
    public function vytvorFirmu(Request $request)
    {
        $request->validate(['ico' => 'required|string|regex:/^\d{8}$/']);
        $ico = $request->ico;
        $user = auth()->user();

        // Nelze pokud uživatel už je členem
        if ($user->firmy()->where('ico', $ico)->exists()) {
            return back()->withErrors(['ico' => 'Již jste členem této firmy.']);
        }

        $firma = Firma::find($ico);

        // Pokud firma existuje a má uživatele → nelze převzít
        if ($firma && $firma->users()->exists()) {
            return back()->withErrors(['ico' => 'Tato firma již má správce. Požádejte o přiřazení.']);
        }

        // Pokud firma neexistuje, vytvoř z ARES
        if (!$firma) {
            try {
                $ares = Http::timeout(10)->get(
                    "https://ares.gov.cz/ekonomicke-subjekty-v-be/rest/ekonomicke-subjekty/{$ico}"
                );
                if ($ares->successful()) {
                    $data = $ares->json();
                    $sidlo = $data['sidlo'] ?? [];
                    $firma = Firma::create([
                        'ico' => $ico,
                        'nazev' => $data['obchodniJmeno'] ?? 'IČO ' . $ico,
                        'dic' => $data['dic'] ?? null,
                        'ulice' => $sidlo['nazevUlice'] ?? ($sidlo['textovaAdresa'] ?? null),
                        'mesto' => $sidlo['nazevObce'] ?? null,
                        'psc' => isset($sidlo['psc']) ? (string) $sidlo['psc'] : null,
                    ]);
                } else {
                    return back()->withErrors(['ico' => 'IČO nebylo nalezeno v ARES.']);
                }
            } catch (\Exception $e) {
                return back()->withErrors(['ico' => 'Nepodařilo se ověřit IČO v ARES.']);
            }
        }

        // Seed default kategorie
        if ($firma->kategorie()->count() === 0) {
            Firma::seedDefaultKategorie($ico);
        }

        // Nastav email pro doklady
        if (!$firma->email_doklady) {
            $firma->update(['email_doklady' => $ico . '@doklady.tuptudu.cz']);
        }

        // Připoj uživatele jako superadmin
        $firma->users()->attach($user->id, ['interni_role' => 'superadmin']);

        // Nastav jako aktivní firmu
        session(['aktivni_firma_ico' => $ico]);

        return redirect()->route('doklady.index')->with('flash', "Firma {$firma->nazev} byla vytvořena a přiřazena.");
    }

    public function zadostOPristup(Request $request)
    {
        $request->validate([
            'ico' => 'required|string|regex:/^\d{8}$/',
            'jmeno' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'superadmin_id' => 'nullable|integer',
        ]);

        $firma = Firma::find($request->ico);
        if (!$firma) {
            return response()->json(['ok' => false, 'error' => 'Firma nenalezena.'], 404);
        }

        if ($request->superadmin_id) {
            $superadmin = $firma->users()->withPivot('interni_role')
                ->wherePivot('interni_role', 'superadmin')
                ->where('sys_users.id', $request->superadmin_id)
                ->first();
        } else {
            $superadmin = $firma->users()->withPivot('interni_role')
                ->wherePivot('interni_role', 'superadmin')->first();
        }

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

        $jeVlastni = $user->firmy()->where('ico', $ico)->exists();
        $jeKlient = !$jeVlastni && $user->jeKlientFirma($ico);

        if (!$jeVlastni && !$jeKlient) {
            abort(403, 'Nemáte přístup k této firmě.');
        }

        session(['aktivni_firma_ico' => $ico]);

        // Při přepnutí na klienta vždy na doklady (nemá přístup k nastavení)
        if ($jeKlient) {
            return redirect()->route('doklady.index')->with('flash', 'Přepnuto na klientskou firmu.');
        }

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

    public function toggleSystemEmail(Request $request)
    {
        $firma = auth()->user()->aktivniFirma();

        if (!$firma) {
            return response()->json(['ok' => false, 'error' => 'Žádná aktivní firma.'], 400);
        }

        $request->validate(['aktivni' => 'required|boolean']);

        $firma->update(['email_system_aktivni' => (bool) $request->aktivni]);

        // Ensure email_doklady is set
        if ($request->aktivni && !$firma->email_doklady) {
            $firma->update(['email_doklady' => $firma->ico . '@tuptudu.cz']);
        }

        return response()->json(['ok' => true, 'email' => $firma->ico . '@tuptudu.cz']);
    }

    public function ulozitVlastniEmail(Request $request)
    {
        $firma = auth()->user()->aktivniFirma();

        if (!$firma) {
            return response()->json(['ok' => false, 'error' => 'Žádná aktivní firma.'], 400);
        }

        $request->validate([
            'aktivni' => 'required|boolean',
            'email' => 'nullable|email|max:255',
            'host' => 'nullable|string|max:255',
            'port' => 'nullable|integer|min:1|max:65535',
            'sifrovani' => 'nullable|in:ssl,tls,none',
            'uzivatel' => 'nullable|string|max:255',
            'heslo' => 'nullable|string|max:255',
        ]);

        $firma->update([
            'email_vlastni_aktivni' => (bool) $request->aktivni,
            'email_vlastni' => $request->email,
            'email_vlastni_host' => $request->host,
            'email_vlastni_port' => $request->port,
            'email_vlastni_sifrovani' => $request->sifrovani ?? 'ssl',
            'email_vlastni_uzivatel' => $request->uzivatel,
            'email_vlastni_heslo' => $request->heslo,
        ]);

        return response()->json(['ok' => true]);
    }

    public function testEmailVlastni(Request $request)
    {
        $request->validate([
            'host' => 'required|string|max:255',
            'port' => 'required|integer|min:1|max:65535',
            'sifrovani' => 'required|in:ssl,tls,none',
            'uzivatel' => 'required|string|max:255',
            'heslo' => 'required|string|max:255',
        ]);

        try {
            $cm = new ClientManager();
            $client = $cm->make([
                'host' => $request->host,
                'port' => $request->port,
                'encryption' => $request->sifrovani === 'none' ? false : $request->sifrovani,
                'validate_cert' => true,
                'username' => $request->uzivatel,
                'password' => $request->heslo,
                'protocol' => 'imap',
            ]);

            $client->connect();
            $folder = $client->getFolder('INBOX');
            $unseen = $folder->query()->unseen()->count();
            $client->disconnect();

            return response()->json([
                'ok' => true,
                'message' => "Připojení úspěšné. Nepřečtených zpráv: {$unseen}.",
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => 'Nepodařilo se připojit: ' . $e->getMessage(),
            ]);
        }
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
