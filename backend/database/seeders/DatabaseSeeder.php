<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Approval\Models\ApprovalStep;
use App\Modules\Fleet\Models\Fleet;
use App\Modules\Fleet\Models\FleetLegalDoc;
use App\Modules\MasterData\Models\Branch;
use App\Modules\MasterData\Models\CostType;
use App\Modules\MasterData\Models\JobType;
use App\Modules\MasterData\Models\Mechanic;
use App\Modules\MasterData\Models\Role;
use App\Modules\MasterData\Models\Sparepart;
use App\Modules\MasterData\Models\Vendor;
use App\Modules\MasterData\Models\Warehouse;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Password default seluruh user seed — HANYA untuk data
     * pengembangan/demo lokal, bukan kredensial produksi. Di-hash otomatis
     * lewat cast 'password' => 'hashed' pada model User.
     */
    private const SEED_PASSWORD = 'password123';

    /**
     * Data awal untuk pengembangan lokal — bukan data produksi. Mencakup satu
     * user per role per cabang (login sementara via DevAuthController) dan
     * sedikit master data agar UI tidak kosong saat dites di browser.
     */
    public function run(): void
    {
        $roleNames = ['sa', 'fleet_operations', 'kepala_pool', 'tim_logistik', 'logistik_ho', 'admin_it_ga', 'admin_sistem', 'manajemen'];
        $roles = collect($roleNames)->mapWithKeys(fn ($name) => [$name => Role::firstOrCreate(['name' => $name])]);

        $this->call(RolePermissionSeeder::class);

        // Approval Workflow Engine dinamis (lihat ApprovalStep &
        // ApprovalWorkflowService) — dua tahap seragam untuk SEMUA cabang:
        // Fleet Operations verifikasi dulu, baru Kepala Pool approval akhir.
        ApprovalStep::firstOrCreate(
            ['role_name' => 'fleet_operations', 'sequence_order' => 1],
            ['label' => 'Verifikasi Fleet Operations', 'is_active' => true]
        );
        ApprovalStep::firstOrCreate(
            ['role_name' => 'kepala_pool', 'sequence_order' => 2],
            ['label' => 'Approval Akhir Kepala Pool', 'is_active' => true]
        );

        // 7 cabang operasional riil PT Pro Energi. Setiap cabang sekarang
        // punya SA, Fleet Operations, DAN Kepala Pool sendiri (region
        // dihapus total — lihat migrasi drop_region_from_branches_table) —
        // Admin IT & GA, Admin Sistem, dan Manajemen tetap beroperasi
        // lintas-cabang dari Head Office.
        $branchDefs = [
            'JKT' => 'Jakarta',
            'SBY' => 'Surabaya',
            'PLG' => 'Palembang',
            'BJM' => 'Banjarmasin',
            'PTK' => 'Pontianak',
            'SMD' => 'Samarinda',
            'SUL' => 'Sulawesi',
        ];
        $branches = collect($branchDefs)->mapWithKeys(
            fn ($name, $code) => [$code => Branch::firstOrCreate(['code' => $code], ['name' => $name])]
        );
        $branchJkt = $branches['JKT'];
        $branchSmd = $branches['SMD'];

        // SA (Service Advisor) — satu per cabang, membuat pengajuan SEKALIGUS
        // Work Order dalam satu langkah (diagnosis, prioritas, estimasi
        // biaya, pelaksana internal/eksternal, No. TAR terisi di awal untuk
        // perbaikan).
        foreach ($branches as $code => $branch) {
            $slug = strtolower($code);
            User::firstOrCreate(
                ['email' => "sa.{$slug}@tms.test"],
                [
                    'name' => "SA ({$branch->name})",
                    'role_id' => $roles['sa']->id,
                    'branch_id' => $branch->id,
                    'sso_id' => "seed-sa-{$slug}",
                    'password' => self::SEED_PASSWORD,
                ]
            );
        }

        // Tim Logistik — satu per cabang (unchanged), tidak terlibat approval
        // maupun eksekusi Work Order (SA yang menjalankan WO start s/d
        // selesai).
        foreach ($branches as $code => $branch) {
            $slug = strtolower($code);
            User::firstOrCreate(
                ['email' => "tim_logistik.{$slug}@tms.test"],
                [
                    'name' => "Tim Logistik ({$branch->name})",
                    'role_id' => $roles['tim_logistik']->id,
                    'branch_id' => $branch->id,
                    'sso_id' => "seed-tim_logistik-{$slug}",
                    'password' => self::SEED_PASSWORD,
                ]
            );
        }

        // Kepala Pool — SEKARANG SATU PER CABANG (seluruh 7 cabang, bukan
        // hanya 4 seperti sebelumnya) — setiap cabang dijamin punya Kepala
        // Pool sendiri, jadi rantai approval seragam tanpa fallback wilayah
        // apa pun lagi (lihat ApprovalWorkflowService).
        foreach ($branches as $code => $branch) {
            $slug = strtolower($code);
            User::firstOrCreate(
                ['email' => "kepala_pool.{$slug}@tms.test"],
                [
                    'name' => "Kepala Pool ({$branch->name})",
                    'role_id' => $roles['kepala_pool']->id,
                    'branch_id' => $branch->id,
                    'sso_id' => "seed-kepala_pool-{$slug}",
                    'password' => self::SEED_PASSWORD,
                ]
            );
        }

        // Fleet Operations — SEKARANG SATU PER CABANG (bukan per wilayah
        // lagi — lihat User::GLOBAL_ROLES, fleet_operations branch-scoped
        // persis seperti sa/kepala_pool/tim_logistik). Tahap approval
        // pertama (verifikasi, bisa reject/edit pengajuan) SEKALIGUS
        // penanggung jawab verifikasi laporan kegiatan mekanik setelah WO
        // selesai (fleet-ops.verify).
        foreach ($branches as $code => $branch) {
            $slug = strtolower($code);
            User::firstOrCreate(
                ['email' => "fleet_operations.{$slug}@tms.test"],
                [
                    'name' => "Fleet Operations ({$branch->name})",
                    'role_id' => $roles['fleet_operations']->id,
                    'branch_id' => $branch->id,
                    'sso_id' => "seed-fleet_operations-{$slug}",
                    'password' => self::SEED_PASSWORD,
                ]
            );
        }

        // Role global (Head Office) — satu user, tidak dibatasi cabang
        // (User::isBranchScoped() otomatis false). branch_id diisi Jakarta
        // hanya sebagai info lokasi kantor pusat, bukan pembatas akses.
        foreach (['admin_it_ga', 'admin_sistem', 'manajemen'] as $roleName) {
            User::firstOrCreate(
                ['email' => "{$roleName}@tms.test"],
                [
                    'name' => ucwords(str_replace('_', ' ', $roleName)),
                    'role_id' => $roles[$roleName]->id,
                    'branch_id' => $branchJkt->id,
                    'sso_id' => "seed-{$roleName}",
                    'password' => self::SEED_PASSWORD,
                ]
            );
        }

        // Logistik HO — role global juga (lihat User::GLOBAL_ROLES), khusus
        // pemantauan lintas cabang: hanya permission *.view (master data,
        // pengajuan, armada, laporan), TANPA manage/approval sama sekali.
        User::firstOrCreate(
            ['email' => 'logistik_ho@tms.test'],
            [
                'name' => 'Logistik HO',
                'role_id' => $roles['logistik_ho']->id,
                'branch_id' => $branchJkt->id,
                'sso_id' => 'seed-logistik_ho',
                'password' => self::SEED_PASSWORD,
            ]
        );

        $fleet1 = Fleet::firstOrCreate(
            ['plate_number' => 'B 1234 XYZ'],
            ['fleet_type' => 'Tronton', 'brand' => 'Hino', 'model' => 'FG', 'year' => 2020, 'capacity' => 20, 'branch_id' => $branchJkt->id, 'status' => 'aktif']
        );
        $fleet2 = Fleet::firstOrCreate(
            ['plate_number' => 'B 5678 ABC'],
            ['fleet_type' => 'Tangki', 'brand' => 'Mitsubishi', 'model' => 'Fuso', 'year' => 2019, 'capacity' => 16, 'branch_id' => $branchJkt->id, 'status' => 'aktif']
        );
        $fleet3 = Fleet::firstOrCreate(
            ['plate_number' => 'KT 1122 DA'],
            ['fleet_type' => 'Tronton', 'brand' => 'Hino', 'model' => 'FM', 'year' => 2021, 'capacity' => 20, 'branch_id' => $branchSmd->id, 'status' => 'aktif']
        );

        FleetLegalDoc::firstOrCreate(
            ['fleet_id' => $fleet1->id, 'doc_type' => 'STNK'],
            ['expiry_date' => now()->addDays(15)]
        );
        FleetLegalDoc::firstOrCreate(
            ['fleet_id' => $fleet1->id, 'doc_type' => 'KIR'],
            ['expiry_date' => now()->addMonths(6)]
        );
        FleetLegalDoc::firstOrCreate(
            ['fleet_id' => $fleet2->id, 'doc_type' => 'ASURANSI'],
            ['expiry_date' => now()->addDays(45)]
        );
        FleetLegalDoc::firstOrCreate(
            ['fleet_id' => $fleet3->id, 'doc_type' => 'STNK'],
            ['expiry_date' => now()->addMonths(4)]
        );

        Mechanic::firstOrCreate(['name' => 'Budi Santoso'], ['branch_id' => $branchJkt->id]);
        Mechanic::firstOrCreate(['name' => 'Agus Wijaya'], ['branch_id' => $branchJkt->id]);
        Mechanic::firstOrCreate(['name' => 'Herman Yusuf'], ['branch_id' => $branchSmd->id]);

        // Vendor/bengkel, sparepart, jenis biaya, dan jenis pekerjaan tidak
        // punya branch_id (dianggap referensi bersama lintas cabang).
        Vendor::firstOrCreate(['name' => 'Bengkel Jaya Motor'], ['type' => 'bengkel']);

        $warehouseJkt = Warehouse::firstOrCreate(['name' => 'Gudang Pusat Jakarta'], ['branch_id' => $branchJkt->id]);
        Warehouse::firstOrCreate(['name' => 'Gudang Samarinda'], ['branch_id' => $branchSmd->id]);

        Sparepart::firstOrCreate(
            ['sku' => 'BAN-001'],
            ['name' => 'Ban Tronton 1000-20', 'category' => 'ban', 'unit_cost' => 850000, 'warehouse_id' => $warehouseJkt->id, 'stock_qty' => 8, 'min_stock' => 4]
        );
        Sparepart::firstOrCreate(
            ['sku' => 'OLI-001'],
            ['name' => 'Oli Mesin 15W-40', 'category' => 'oli_pelumas', 'unit_cost' => 85000, 'warehouse_id' => $warehouseJkt->id, 'stock_qty' => 2, 'min_stock' => 10]
        );

        CostType::firstOrCreate(['name' => 'Sparepart'], ['category' => 'operasional']);
        CostType::firstOrCreate(['name' => 'Jasa Bengkel'], ['category' => 'operasional']);
        CostType::firstOrCreate(['name' => 'GPS'], ['category' => 'operasional']);
        CostType::firstOrCreate(['name' => 'Asuransi'], ['category' => 'administrasi']);
        CostType::firstOrCreate(['name' => 'Cicilan'], ['category' => 'administrasi']);

        // Katalog Jenis Pekerjaan resmi dari referensi bengkel PT Pro Energi
        // (kategori komponen + deskripsi pekerjaan) — dipakai saat SA mencatat
        // rincian biaya pengajuan/realisasi WO.
        $jobTypes = [
            'AC' => ['Service AC', 'Perbaikan AC'],
            'Brake Systems' => [
                'Perbaikan Brake/Rem', 'Jasa Penggantian Brake/Rem', 'Jasa pembersihan Brake/Rem',
                'Ganti oli Brake/Rem', 'Ganti kampas Brake/Rem', 'Seal Brake/Rem', 'Paku Brake/Rem',
                'Karet Abu kaliper', 'Per Brake/Rem', 'Brake Linning Brake/Rem',
            ],
            'Chassis' => ['Repainting', 'Cat Body', 'Jasa las', 'Las Knalpot', 'Repair branding', 'sticker unit'],
            'Electrical' => [
                'Lampu rotary', 'Accu', 'Air aki', 'air zuur', 'lampu tanduk', 'lampu utama', 'lampu sein',
            ],
            'Legalitas' => ['Stnk', 'BPKB', 'B3', 'Kir', 'Tera', 'tera flowmeter unit'],
            'Service' => ['Perbaikan unit'],
            'Spare Parts' => ['Pembelian kampas kopling', 'pembelian spring', 'pembelian oli', 'pembelian Part unit'],
            'Tire' => ['Pembelian ban luar/bandalam', 'Pembelian velg', 'Penambalan ban', 'Spooring', 'balancing'],
            'Wash' => ['pembelian alat cuci', 'pembelian sabun dan lain-lain', 'Service alat steam'],
        ];
        foreach ($jobTypes as $category => $names) {
            foreach ($names as $name) {
                JobType::firstOrCreate(['name' => $name], ['category' => $category]);
            }
        }
    }
}
