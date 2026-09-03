<?php

// Endpoint Master Data — Design Document Bagian 3.2.
// RBAC: baca (index/show) perlu master-data.view, tulis (store/update/
// destroy) perlu master-data.manage — lihat RolePermissionSeeder.

use App\Modules\Fleet\Http\Controllers\FleetController;
use App\Modules\MasterData\Http\Controllers\BranchController;
use App\Modules\MasterData\Http\Controllers\CostTypeController;
use App\Modules\MasterData\Http\Controllers\DriverController;
use App\Modules\MasterData\Http\Controllers\JobTypeController;
use App\Modules\MasterData\Http\Controllers\MechanicController;
use App\Modules\MasterData\Http\Controllers\SparepartController;
use App\Modules\MasterData\Http\Controllers\VendorController;
use App\Modules\MasterData\Http\Controllers\WarehouseController;
use Illuminate\Support\Facades\Route;

$resources = [
    'fleets' => FleetController::class,
    'drivers' => DriverController::class,
    'mechanics' => MechanicController::class,
    'vendors' => VendorController::class,
    'warehouses' => WarehouseController::class,
    'spareparts' => SparepartController::class,
];

foreach ($resources as $uri => $controller) {
    Route::apiResource($uri, $controller)->only(['index', 'show'])->middleware('permission:master-data.view');
    Route::apiResource($uri, $controller)->only(['store', 'update', 'destroy'])->middleware('permission:master-data.manage');
}

// Cabang dipisah dari loop di atas — baca tetap master-data.view (siapa pun
// yang biasa lihat master data lain), tapi tulis (create/edit/hapus) hanya
// branch.manage (Admin Sistem saja), lihat RolePermissionSeeder.
Route::apiResource('branches', BranchController::class)->only(['index', 'show'])->middleware('permission:master-data.view');
Route::apiResource('branches', BranchController::class)->only(['store', 'update', 'destroy'])->middleware('permission:branch.manage');

Route::post('drivers/sync-syop', [DriverController::class, 'syncFromSyop'])->middleware('permission:master-data.manage');
Route::post('fleets/sync-syop', [FleetController::class, 'syncFromSyop'])->middleware('permission:master-data.manage');

Route::apiResource('cost-types', CostTypeController::class)
    ->parameters(['cost-types' => 'costType'])
    ->only(['index', 'show'])
    ->middleware('permission:master-data.view');
Route::apiResource('cost-types', CostTypeController::class)
    ->parameters(['cost-types' => 'costType'])
    ->only(['store', 'update', 'destroy'])
    ->middleware('permission:master-data.manage');

Route::apiResource('job-types', JobTypeController::class)
    ->parameters(['job-types' => 'jobType'])
    ->only(['index', 'show'])
    ->middleware('permission:master-data.view');
Route::apiResource('job-types', JobTypeController::class)
    ->parameters(['job-types' => 'jobType'])
    ->only(['store', 'update', 'destroy'])
    ->middleware('permission:master-data.manage');
