<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Sinkronisasi armada & driver dari SYOP native untuk seluruh cabang — tidak
// lagi murni manual (lihat SyncSyopMasterData). Dijalankan tiap jam supaya
// data yang dilihat SA saat membuat pengajuan tidak pernah lebih dari
// beberapa saat basi dibanding SYOP, tanpa membebani DB SYOP terlalu sering.
Schedule::command('syop:sync-master-data')->hourly();

// FR-17 — notifikasi legalitas armada mendekati jatuh tempo (Architecture
// Document Bagian 4.5). Dijalankan pagi hari agar Tim Logistik punya waktu
// menindaklanjuti sebelum jam operasional.
Schedule::command('notifications:check-legal-expiry')->dailyAt('07:00');

// Inspeksi rutin armada wajib tiap 2 minggu (baik rusak atau tidak) — lihat
// CheckFleetInspectionDue.
Schedule::command('fleets:check-inspection-due')->dailyAt('07:15');

// Servis berkala berdasar km/jam operasi/waktu — lihat CheckFleetServiceDue.
Schedule::command('fleets:check-service-due')->dailyAt('07:20');

// Stok sparepart di bawah ambang minimum — lihat CheckSparepartLowStock.
Schedule::command('spareparts:check-low-stock')->dailyAt('07:25');

// Manajemen ban & komponen major (aki, oli, rem) — lihat CheckFleetComponentDue.
Schedule::command('fleets:check-component-due')->dailyAt('07:30');
