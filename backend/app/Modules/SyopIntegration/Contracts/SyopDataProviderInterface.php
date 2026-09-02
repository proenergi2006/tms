<?php

namespace App\Modules\SyopIntegration\Contracts;

use Illuminate\Support\Collection;

/**
 * Kontrak akses data SYOP — lihat Architecture Document Bagian 4.4 & 8.3 PRD.
 * Seluruh akses ke syop_db WAJIB melalui interface ini, tidak ada query
 * langsung ke koneksi 'syop' dari modul bisnis lain.
 *
 * Implementasi saat ini: SyopNativeAdapter (syop_db native, MySQL).
 * Implementasi masa depan: SyopV4Adapter (API SYOP v4) — lihat Bagian 6.3.
 */
interface SyopDataProviderInterface
{
    /**
     * Master armada dari SYOP, untuk one-time import saat go-live
     * (lihat Architecture Document Bagian 5.4).
     */
    public function getMasterArmada(): Collection;

    /**
     * Master driver dari SYOP, untuk one-time import saat go-live.
     */
    public function getMasterDriver(): Collection;

    /**
     * Driver yang layak disinkronkan ke TMS: hanya milik transportir Pro
     * Energi sendiri (PE-*) atau TDS (PT. Tri Daya Selaras), bukan vendor
     * transportir pihak ketiga lain, dan hanya yang aktif di SYOP. Setiap
     * baris sudah menyertakan `branch_code` (kode cabang TMS, bukan istilah
     * SYOP) hasil pemetaan nama transportir/lokasi — pemanggil tidak perlu
     * tahu detail penamaan transportir SYOP.
     */
    public function getEligibleDrivers(): Collection;

    /**
     * Armada yang layak disinkronkan ke TMS: hanya milik transportir Pro
     * Energi sendiri (PE-*) atau TDS (PT. Tri Daya Selaras), bukan vendor
     * transportir pihak ketiga lain, dan hanya yang aktif di SYOP. Sama
     * seperti getEligibleDrivers(), sumbernya pro_master_transportir_mobil
     * (bukan pro_master_mobil yang dipakai getMasterArmada() untuk one-time
     * import) — setiap baris sudah menyertakan `branch_code` hasil pemetaan.
     */
    public function getEligibleFleets(): Collection;

    /**
     * Realisasi PO dari SYOP, sumber data untuk cache fleet_revenue.
     */
    public function getRealisasiPO(array $filters = []): Collection;

    /**
     * Pendapatan armada dari SYOP (agregat realisasi PO).
     */
    public function getPendapatan(array $filters = []): Collection;

    /**
     * Data perjalanan/distribusi dari SYOP.
     */
    public function getDataPerjalanan(array $filters = []): Collection;
}
