<?php

// Endpoint Pengajuan & Work Order/SPK — Design Document Bagian 3.3.
// RBAC — lihat RolePermissionSeeder untuk pemetaan permission ke role.

use App\Modules\Maintenance\Http\Controllers\RequestController;
use App\Modules\Maintenance\Http\Controllers\WorkOrderController;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:request.view')->group(function () {
    Route::get('requests/mine', [RequestController::class, 'mine']);
    Route::get('requests', [RequestController::class, 'index']);
    Route::get('requests/{request}', [RequestController::class, 'show']);
    Route::get('work-orders', [WorkOrderController::class, 'index']);
    Route::get('work-orders/{workOrder}', [WorkOrderController::class, 'show']);
});

Route::middleware('permission:request.create')->group(function () {
    Route::post('requests', [RequestController::class, 'store']);
    // SA mengajukan ulang pengajuan MILIK SENDIRI yang ditolak — lihat
    // RequestController::resubmit() untuk otorisasi kepemilikan/status.
    Route::post('requests/{request}/resubmit', [RequestController::class, 'resubmit']);
});

// Fleet Operations mengedit pengajuan SA selama giliran tahap approval-nya —
// lihat RequestController::update() & catatan pada RolePermissionSeeder.
Route::middleware('permission:request.edit')->group(function () {
    Route::patch('requests/{request}', [RequestController::class, 'update']);
});

// Lampiran: SA (request.create) kapan saja untuk pengajuan cabangnya, Fleet
// Operations (request.edit) HANYA selama giliran tahap approval-nya — dua
// aturan berbeda, sengaja tidak digembok satu permission middleware saja,
// otorisasi granular dilakukan di RequestController::storeAttachment().
Route::middleware('permission:request.create,request.edit')->group(function () {
    Route::post('requests/{request}/attachments', [RequestController::class, 'storeAttachment']);
});

Route::middleware('permission:work-order.manage')->group(function () {
    Route::post('work-orders', [WorkOrderController::class, 'store']);
    Route::post('work-orders/{workOrder}/items', [WorkOrderController::class, 'storeItem']);
    Route::post('work-orders/{workOrder}/attachments', [WorkOrderController::class, 'storeAttachment']);
    Route::post('work-orders/{workOrder}/realize-items', [WorkOrderController::class, 'realizeItems']);
});

Route::middleware('permission:work-order.update-status')->group(function () {
    Route::patch('work-orders/{workOrder}/status', [WorkOrderController::class, 'updateStatus']);
});
