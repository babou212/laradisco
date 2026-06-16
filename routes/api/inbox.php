<?php

use App\Http\Controllers\Api\InboxController;
use Illuminate\Support\Facades\Route;

Route::get('/', [InboxController::class, 'index'])->name('index');
Route::post('/ack', [InboxController::class, 'ack'])->name('ack');
