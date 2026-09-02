<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sejak restrukturisasi peran, Service Advisor (SA) membuat pengajuan
     * SEKALIGUS Work Order dalam satu langkah — field yang dulu diisi
     * belakangan oleh mekanik saat eksekusi WO (diagnosis) atau tidak ada
     * sama sekali (No. TAR) sekarang diisi SA di sini, saat pengajuan
     * dibuat. Ketiganya nullable di DB, wajib di validasi hanya untuk
     * type=perbaikan (lihat StoreRequestRequest — mirror pola
     * requiredIf yang dulu dipakai untuk damage_code_id).
     */
    public function up(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->enum('maintenance_nature', ['preventive', 'corrective'])->nullable()->after('type');
            $table->string('tar_no', 50)->nullable()->after('description')
                ->comment('No. TAR (Technical Analysis Report), diisi SA untuk pengajuan perbaikan.');
            $table->text('diagnosis')->nullable()->after('tar_no')
                ->comment('Diagnosis SA saat pengajuan perbaikan dibuat (dipindah dari work_orders.diagnosis).');
        });
    }

    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->dropColumn(['maintenance_nature', 'tar_no', 'diagnosis']);
        });
    }
};
