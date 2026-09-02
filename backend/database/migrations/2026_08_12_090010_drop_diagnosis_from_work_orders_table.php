<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Diagnosis pindah ke requests.diagnosis (diisi SA saat pengajuan
     * dibuat, bukan lagi dicatat belakangan oleh mekanik saat eksekusi WO —
     * peran mekanik dihapus total) — lihat migrasi
     * 2026_08_12_090003_add_sa_fields_to_requests_table.
     */
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('diagnosed_by');
            $table->dropColumn(['diagnosis', 'diagnosed_at']);
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->text('diagnosis')->nullable()->after('vendor_id');
            $table->dateTime('diagnosed_at')->nullable()->after('diagnosis');
            $table->foreignId('diagnosed_by')->nullable()->after('diagnosed_at')
                ->constrained('users')->nullOnDelete();
        });
    }
};
