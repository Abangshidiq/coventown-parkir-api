<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PublicSlotController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\SlotController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
| Semua alamat berawalan /api. Setelah login, kirim header: Authorization: Bearer <token>
| Status box: empty = abu-abu, occupied = biru, booked = merah
| Middleware 'assign' memilihkan box untuk reservasi yang jendela 7 jamnya sudah tiba,
| setiap kali denah atau daftar reservasi diminta (pengganti scheduler).
*/

// ===== LOGIN =====

// Masuk. Kirim: username, pin (6 angka, bentuk teks). Balasan: token + role (admin/satpam)
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

Route::middleware('auth:sanctum')->group(function () {
    // Cek siapa yang login dan apakah token masih berlaku (id, username, role)
    Route::get('/me', [AuthController::class, 'me']);

    // Keluar (hanya di perangkat ini)
    Route::post('/logout', [AuthController::class, 'logout']);
});

// ===== SLOT PARKIR =====

// Denah untuk CUSTOMER, tanpa login. Isi: code + status saja
Route::get('/public/slots', [PublicSlotController::class, 'index'])->middleware(['throttle:public', 'assign']);

// Denah untuk ADMIN dan SATPAM. Isi: id, code, status, locked (ikon kunci), reservation (data pemesan)
Route::middleware(['auth:sanctum', 'role:admin,satpam', 'assign'])->group(function () {
    Route::get('/slots', [SlotController::class, 'index']);
});

// Hanya SATPAM: ubah status box. Kirim: status = "occupied" (terisi) atau "empty" (kosong)
// Ditolak (409) kalau box merah masih terkunci
Route::middleware(['auth:sanctum', 'role:satpam'])->group(function () {
    Route::put('/slots/{slot}/status', [SlotController::class, 'updateStatus']);
});

// ===== KHUSUS ADMIN =====
Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {

    // --- Akun ---

    // Daftar akun (username, pin, role, is_active)
    Route::get('/users', [UserController::class, 'index']);

    // Tambah akun. Kirim: username, pin, role (admin/satpam)
    Route::post('/users', [UserController::class, 'store']);

    // Ubah akun. Kirim hanya yang diubah: username, pin, role, is_active
    Route::put('/users/{user}', [UserController::class, 'update']);

    // Hapus akun
    Route::delete('/users/{user}', [UserController::class, 'destroy']);

    // --- Reservasi ---

    // Daftar reservasi. Opsional ?date=YYYY-MM-DD. has_parking = sudah/belum dapat parkir
    Route::get('/reservations', [ReservationController::class, 'index'])->middleware('assign');

    // Tambah reservasi. Kirim: customer_name, plate, arrival_time. Box dipilih otomatis oleh sistem
    Route::post('/reservations', [ReservationController::class, 'store']);

    // Edit reservasi. Kirim hanya yang diubah. Kalau jadwal berubah, box dilepas dan dipilih ulang
    Route::put('/reservations/{reservation}', [ReservationController::class, 'update']);

    // Batalkan reservasi
    Route::delete('/reservations/{reservation}', [ReservationController::class, 'destroy']);
});