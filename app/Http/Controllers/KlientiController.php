<?php

namespace App\Http\Controllers;

use App\Models\Firma;
use App\Models\UcetniVazba;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

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

        return redirect()->route('klienti.index')->with('flash', "Klient {$klientFirma->nazev} přidán. Čeká na schválení.");
    }

    /**
     * AJAX: Lookup IČO — ARES + check existence v systému.
     */
    public function lookup(Request $request)
    {
        $request->validate(['ico' => 'required|string|regex:/^\d{8}$/']);

        $firma = auth()->user()->aktivniFirma();
        $ico = $request->ico;

        if ($ico === $firma->ico) {
            return response()->json(['error' => 'Nemůžete přidat sami sebe jako klienta.']);
        }

        // Check zda už je v seznamu klientů
        $existujeVazba = UcetniVazba::where('ucetni_ico', $firma->ico)
            ->where('klient_ico', $ico)
            ->first();
        if ($existujeVazba) {
            return response()->json(['error' => 'Tato firma je již ve vašem seznamu klientů.']);
        }

        // Check zda má jiného účetního
        $jinyUcetni = UcetniVazba::where('klient_ico', $ico)
            ->whereIn('stav', ['ceka_na_firmu', 'schvaleno'])
            ->where('ucetni_ico', '!=', $firma->ico)
            ->exists();
        if ($jinyUcetni) {
            return response()->json(['error' => 'Tato firma již má přiřazeného účetního.']);
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
        $klientFirma = Firma::find($ico);
        if ($klientFirma && $klientFirma->users()->exists()) {
            // Firma je registrována a má uživatele - najdi superadminy
            $superadmins = $klientFirma->users()
                ->wherePivot('interni_role', 'superadmin')
                ->get()
                ->map(fn ($u) => [
                    'id' => $u->id,
                    'masked_email' => User::maskEmail($u->email),
                ]);

            // Check rate-limit: existuje vazba s žádostí < 24h?
            $existujeVazba = UcetniVazba::where('ucetni_ico', $firma->ico)
                ->where('klient_ico', $ico)
                ->first();
            $cooldown = false;
            if ($existujeVazba && $existujeVazba->zadost_odeslana_at && $existujeVazba->zadost_odeslana_at->gt(now()->subHours(24))) {
                $cooldown = true;
            }

            return response()->json([
                'ok' => true,
                'nazev' => $klientFirma->nazev,
                'v_systemu' => true,
                'superadmins' => $superadmins->values(),
                'cooldown' => $cooldown,
            ]);
        }

        // Firma neexistuje v systému - check cooldown
        $existujeVazba = UcetniVazba::where('ucetni_ico', $firma->ico)
            ->where('klient_ico', $ico)
            ->first();
        $cooldown = false;
        if ($existujeVazba && $existujeVazba->zadost_odeslana_at && $existujeVazba->zadost_odeslana_at->gt(now()->subHours(24))) {
            $cooldown = true;
        }

        return response()->json([
            'ok' => true,
            'nazev' => $nazev,
            'v_systemu' => false,
            'cooldown' => $cooldown,
        ]);
    }

    /**
     * AJAX: Pošle žádost o přiřazení klienta emailem.
     */
    public function poslZadost(Request $request)
    {
        $request->validate([
            'ico' => 'required|string|regex:/^\d{8}$/',
            'email' => 'nullable|email',
            'superadmin_id' => 'nullable|integer',
        ]);

        $firma = auth()->user()->aktivniFirma();
        $ico = $request->ico;
        $email = $request->email;

        if ($ico === $firma->ico) {
            return response()->json(['error' => 'Nemůžete přidat sami sebe.']);
        }

        // Rate-limit: check existing vazba
        $existujeVazba = UcetniVazba::where('ucetni_ico', $firma->ico)
            ->where('klient_ico', $ico)
            ->first();
        if ($existujeVazba && $existujeVazba->zadost_odeslana_at && $existujeVazba->zadost_odeslana_at->gt(now()->subHours(24))) {
            return response()->json(['error' => 'Žádost byla odeslána nedávno. Další odeslání bude možné za 24 hodin.']);
        }

        // Zjisti stav firmy
        $klientFirma = Firma::find($ico);
        $vSystemu = $klientFirma && $klientFirma->users()->exists();

        // Pokud neexistuje, vytvoř ji z ARES
        if (!$klientFirma) {
            try {
                $ares = Http::timeout(10)->get(
                    "https://ares.gov.cz/ekonomicke-subjekty-v-be/rest/ekonomicke-subjekty/{$ico}"
                );
                if ($ares->successful()) {
                    $data = $ares->json();
                    $sidlo = $data['sidlo'] ?? [];
                    $klientFirma = Firma::create([
                        'ico' => $ico,
                        'nazev' => $data['obchodniJmeno'] ?? 'IČO ' . $ico,
                        'dic' => $data['dic'] ?? null,
                        'ulice' => $sidlo['nazevUlice'] ?? ($sidlo['textovaAdresa'] ?? null),
                        'mesto' => $sidlo['nazevObce'] ?? null,
                        'psc' => isset($sidlo['psc']) ? (string) $sidlo['psc'] : null,
                    ]);
                } else {
                    $klientFirma = Firma::create([
                        'ico' => $ico,
                        'nazev' => 'IČO ' . $ico,
                    ]);
                }
            } catch (\Exception $e) {
                $klientFirma = Firma::create([
                    'ico' => $ico,
                    'nazev' => 'IČO ' . $ico,
                ]);
            }

            if ($klientFirma->kategorie()->count() === 0) {
                Firma::seedDefaultKategorie($ico);
            }
        }

        // Vytvoř vazbu nebo aktualizuj existující
        if (!$existujeVazba) {
            $existujeVazba = UcetniVazba::create([
                'ucetni_ico' => $firma->ico,
                'klient_ico' => $ico,
                'stav' => 'ceka_na_firmu',
            ]);
        }

        // Urči příjemce emailu
        $prijemce = null;
        if ($vSystemu) {
            if ($request->superadmin_id) {
                $superadmin = $klientFirma->users()
                    ->wherePivot('interni_role', 'superadmin')
                    ->where('sys_users.id', $request->superadmin_id)
                    ->first();
            } else {
                $superadmin = $klientFirma->users()
                    ->wherePivot('interni_role', 'superadmin')
                    ->first();
            }
            if ($superadmin) {
                $prijemce = $superadmin->email;
            }
        } else {
            $prijemce = $email;
        }

        if (!$prijemce) {
            return response()->json(['ok' => true, 'message' => "Žádost vytvořena. Nebylo možné odeslat email (firma nemá správce)."]);
        }

        // Check zda příjemce je existující uživatel (pro rozlišení v emailu)
        $prijemceJeUzivatel = !$vSystemu && User::where('email', $prijemce)->exists();

        // Odešli email
        try {
            Mail::send('emails.zadost-ucetni', [
                'ucetniFirma' => $firma,
                'klientFirma' => $klientFirma,
                'vSystemu' => $vSystemu,
                'prijemceJeUzivatel' => $prijemceJeUzivatel,
                'user' => auth()->user(),
            ], function ($m) use ($prijemce, $firma) {
                $m->to($prijemce)
                  ->subject("TupTuDu - Žádost o vedení účetnictví od {$firma->nazev}");
            });

            $existujeVazba->update(['zadost_odeslana_at' => now()]);
        } catch (\Exception $e) {
            return response()->json(['ok' => true, 'message' => "Žádost vytvořena, ale email se nepodařilo odeslat: {$e->getMessage()}"]);
        }

        return response()->json(['ok' => true, 'message' => "Žádost odeslána na email."]);
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
