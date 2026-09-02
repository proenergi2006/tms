<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * priority berubah dari 4 tingkat (darurat/tinggi/sedang/rendah) jadi
     * 3 tingkat (low/medium/high) — widen dulu supaya baris lama bisa
     * di-backfill tanpa truncation, lalu persempit ke enum final. Mapping:
     * darurat->high, tinggi->high, sedang->medium, rendah->low.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE requests MODIFY COLUMN priority ENUM('darurat', 'tinggi', 'sedang', 'rendah', 'low', 'medium', 'high') NOT NULL DEFAULT 'sedang'");

        DB::table('requests')->where('priority', 'darurat')->update(['priority' => 'high']);
        DB::table('requests')->where('priority', 'tinggi')->update(['priority' => 'high']);
        DB::table('requests')->where('priority', 'sedang')->update(['priority' => 'medium']);
        DB::table('requests')->where('priority', 'rendah')->update(['priority' => 'low']);

        DB::statement("ALTER TABLE requests MODIFY COLUMN priority ENUM('low', 'medium', 'high') NOT NULL DEFAULT 'medium'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE requests MODIFY COLUMN priority ENUM('darurat', 'tinggi', 'sedang', 'rendah', 'low', 'medium', 'high') NOT NULL DEFAULT 'medium'");

        DB::table('requests')->where('priority', 'high')->update(['priority' => 'tinggi']);
        DB::table('requests')->where('priority', 'medium')->update(['priority' => 'sedang']);
        DB::table('requests')->where('priority', 'low')->update(['priority' => 'rendah']);

        DB::statement("ALTER TABLE requests MODIFY COLUMN priority ENUM('darurat', 'tinggi', 'sedang', 'rendah') NOT NULL DEFAULT 'sedang'");
    }
};
