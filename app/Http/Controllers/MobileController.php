<?php

namespace App\Http\Controllers;

use App\Models\Firma;
use App\Models\UcetniVazba;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MobileController extends Controller
{
    public function prihlaseni()
    {
        if (Auth::check()) {
            return redirect()->route('mobile.skenovat');
        }
        return view('mobile.prihlaseni');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if (!Auth::attempt(['email' => $request->email, 'password' => $request->password], true)) {
            return back()->withErrors(['email' => 'Neplatné přihlašovací údaje.'])->withInput();
        }

        $request->session()->regenerate();

        $prvniFirma = Auth::user()->firmy()->first();
        if ($prvniFirma && !session('aktivni_firma_ico')) {
            session(['aktivni_firma_ico' => $prvniFirma->ico]);
        }

        return redirect()->route('mobile.skenovat');
    }

    public function skenovat()
    {
        /** @var User $user */
        $user = Auth::user();

        return view('mobile.skenovat', [
            'firma' => $user->aktivniFirma(),
            'user' => $user,
            'firmy' => $this->dostupneFirmy($user),
        ]);
    }

    /**
     * Přepnutí aktivní firmy přímo z mobilní appky — stejná kontrola přístupu
     * jako FirmaController::prepnout, jen redirect zpět na skener.
     */
    public function prepnoutFirmu(string $ico)
    {
        /** @var User $user */
        $user = Auth::user();

        $jeVlastni = $user->firmy()->where('ico', $ico)->exists();
        if (!$jeVlastni && !$user->jeKlientFirma($ico)) {
            abort(403, 'Nemáte přístup k této firmě.');
        }

        session(['aktivni_firma_ico' => $ico]);

        return redirect()->route('mobile.skenovat');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('mobile.prihlaseni');
    }

    /**
     * Vlastní firmy uživatele + klientské firmy (pokud je účetní),
     * jako jeden seznam pro výběr ve skeneru.
     *
     * @return \Illuminate\Support\Collection<int, Firma>
     */
    private function dostupneFirmy(User $user)
    {
        $vlastni = $user->firmy;

        $ucetniIcos = $user->firmy()->wherePivot('role', 'ucetni')->pluck('ico')->toArray();
        if (empty($ucetniIcos)) {
            return $vlastni;
        }

        $klientIcos = UcetniVazba::whereIn('ucetni_ico', $ucetniIcos)
            ->where('stav', 'schvaleno')
            ->pluck('klient_ico')
            ->toArray();

        if (empty($klientIcos)) {
            return $vlastni;
        }

        return $vlastni->concat(Firma::whereIn('ico', $klientIcos)->get())->unique('ico')->values();
    }
}
