<?php

use App\Http\Controllers\Api\PartnerController;
use Illuminate\Support\Facades\Route;

/**
 * Partnerské API — přihlášení API klíčem, bez session.
 *
 * Vědomě tu nejsou žádné routy k dokladům. Partner je obchodní kanál, ne
 * správce dat; do dokladů smí jen účetní firma se schválenou vazbou, a ta
 * chodí přes web pod svým uživatelem.
 */
Route::middleware(['partner', 'throttle:60,1'])->prefix('partner')->group(function () {
    Route::get('/firmy', [PartnerController::class, 'seznamFirem'])->name('api.partner.firmy');
    Route::post('/firmy', [PartnerController::class, 'napojitFirmu'])->name('api.partner.napojit');
    Route::get('/firmy/{ico}', [PartnerController::class, 'detailFirmy'])
        ->where('ico', '\d{8}')
        ->name('api.partner.detail');
    Route::delete('/firmy/{ico}', [PartnerController::class, 'odpojitFirmu'])
        ->where('ico', '\d{8}')
        ->name('api.partner.odpojit');
});
