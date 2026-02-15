<?php

use App\Http\Controllers\AresController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\VerificationController;
use App\Http\Controllers\FirmaController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\KlientiController;
use App\Http\Controllers\VazbyController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// --- Diagnostic upload route (token-protected, bypasses auth, goes through full HTTP kernel) ---
Route::post('/upload-diag/{token}', function (\Illuminate\Http\Request $request, string $token) {
    if ($token !== 'tuptudu-diag-2026-xK9m') {
        abort(403);
    }
    // Login as the first available user
    $user = \App\Models\User::first();
    Auth::login($user);
    session(['aktivni_firma_ico' => $user->firmy()->first()?->ico ?? '07994605']);

    // Delegate to the real InvoiceController::store() - same code path as /upload
    return app(InvoiceController::class)->store($request);
})->middleware('web');

// --- Guest routes ---
Route::middleware('guest')->group(function () {
    Route::get('/registrace', [RegisterController::class, 'showForm'])->name('register');
    Route::post('/registrace', [RegisterController::class, 'register']);

    Route::get('/prihlaseni', [LoginController::class, 'showForm'])->name('login');
    Route::post('/prihlaseni', [LoginController::class, 'login']);

    Route::get('/zapomenute-heslo', [PasswordResetController::class, 'showForgotForm'])->name('password.request');
    Route::post('/zapomenute-heslo', [PasswordResetController::class, 'sendResetLink'])->name('password.email');
    Route::get('/reset-hesla/{token}', [PasswordResetController::class, 'showResetForm'])->name('password.reset');
    Route::post('/reset-hesla', [PasswordResetController::class, 'reset'])->name('password.update');
});

// --- ARES (bez auth) ---
Route::get('/api/ares/{ico}', [AresController::class, 'lookup'])->middleware('throttle:30,1')->name('ares.lookup');

// --- Email verification (auth, not yet verified) ---
Route::middleware('auth')->group(function () {
    Route::get('/email/overeni', [VerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/email/overeni/{id}', [VerificationController::class, 'verify'])->middleware('signed')->name('verification.verify');
    Route::post('/email/overeni/znovu', [VerificationController::class, 'resend'])->name('verification.resend');
});

// --- Auth + verified ---
Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('/odhlaseni', [LoginController::class, 'logout'])->name('logout');

    Route::get('/firma/pridat', [FirmaController::class, 'pridatFirmu'])->name('firma.pridat');
    Route::post('/firma/pridat', [FirmaController::class, 'ulozitNovou'])->name('firma.ulozitNovou');
    Route::post('/firma/prepnout/{ico}', [FirmaController::class, 'prepnout'])->name('firma.prepnout');
});

// --- Auth + verified + firma ---
Route::middleware(['auth', 'verified', 'firma'])->group(function () {
    Route::get('/', fn() => redirect()->route('doklady.index'));

    // Doklady
    Route::post('/upload', [InvoiceController::class, 'store'])->name('invoices.store');
    Route::get('/doklady', [InvoiceController::class, 'index'])->name('doklady.index');
    Route::get('/doklady/mesic/{mesic}/zip', [InvoiceController::class, 'downloadMonth'])->name('doklady.downloadMonth');
    Route::get('/doklady/{doklad}', [InvoiceController::class, 'show'])->name('doklady.show');
    Route::get('/doklady/{doklad}/stahnout', [InvoiceController::class, 'download'])->name('doklady.download');
    Route::get('/doklady/{doklad}/nahled', [InvoiceController::class, 'preview'])->name('doklady.preview');
    Route::patch('/doklady/{doklad}', [InvoiceController::class, 'update'])->name('doklady.update');
    Route::delete('/doklady/{doklad}', [InvoiceController::class, 'destroy'])->name('doklady.destroy');

    // Nastaveni firmy
    Route::get('/nastaveni', [FirmaController::class, 'nastaveni'])->name('firma.nastaveni');
    Route::post('/nastaveni', [FirmaController::class, 'ulozit'])->name('firma.ulozit');
    Route::post('/nastaveni/ares', [FirmaController::class, 'obnovitAres'])->name('firma.obnovitAres');

    // Klienti (pouze ucetni)
    Route::middleware('role:ucetni')->group(function () {
        Route::get('/klienti', [KlientiController::class, 'index'])->name('klienti.index');
        Route::post('/klienti', [KlientiController::class, 'store'])->name('klienti.store');
        Route::delete('/klienti/{klientIco}', [KlientiController::class, 'destroy'])->name('klienti.destroy');
    });

    // Vazby (firma, dodavatel)
    Route::middleware('role:firma,dodavatel')->group(function () {
        Route::get('/vazby', [VazbyController::class, 'index'])->name('vazby.index');
        Route::post('/vazby/{id}/schvalit', [VazbyController::class, 'approve'])->name('vazby.approve');
        Route::post('/vazby/{id}/zamitnout', [VazbyController::class, 'reject'])->name('vazby.reject');
    });
});
