<?php

// Endpoint Riwayat Armada & Laporan — Design Document Bagian 3.5.
// RBAC — lihat RolePermissionSeeder untuk pemetaan permission ke role.

use App\Modules\Fleet\Http\Controllers\FleetController;
use App\Modules\Fleet\Http\Controllers\FleetHistoryController;
use App\Modules\Fleet\Http\Controllers\FleetProfitabilityReportController;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:fleet.view')->group(function () {
    Route::get('fleets-status-summary', [FleetController::class, 'statusSummary']);
    Route::get('fleets-legal-warnings', [FleetController::class, 'legalWarnings']);
    Route::get('fleets-reliability-summary', [FleetController::class, 'reliabilitySummary']);
    Route::get('fleets/{fleet}/maintenance-history', [FleetHistoryController::class, 'maintenanceHistory']);
    Route::get('fleets/{fleet}/legal-docs', [FleetHistoryController::class, 'legalDocsIndex']);
    Route::get('fleets/{fleet}/fuel-logs', [FleetHistoryController::class, 'fuelLogsIndex']);
    Route::get('fleets/{fleet}/operational-costs', [FleetHistoryController::class, 'operationalCosts']);
    Route::get('fleets/{fleet}/profitability', [FleetHistoryController::class, 'profitability']);
    Route::get('fleets/{fleet}/revenues', [FleetHistoryController::class, 'revenueIndex']);
    Route::get('fleets/{fleet}/cost-per-km', [FleetHistoryController::class, 'costPerKm']);
    Route::get('fleets/{fleet}/downtimes', [FleetHistoryController::class, 'downtimes']);
    Route::get('fleets/{fleet}/components', [FleetHistoryController::class, 'componentsIndex']);
});

Route::middleware('permission:fleet.manage')->group(function () {
    Route::post('fleets/{fleet}/legal-docs', [FleetHistoryController::class, 'legalDocsStore']);
    Route::put('fleets/{fleet}/legal-docs/{legalDoc}', [FleetHistoryController::class, 'legalDocsUpdate']);
    Route::post('fleets/{fleet}/fuel-logs', [FleetHistoryController::class, 'fuelLogsStore']);
    Route::post('fleets/{fleet}/revenues', [FleetHistoryController::class, 'revenueStore']);
    Route::put('fleets/{fleet}/components/{componentType}', [FleetHistoryController::class, 'componentsUpdate']);
    Route::post('fleets/{fleet}/components/{componentType}/mark-replaced', [FleetHistoryController::class, 'componentsMarkReplaced']);
});

Route::middleware('permission:report.view')->group(function () {
    Route::get('reports/fleet-profitability', [FleetProfitabilityReportController::class, 'index']);
    Route::get('reports/fleet-profitability/export', [FleetProfitabilityReportController::class, 'export']);
    Route::get('reports/fleet-maintenance-cost', [FleetProfitabilityReportController::class, 'maintenanceCost']);
});
