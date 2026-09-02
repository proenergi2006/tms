<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DevAuthController;
use App\Http\Controllers\SsoController;
use App\Http\Controllers\SystemLogController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Base URL /api/v1 — lihat Design Document Bagian 3.1.
Route::prefix('v1')->group(function () {
    // Login kredensial TMS (email+password) — jalur utama, aktif di semua
    // environment. Lihat AuthController untuk alasan kenapa ini tetap ada
    // di samping SSO (tidak semua pengguna punya akun SYOP).
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:login');

    // SSO dari SYOP — lihat SsoController untuk detail verifikasi AES-256-GCM.
    Route::post('/auth/sso', [SsoController::class, 'login'])->middleware('throttle:sso');

    // Login sementara sebelum SSO tersedia — hanya aktif di local/testing,
    // lihat DevAuthController.
    Route::get('/auth/dev-users', [DevAuthController::class, 'users']);
    Route::post('/auth/dev-login', [DevAuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/user', function (Request $request) {
            return $request->user();
        });

        Route::post('/auth/logout', [AuthController::class, 'logout']);

        Route::get('/audit-logs', [AuditLogController::class, 'index'])->middleware('permission:audit-log.view');

        Route::get('/system-logs', [SystemLogController::class, 'index'])->middleware('permission:system-log.view');
        Route::get('/system-logs-summary', [SystemLogController::class, 'summary'])->middleware('permission:system-log.view');

        require __DIR__.'/modules/master-data.php';
        require __DIR__.'/modules/maintenance.php';
        require __DIR__.'/modules/approval.php';
        require __DIR__.'/modules/fleet.php';
        require __DIR__.'/modules/asset-registry.php';
        require __DIR__.'/modules/rbac.php';
        require __DIR__.'/modules/users.php';
    });
});
