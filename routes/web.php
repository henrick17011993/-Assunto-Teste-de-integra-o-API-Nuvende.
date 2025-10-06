<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PixController;

Route::get('/pix/form', [PixController::class, 'showForm'])->name('pix.form');
Route::post('/pix/create', [PixController::class, 'create'])->name('pix.create');
Route::get('/pix/status/{chargeId}', [PixController::class, 'status']);
Route::get('/pix/diagnostic', [PixController::class, 'diagnostic']);
Route::get('/pix/tokens', [PixController::class, 'showTokens']);