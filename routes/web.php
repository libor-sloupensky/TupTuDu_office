<?php

use App\Http\Controllers\FirmaController;
use App\Http\Controllers\InvoiceController;
use Illuminate\Support\Facades\Route;

Route::get('/', [InvoiceController::class, 'create'])->name('invoices.create');
Route::post('/upload', [InvoiceController::class, 'store'])->name('invoices.store');
Route::get('/doklady', [InvoiceController::class, 'index'])->name('doklady.index');
Route::get('/doklady/{doklad}', [InvoiceController::class, 'show'])->name('doklady.show');

Route::get('/nastaveni', [FirmaController::class, 'nastaveni'])->name('firma.nastaveni');
Route::post('/nastaveni', [FirmaController::class, 'ulozit'])->name('firma.ulozit');
