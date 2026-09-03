<?php

namespace Database\Seeders;

use App\Modules\MasterData\Models\Permission;
use App\Modules\MasterData\Models\Role;
use Illuminate\Database\Seeder;

/**
 * RBAC per-endpoint — Architecture Document Bagian 7.2. Permission bersifat
 * granular per modul, dipetakan ke role sesuai PRD Bagian 4 (Pengguna &
 * Peran) dan akses role pada Wireframe Document Bagian 2. Middleware
 * `permission:<nama>` (lihat App\Http\Middleware\EnsurePermission) membaca
 * daftar ini lewat User::hasPermission().
 */
class RolePermissionSeeder extends Seeder
{
    /**
     * Katalog permission. Kunci = nama permission, nilai = daftar role yang
     * memilikinya. admin_sistem selalu mendapat semua permission (super role
     * konfigurasi sistem — PRD Bagian 10).
     */
    private const CATALOG = [
        // Master Data (branches, fleets, drivers, mechanics, vendors,
        // warehouses, spareparts, cost-types, job-types)
        'master-data.view' => ['sa', 'fleet_operations', 'kepala_pool', 'tim_logistik', 'logistik_ho', 'admin_it_ga', 'manajemen'],
        'master-data.manage' => ['fleet_operations', 'tim_logistik'],

        // Cabang (branches) sengaja DIPISAH dari master-data.manage — ini
        // struktur organisasi (7 cabang PT Pro Energi), bukan data
        // operasional harian seperti driver/vendor/sparepart, jadi hanya
        // Admin Sistem yang boleh CRUD. Role lain (SA, Kepala Pool, Fleet
        // Operations, Tim Logistik, dst) tetap bisa lihat lewat
        // master-data.view yang sudah ada — cuma tidak bisa ubah/hapus.
        'branch.manage' => [],

        // Pengajuan & Work Order — SA membuat pengajuan SEKALIGUS Work Order
        // dalam satu langkah (diagnosis, prioritas, estimasi biaya, pelaksana
        // internal/eksternal, No. TAR langsung terisi di awal untuk
        // perbaikan), DAN setelah disetujui SA sendiri yang menjalankan Work
        // Order (start s/d selesai, termasuk realisasi sparepart — lihat
        // WorkOrderController::realizeItems()) karena mekanik dihapus. Tim
        // Logistik TIDAK terlibat di eksekusi Work Order.
        'request.create' => ['sa'],
        'request.view' => ['sa', 'fleet_operations', 'kepala_pool', 'tim_logistik', 'logistik_ho', 'manajemen'],
        'work-order.manage' => ['sa'],
        'work-order.update-status' => ['sa'],

        // Approval — dua tahap SERAGAM per cabang & DINAMIS (data-driven
        // lewat tabel approval_steps, bukan hardcode — lihat
        // ApprovalWorkflowService): Fleet Operations cabang verifikasi dulu
        // (bisa reject; bisa mengedit pengajuan selama gilirannya — lihat
        // request.edit di bawah), baru Kepala Pool cabang approval akhir.
        // Setiap cabang sekarang punya keduanya sendiri (region &
        // leader_operations dihapus total). Tim Logistik TIDAK punya
        // wewenang approval.
        'approval.view' => ['fleet_operations', 'kepala_pool'],
        'approval.act' => ['fleet_operations', 'kepala_pool'],

        // Fleet Operations mengedit pengajuan SA selama giliran tahap
        // approval-nya — lihat RequestController::update().
        'request.edit' => ['fleet_operations'],

        // Riwayat & Laporan Armada — SA butuh fleet.view supaya bisa membuka
        // Detail Armada (riwayat servis/legalitas) dari armada yang sedang
        // dikerjakannya, meski cuma cabangnya sendiri & read-only (fleet.manage
        // tetap khusus Tim Logistik). Fleet Operations juga melihat seksi
        // dashboard status armada/reliability/profitabilitas yang sama
        // dengan Tim Logistik (fleet.view/report.view menentukan seksi mana
        // yang tampil di Dashboard, lihat src/pages/index.vue), tanpa ikut
        // mengelola data armada.
        // Logistik HO (role global, lihat User::GLOBAL_ROLES) dapat
        // fleet.view/report.view/request.view/master-data.view TANPA
        // pembatasan cabang — bisa melihat proses semua cabang sekaligus,
        // murni pemantauan (tidak ada permission manage/approval sama sekali).
        'fleet.view' => ['sa', 'fleet_operations', 'kepala_pool', 'tim_logistik', 'logistik_ho', 'manajemen'],
        'fleet.manage' => ['tim_logistik'],
        'report.view' => ['fleet_operations', 'tim_logistik', 'logistik_ho', 'manajemen'],

        // Asset Registry
        'asset.view' => ['admin_it_ga', 'manajemen'],
        'asset.manage' => ['admin_it_ga'],

        // Manajemen tahap approval (Approval Workflow Engine dinamis) —
        // dibatasi ke Admin Sistem saja, sama seperti rbac.manage/
        // user.manage/audit-log.view/system-log.view di bawah.
        'approval-step.manage' => [],

        // Audit Trail (NFR-05 Auditability) — bersifat sensitif, dibatasi
        // ke Admin Sistem saja (sama seperti rbac.manage/user.manage/
        // system-log.view di bawah).
        'audit-log.view' => [],

        // Manajemen Role & Permission (RBAC) — PRD Bagian 4: Admin Sistem
        // "Mengatur ... RBAC (role/permission)". Dibatasi ke Admin Sistem
        // saja, sama seperti audit-log.view.
        'rbac.manage' => [],

        // Manajemen Pengguna (buat akun, tetapkan role/cabang) — PRD
        // Bagian 4: Admin Sistem. Dipisah dari rbac.manage karena secara
        // konsep beda (akun vs. definisi role/permission), meski sama-sama
        // hanya untuk Admin Sistem.
        'user.manage' => [],

        // Log aplikasi (Laravel log) — supaya Admin Sistem tahu kalau ada
        // error di server tanpa akses SSH. Dibatasi ke Admin Sistem saja,
        // sama seperti audit-log.view/rbac.manage/user.manage.
        'system-log.view' => [],
    ];

    public function run(): void
    {
        // Role::all()->keyBy('name') TIDAK bisa dipakai dengan ->only() di sini:
        // Illuminate\Database\Eloquent\Collection meng-override only() untuk
        // memfilter berdasarkan primary key model (id), bukan key array hasil
        // keyBy(). pluck('id', 'name') menghasilkan base Support\Collection
        // biasa sehingga ->only($roleNames) bekerja sesuai key nama role.
        $roleIdsByName = Role::pluck('id', 'name');
        $adminSistemId = $roleIdsByName->get('admin_sistem');

        foreach (self::CATALOG as $permissionName => $roleNames) {
            $permission = Permission::firstOrCreate(['name' => $permissionName]);

            $roleIds = $roleIdsByName->only($roleNames)->values();
            if ($adminSistemId) {
                $roleIds->push($adminSistemId);
            }

            $permission->roles()->syncWithoutDetaching($roleIds->unique());
        }
    }
}
