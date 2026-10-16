<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PublicSlotController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\SlotController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Publik (tanpa token)
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
Route::get('/public/slots', [PublicSlotController::class, 'index'])->middleware('throttle:public');

// Harus login (admin maupun satpam)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
});

// Admin dan satpam: melihat denah
Route::middleware(['auth:sanctum', 'role:admin,satpam'])->group(function () {
    Route::get('/slots', [SlotController::class, 'index']);
});

// Khusus satpam: mengubah status box
Route::middleware(['auth:sanctum', 'role:satpam'])->group(function () {
    Route::put('/slots/{slot}/status', [SlotController::class, 'updateStatus']);
});

// Khusus admin
Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    // Manajemen akun
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::put('/users/{user}', [UserController::class, 'update']);
    Route::delete('/users/{user}', [UserController::class, 'destroy']);

    // Reservasi
    Route::get('/reservations', [ReservationController::class, 'index']);
    Route::post('/reservations', [ReservationController::class, 'store']);
    Route::put('/reservations/{reservation}', [ReservationController::class, 'update']);
    Route::delete('/reservations/{reservation}', [ReservationController::class, 'destroy']);
});