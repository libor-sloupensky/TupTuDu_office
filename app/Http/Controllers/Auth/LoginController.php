<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function showForm()
    {
        return view('auth.prihlaseni');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (!Auth::attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Nesprávný email nebo heslo.'])->withInput(['email' => $request->email]);
        }

        $request->session()->regenerate();

        $user = Auth::user();
        $prvniFirma = $user->firmy()->first();
        if ($prvniFirma) {
            session(['aktivni_firma_ico' => $prvniFirma->ico]);
        }

        return redirect()->intended(route('doklady.index'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
