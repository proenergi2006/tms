<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Reorganisasi wilayah: wilayah_1/wilayah_2 (dipakai fleet_operations
     * saja) diganti barat/timur, sekarang dipakai BERSAMA oleh
     * fleet_operations DAN leader_operations (approval untuk cabang tanpa
     * Kepala Pool — lihat ApprovalWorkflowService::currentStage()).
     * Pengelompokan cabang juga berubah (bukan cuma rename): Banjarmasin &
     * Pontianak pindah dari wilayah_1 lama ke kelompok timur yang baru
     * (lihat DatabaseSeeder untuk pemetaan final per cabang).
     *
     * Widen dulu (union nilai lama+baru) supaya baris lama tidak
     * menghasilkan data truncation, null-kan nilai lama, baru persempit ke
     * enum final — DB::statement dipakai karena ALTER ... MODIFY COLUMN
     * enum tidak didukung langsung oleh Schema Builder Laravel untuk MySQL.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE branches MODIFY COLUMN region ENUM('wilayah_1', 'wilayah_2', 'barat', 'timur') NULL COMMENT 'Kelompok wilayah, dipakai fleet_operations & leader_operations'");
        DB::table('branches')->whereIn('region', ['wilayah_1', 'wilayah_2'])->update(['region' => null]);
        DB::statement("ALTER TABLE branches MODIFY COLUMN region ENUM('barat', 'timur') NULL COMMENT 'Kelompok wilayah: barat = Palembang/Jakarta/Surabaya, timur = Banjarmasin/Pontianak/Samarinda/Sulawesi.'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE branches MODIFY COLUMN region ENUM('wilayah_1', 'wilayah_2', 'barat', 'timur') NULL");
        DB::table('branches')->whereIn('region', ['barat', 'timur'])->update(['region' => null]);
        DB::statement("ALTER TABLE branches MODIFY COLUMN region ENUM('wilayah_1', 'wilayah_2') NULL COMMENT 'Kelompok wilayah untuk verifikasi Fleet Operations: wilayah_1 = SBY/JKT/PLG/BJM/PTK, wilayah_2 = SUL/SMD.'");
    }
};
