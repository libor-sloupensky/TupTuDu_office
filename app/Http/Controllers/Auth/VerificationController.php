<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\OvereniEmailu;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class VerificationController extends Controller
{
    public function notice()
    {
        if (auth()->user()->hasVerifiedEmail()) {
            return redirect()->route('doklady.index');
        }

        return view('auth.overeni-emailu');
    }

    public function verify(Request $request, int $id)
    {
        if (!$request->hasValidSignature()) {
            abort(403, 'Neplatný nebo vypršelý odkaz pro ověření.');
        }

        $user = User::findOrFail($id);

        if (!$user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        if (!Auth::check()) {
            Auth::login($user);
            $prvniFirma = $user->firmy()->first();
            if ($prvniFirma) {
                session(['aktivni_firma_ico' => $prvniFirma->ico]);
            }
        }

        return redirect()->route('doklady.index')->with('flash', 'Email úspěšně ověřen.');
    }

    public function resend(Request $request)
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->route('doklady.index');
        }

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id]
        );

        Mail::to($user->email)->send(new OvereniEmailu($user, $verificationUrl));

        return back()->with('flash', 'Ověřovací email byl znovu odeslán.');
    }
}
