<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Prioritas & kode kerusakan (konsep notifikasi SAP PM) — priority
     * untuk urgensi pengajuan, damage_code_id khusus pengajuan perbaikan
     * (nullable di DB, tapi diwajibkan di validasi untuk type=perbaikan,
     * lihat StoreRequestRequest) supaya jenis pengajuan lain (restock,
     * pembelian, dst) tidak dipaksa punya kode kerusakan yang tidak relevan.
     */
    public function up(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->enum('priority', ['darurat', 'tinggi', 'sedang', 'rendah'])->default('sedang')->after('type');
            $table->foreignId('damage_code_id')->nullable()->after('fleet_id')
                ->constrained('damage_codes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('damage_code_id');
            $table->dropColumn('priority');
        });
    }
};
