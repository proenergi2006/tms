<?php

// Manajemen Pengguna — Design Document Bagian 3.2 (lanjutan Master Data).
// Seluruh endpoint di sini dibatasi permission `user.manage` (hanya Admin
// Sistem — lihat RolePermissionSeeder).

use App\Modules\MasterData\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:user.manage')->group(function () {
    Route::get('users', [UserController::class, 'index']);
    Route::post('users', [UserController::class, 'store']);
    Route::get('users/{user}', [UserController::class, 'show']);
    Route::put('users/{user}', [UserController::class, 'update']);
    Route::delete('users/{user}', [UserController::class, 'destroy']);
});
