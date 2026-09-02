<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Kode Kerusakan" dihapus dari alur bisnis — SA sekarang menulis
     * diagnosis bebas teks (requests.diagnosis) saat pengajuan dibuat,
     * menggantikan kebutuhan katalog damage_codes. Harus dijalankan
     * SEBELUM migrasi drop tabel damage_codes (FK di sini merujuk ke sana).
     */
    public function up(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('damage_code_id');
        });
    }

    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->foreignId('damage_code_id')->nullable()->after('fleet_id')
                ->constrained('damage_codes')->nullOnDelete();
        });
    }
};
