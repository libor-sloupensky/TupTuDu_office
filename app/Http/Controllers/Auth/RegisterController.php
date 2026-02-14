<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\OvereniEmailu;
use App\Models\Firma;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class RegisterController extends Controller
{
    public function showForm()
    {
        return view('auth.registrace');
    }

    public function register(Request $request)
    {
        $request->validate([
            'jmeno' => 'required|string|max:255',
            'prijmeni' => 'required|string|max:255',
            'email' => 'required|email|unique:sys_users,email',
            'telefon' => 'nullable|string|max:20',
            'password' => 'required|string|min:8|confirmed',
            'ico' => 'required|string|regex:/^\d{8}$/',
            'role' => 'required|in:ucetni,firma,dodavatel',
            'nazev' => 'required|string|max:255',
            'dic' => 'nullable|string|max:20',
            'ulice' => 'nullable|string|max:255',
            'mesto' => 'nullable|string|max:255',
            'psc' => 'nullable|string|max:10',
        ]);

        $user = User::create([
            'jmeno' => $request->jmeno,
            'prijmeni' => $request->prijmeni,
            'email' => $request->email,
            'telefon' => $request->telefon,
            'password' => $request->password,
        ]);

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

        Auth::login($user);
        session(['aktivni_firma_ico' => $firma->ico]);

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id]
        );

        Mail::to($user->email)->send(new OvereniEmailu($user, $verificationUrl));

        return redirect()->route('verification.notice');
    }
}
