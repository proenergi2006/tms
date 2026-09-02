<?php

// Endpoint Approval — Design Document Bagian 3.4.
// RBAC — lihat RolePermissionSeeder untuk pemetaan permission ke role.

use App\Modules\Approval\Http\Controllers\ApprovalController;
use App\Modules\Approval\Http\Controllers\ApprovalStepController;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:approval.view')->group(function () {
    Route::get('approvals/pending', [ApprovalController::class, 'pending']);
    Route::get('approvals/history', [ApprovalController::class, 'history']);
});

Route::middleware('permission:approval.act')->group(function () {
    Route::post('work-orders/{workOrder}/approve', [ApprovalController::class, 'approve']);
    Route::post('work-orders/{workOrder}/reject', [ApprovalController::class, 'reject']);
});

// Manajemen tahap approval (Approval Workflow Engine dinamis) — khusus
// admin_sistem, lihat RolePermissionSeeder & ApprovalStepController.
Route::middleware('permission:approval-step.manage')->group(function () {
    Route::get('approval-steps', [ApprovalStepController::class, 'index']);
    Route::post('approval-steps', [ApprovalStepController::class, 'store']);
    Route::put('approval-steps/{approvalStep}', [ApprovalStepController::class, 'update']);
    Route::patch('approval-steps/{approvalStep}', [ApprovalStepController::class, 'update']);
});
