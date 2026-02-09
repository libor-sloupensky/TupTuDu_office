<?php

use App\Http\Controllers\InvoiceController;
use Illuminate\Support\Facades\Route;

Route::get('/', [InvoiceController::class, 'create'])->name('invoices.create');
Route::post('/upload', [InvoiceController::class, 'store'])->name('invoices.store');
