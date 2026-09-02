<?php

// Manajemen Role & Permission (RBAC) — Design Document Bagian 3.2 (lanjutan
// Master Data). RBAC: seluruh endpoint di sini dibatasi permission
// `rbac.manage` (hanya Admin Sistem — lihat RolePermissionSeeder).

use App\Modules\MasterData\Http\Controllers\PermissionController;
use App\Modules\MasterData\Http\Controllers\RoleController;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:rbac.manage')->group(function () {
    Route::get('roles', [RoleController::class, 'index']);
    Route::post('roles', [RoleController::class, 'store']);
    Route::get('roles/{role}', [RoleController::class, 'show']);
    Route::put('roles/{role}', [RoleController::class, 'update']);
    Route::put('roles/{role}/permissions', [RoleController::class, 'syncPermissions']);
    Route::delete('roles/{role}', [RoleController::class, 'destroy']);

    Route::get('permissions', [PermissionController::class, 'index']);
});
