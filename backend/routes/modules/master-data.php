<?php

// Endpoint Master Data — Design Document Bagian 3.2.
// RBAC: baca (index/show) perlu master-data.view untuk SEMUA resource
// (termasuk branches/spareparts). Tulis (store/update/destroy) BEDA-BEDA
// per resource: master-data.manage untuk fleet/driver/mechanic/vendor/
// warehouse/cost-type/job-type, branch.manage khusus branches,
// sparepart.manage khusus spareparts — lihat RolePermissionSeeder.

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

// Sparepart juga dipisah dari loop di atas — baca tetap master-data.view,
// tapi tulis pakai sparepart.manage (bukan master-data.manage) supaya Admin
// Logistik bisa full CRUD sparepart tanpa otomatis dapat CRUD resource
// master data lain juga, lihat RolePermissionSeeder.
Route::apiResource('spareparts', SparepartController::class)->only(['index', 'show'])->middleware('permission:master-data.view');
Route::apiResource('spareparts', SparepartController::class)->only(['store', 'update', 'destroy'])->middleware('permission:sparepart.manage');

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
