<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Sama seperti migrasi region pada branches (lihat
     * 2026_08_12_090001_change_region_enum_on_branches_table) — kolom ini
     * sekarang dipakai bersama oleh fleet_operations DAN leader_operations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN region ENUM('wilayah_1', 'wilayah_2', 'barat', 'timur') NULL COMMENT 'Wilayah cakupan untuk role fleet_operations & leader_operations'");
        DB::table('users')->whereIn('region', ['wilayah_1', 'wilayah_2'])->update(['region' => null]);
        DB::statement("ALTER TABLE users MODIFY COLUMN region ENUM('barat', 'timur') NULL COMMENT 'Wilayah cakupan untuk role fleet_operations & leader_operations — lihat branches.region.'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN region ENUM('wilayah_1', 'wilayah_2', 'barat', 'timur') NULL");
        DB::table('users')->whereIn('region', ['barat', 'timur'])->update(['region' => null]);
        DB::statement("ALTER TABLE users MODIFY COLUMN region ENUM('wilayah_1', 'wilayah_2') NULL COMMENT 'Wilayah cakupan untuk role fleet_operations — lihat branches.region.'");
    }
};
