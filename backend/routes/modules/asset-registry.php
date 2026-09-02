<?php

// Endpoint Asset Registry & Notifikasi — Design Document Bagian 3.6.
// RBAC — lihat RolePermissionSeeder untuk pemetaan permission ke role.
// Notifikasi tidak digembok permission tambahan: hasilnya sudah dibatasi
// per user_id pengguna yang login (lihat NotificationController).

use App\Http\Controllers\NotificationController;
use App\Modules\AssetRegistry\Http\Controllers\AssetController;
use Illuminate\Support\Facades\Route;

Route::apiResource('assets', AssetController::class)->only(['index', 'show'])->middleware('permission:asset.view');
Route::apiResource('assets', AssetController::class)->only(['store', 'update', 'destroy'])->middleware('permission:asset.manage');

Route::get('notifications', [NotificationController::class, 'index']);
Route::patch('notifications/read-all', [NotificationController::class, 'markAllRead']);
Route::patch('notifications/{notification}/read', [NotificationController::class, 'markRead']);
