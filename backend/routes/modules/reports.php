<?php

// Laporan lintas-modul yang tidak masuk akal ditaruh di routes/modules
// spesifik satu domain (fleet.php/master-data.php) — sumber datanya lintas
// modul (notifikasi + master data driver, atau work-order-item + sparepart).

use App\Http\Controllers\DriverIncidentReportController;
use App\Modules\MasterData\Http\Controllers\SparepartSpendReportController;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:report.view')->group(function () {
    Route::get('reports/driver-incidents', [DriverIncidentReportController::class, 'index']);
    Route::get('reports/sparepart-spend', [SparepartSpendReportController::class, 'index']);
});
