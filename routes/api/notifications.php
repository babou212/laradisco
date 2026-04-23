<?php

use App\Http\Controllers\Api\NotificationController;
use Illuminate\Support\Facades\Route;

Route::get('/', [NotificationController::class, 'index'])->name('index');
Route::patch('/{notification}', [NotificationController::class, 'markAsRead'])->name('read');
Route::patch('/', [NotificationController::class, 'markAllAsRead'])->name('read-all');
