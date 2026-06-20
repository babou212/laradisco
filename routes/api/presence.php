<?php

use App\Http\Controllers\Api\PresenceController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PresenceController::class, 'index'])->name('index');
Route::patch('/', [PresenceController::class, 'update'])->name('update');
Route::patch('/activity', [PresenceController::class, 'updateActivity'])->name('activity');
Route::post('/heartbeat', [PresenceController::class, 'heartbeat'])->name('heartbeat');
