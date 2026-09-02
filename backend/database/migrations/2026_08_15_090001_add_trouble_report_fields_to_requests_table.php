<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Field laporan temuan tambahan saat pengajuan (khusus type=perbaikan) —
 * dilaporkan manual oleh SA berdasarkan pengecekan fisik di lapangan,
 * TERPISAH dari Fleet::currentOdometer() (dihitung otomatis dari fuel log,
 * bisa jadi belum mencerminkan angka terkini saat armada dilaporkan rusak).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->unsignedInteger('odometer_km')->nullable()->after('estimated_days')
                ->comment('Kilometer odometer armada saat dilaporkan, diisi manual SA.');
            $table->date('trouble_date')->nullable()->after('odometer_km')
                ->comment('Tanggal kerusakan/temuan terjadi (bisa beda dari tanggal pengajuan dibuat).');
            $table->text('suggestion')->nullable()->after('trouble_date')
                ->comment('Saran/rekomendasi SA atas temuan.');
            $table->text('action_taken')->nullable()->after('suggestion')
                ->comment('Tindakan yang diambil/direncanakan SA.');
        });
    }

    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->dropColumn(['odometer_km', 'trouble_date', 'suggestion', 'action_taken']);
        });
    }
};
