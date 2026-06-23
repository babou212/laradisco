<?php

use App\Http\Controllers\Api\SoundboardController;
use Illuminate\Support\Facades\Route;

Route::get('/sounds', [SoundboardController::class, 'index'])->name('sounds.index');
Route::post('/sounds', [SoundboardController::class, 'store'])->name('sounds.store');
Route::delete('/sounds/{sound}', [SoundboardController::class, 'destroy'])->name('sounds.destroy');
