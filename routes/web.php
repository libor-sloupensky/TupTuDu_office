<?php

use App\Http\Controllers\AresController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\VerificationController;
use App\Http\Controllers\FirmaController;
use App\Http\Controllers\GoogleDriveController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\KlientiController;
use App\Http\Controllers\VazbyController;
use Illuminate\Support\Facades\Route;

// --- Public pages ---
Route::get('/privacy', fn() => view('privacy'))->name('privacy');

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

// --- Cron endpoint (tajný token) ---
Route::get('/cron/{token}', function (string $token) {
    if ($token !== 'f8k2Ld9xQm4vR7nW') {
        abort(404);
    }
    Illuminate\Support\Facades\Artisan::call('doklady:process-email');
    $output = Illuminate\Support\Facades\Artisan::output();
    return response($output, 200)->header('Content-Type', 'text/plain');
})->middleware('throttle:6,1');

// --- Dočasný test odchozí pošty ---
Route::get('/test-mail/{token}', function (string $token) {
    if ($token !== 'f8k2Ld9xQm4vR7nW') {
        abort(404);
    }
    try {
        Illuminate\Support\Facades\Mail::mailer('doklady')
            ->to('libor@sloupensky.net')
            ->send(new App\Mail\OdpovedNaDoklad('Toto je testovací email z TupTuDu.', 'Test'));
        return response("OK - email odeslán", 200)->header('Content-Type', 'text/plain');
    } catch (\Throwable $e) {
        return response("CHYBA: " . $e->getMessage(), 200)->header('Content-Type', 'text/plain');
    }
});

// --- Žádost o přístup k firmě (bez auth, throttle) ---
Route::post('/zadost-o-pristup', [FirmaController::class, 'zadostOPristup'])->middleware('throttle:3,60')->name('firma.zadostOPristup');

// --- Email verification (auth, not yet verified) ---
Route::middleware('auth')->group(function () {
    Route::get('/email/overeni', [VerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/email/overeni/{id}', [VerificationController::class, 'verify'])->name('verification.verify');
    Route::post('/email/overeni/znovu', [VerificationController::class, 'resend'])->name('verification.resend');
});

// --- Auth + verified ---
Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('/odhlaseni', [LoginController::class, 'logout'])->name('logout');

    Route::get('/firma/zadna', [FirmaController::class, 'zadnaFirma'])->name('firma.zadna');
    Route::post('/firma/lookup-pristup', [FirmaController::class, 'lookupPristup'])->name('firma.lookupPristup');
    Route::post('/firma/vytvorit', [FirmaController::class, 'vytvorFirmu'])->name('firma.vytvorFirmu');
    Route::post('/firma/prepnout/{ico}', [FirmaController::class, 'prepnout'])->name('firma.prepnout');
});

// --- Google Drive OAuth ---
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/google/redirect', [GoogleDriveController::class, 'redirect'])->name('google.redirect');
    Route::get('/google/callback', [GoogleDriveController::class, 'callback'])->name('google.callback');
    Route::post('/google/disconnect', [GoogleDriveController::class, 'disconnect'])->name('google.disconnect');
});

// --- Cron endpoint: Google Drive sync ---
Route::get('/cron-drive/{token}', function (string $token) {
    if ($token !== 'f8k2Ld9xQm4vR7nW') {
        abort(404);
    }
    Illuminate\Support\Facades\Artisan::call('doklady:sync-drive');
    $output = Illuminate\Support\Facades\Artisan::output();
    return response($output, 200)->header('Content-Type', 'text/plain');
})->middleware('throttle:6,1');

// --- Auth + verified + firma ---
Route::middleware(['auth', 'verified', 'firma'])->group(function () {
    Route::get('/', fn() => redirect()->route('doklady.index'));

    // Doklady
    Route::post('/upload', [InvoiceController::class, 'store'])->name('invoices.store');
    Route::get('/doklady', [InvoiceController::class, 'index'])->name('doklady.index');
    Route::post('/doklady/ai-search', [InvoiceController::class, 'aiSearch'])->name('doklady.aiSearch');
    Route::get('/doklady/mesic/{mesic}/zip', [InvoiceController::class, 'downloadMonth'])->name('doklady.downloadMonth');
    Route::get('/doklady/{doklad}', [InvoiceController::class, 'show'])->name('doklady.show');
    Route::get('/doklady/{doklad}/stahnout', [InvoiceController::class, 'download'])->name('doklady.download');
    Route::get('/doklady/{doklad}/nahled', [InvoiceController::class, 'preview'])->name('doklady.preview');
    Route::get('/doklady/{doklad}/nahled-original', [InvoiceController::class, 'previewOriginal'])->name('doklady.previewOriginal');
    Route::patch('/doklady/{doklad}', [InvoiceController::class, 'update'])->name('doklady.update');
    Route::delete('/doklady/{doklad}', [InvoiceController::class, 'destroy'])->name('doklady.destroy');

    // Nastaveni firmy
    Route::get('/nastaveni', [FirmaController::class, 'nastaveni'])->name('firma.nastaveni');
    Route::post('/nastaveni', [FirmaController::class, 'ulozit'])->name('firma.ulozit');
    Route::post('/nastaveni/ares', [FirmaController::class, 'obnovitAres'])->name('firma.obnovitAres');
    Route::post('/nastaveni/toggle-ucetni', [FirmaController::class, 'toggleUcetni'])->name('firma.toggleUcetni');
    Route::post('/nastaveni/kategorie', [FirmaController::class, 'ulozitKategorie'])->name('firma.ulozitKategorie');
    Route::delete('/nastaveni/kategorie/{id}', [FirmaController::class, 'smazatKategorii'])->name('firma.smazatKategorii');
    Route::post('/nastaveni/email-system-toggle', [FirmaController::class, 'toggleSystemEmail'])->name('firma.toggleSystemEmail');
    Route::post('/nastaveni/email-vlastni', [FirmaController::class, 'ulozitVlastniEmail'])->name('firma.ulozitVlastniEmail');
    Route::post('/nastaveni/email-vlastni-test', [FirmaController::class, 'testEmailVlastni'])->name('firma.testEmailVlastni');
    Route::post('/nastaveni/drive-sablona', [FirmaController::class, 'ulozitDriveSablona'])->name('firma.ulozitDriveSablona');
    Route::post('/nastaveni/uzivatele', [FirmaController::class, 'pridatUzivatele'])->name('firma.pridatUzivatele');
    Route::patch('/nastaveni/uzivatele/{userId}', [FirmaController::class, 'upravitUzivatele'])->name('firma.upravitUzivatele');
    Route::delete('/nastaveni/uzivatele/{userId}', [FirmaController::class, 'odebratUzivatele'])->name('firma.odebratUzivatele');

    // Klienti (pouze ucetni)
    Route::middleware('role:ucetni')->group(function () {
        Route::get('/klienti', [KlientiController::class, 'index'])->name('klienti.index');
        Route::post('/klienti', [KlientiController::class, 'store'])->name('klienti.store');
        Route::post('/klienti/lookup', [KlientiController::class, 'lookup'])->name('klienti.lookup');
        Route::post('/klienti/zadost', [KlientiController::class, 'poslZadost'])->name('klienti.poslZadost');
        Route::delete('/klienti/{klientIco}', [KlientiController::class, 'destroy'])->name('klienti.destroy');
    });

    // Vazby - approve/reject actions (klient může mít libovolnou roli)
    Route::middleware('role:firma,dodavatel,ucetni')->group(function () {
        Route::post('/vazby/{id}/schvalit', [VazbyController::class, 'approve'])->name('vazby.approve');
        Route::post('/vazby/{id}/zamitnout', [VazbyController::class, 'reject'])->name('vazby.reject');
        Route::post('/vazby/{id}/odpojit', [VazbyController::class, 'disconnect'])->name('vazby.disconnect');
        Route::post('/vazby/{id}/opravneni', [VazbyController::class, 'updateOpravneni'])->name('vazby.updateOpravneni');
    });
});
