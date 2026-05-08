<?php

namespace App\Http\Controllers;

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

        return redirect()->route('mobile.skenovat');
    }

    public function skenovat()
    {
        $user = Auth::user();
        $firma = $user->aktivniFirma();

        return view('mobile.skenovat', [
            'firma' => $firma,
            'user' => $user,
        ]);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('mobile.prihlaseni');
    }
}
