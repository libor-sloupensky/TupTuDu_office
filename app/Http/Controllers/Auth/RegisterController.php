<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\AresController;
use App\Http\Controllers\Controller;
use App\Mail\OvereniEmailu;
use App\Models\Firma;
use App\Models\Pozvani;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class RegisterController extends Controller
{
    public function showForm(Request $request)
    {
        $pozvani = null;
        $firma = null;

        if ($request->has('pozvanka')) {
            $pozvani = Pozvani::where('token', $request->pozvanka)
                ->whereNull('accepted_at')
                ->where('expires_at', '>', now())
                ->first();

            if ($pozvani) {
                $firma = Firma::find($pozvani->firma_ico);
            }
        }

        return view('auth.registrace', compact('pozvani', 'firma'));
    }

    public function register(Request $request)
    {
        // Check if this is an invitation registration
        $pozvani = null;
        if ($request->filled('pozvanka_token')) {
            $pozvani = Pozvani::where('token', $request->pozvanka_token)
                ->whereNull('accepted_at')
                ->where('expires_at', '>', now())
                ->first();

            if (!$pozvani) {
                return back()->withErrors(['pozvanka_token' => 'Pozvánka je neplatná nebo vypršela.'])->withInput();
            }
        }

        if ($pozvani) {
            // Invitation registration — no IČO needed
            $request->validate([
                'jmeno' => 'required|string|max:255',
                'prijmeni' => 'required|string|max:255',
                'email' => 'required|email|unique:sys_users,email',
                'telefon' => 'nullable|string|max:20',
                'password' => 'required|string|min:8|confirmed',
            ]);

            $user = User::create([
                'jmeno' => $request->jmeno,
                'prijmeni' => $request->prijmeni,
                'email' => $request->email,
                'telefon' => $request->telefon,
                'password' => $request->password,
            ]);

            $firma = Firma::find($pozvani->firma_ico);
            $user->firmy()->attach($firma->ico, [
                'role' => 'firma',
                'interni_role' => $pozvani->interni_role,
            ]);

            $pozvani->update(['accepted_at' => now()]);
        } else {
            // Standard registration with IČO
            $request->validate([
                'jmeno' => 'required|string|max:255',
                'prijmeni' => 'required|string|max:255',
                'email' => 'required|email|unique:sys_users,email',
                'telefon' => 'nullable|string|max:20',
                'password' => 'required|string|min:8|confirmed',
                'ico' => 'required|string|regex:/^\d{8}$/',
            ]);

            $ares = AresController::fetchAres($request->ico);

            if (!$ares || !$ares['nazev']) {
                return back()->withErrors(['ico' => 'IČO nebylo nalezeno v ARES.'])->withInput();
            }

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

            // First user of this firma = superadmin
            $hasUsers = $firma->users()->exists();
            $interniRole = $hasUsers ? 'spravce' : 'superadmin';

            $user->firmy()->attach($firma->ico, [
                'role' => 'firma',
                'interni_role' => $interniRole,
            ]);
        }

        Auth::login($user);
        session(['aktivni_firma_ico' => $firma->ico]);

        if ($pozvani) {
            // Invitation registration — email is pre-verified (came from invitation link)
            $user->update(['email_verified_at' => now()]);
            return redirect()->route('doklady.index');
        }

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id]
        );

        Mail::to($user->email)->send(new OvereniEmailu($user, $verificationUrl));

        return redirect()->route('verification.notice');
    }
}
