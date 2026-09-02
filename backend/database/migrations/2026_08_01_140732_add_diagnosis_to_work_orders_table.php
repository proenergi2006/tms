<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Diagnosa/temuan mekanik saat inspeksi fisik — terpisah dari
     * requests.description (keluhan dari pengaju) karena mewakili hasil
     * pemeriksaan mekanik sendiri, bisa berbeda dari laporan awal (mis.
     * driver lapor "rem blong", mekanik menemukan kampas rem DAN selang
     * rem bocor). Nullable/opsional — WO lama tanpa diagnosa tetap valid.
     */
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->text('diagnosis')->nullable()->after('vendor_id');
            $table->dateTime('diagnosed_at')->nullable()->after('diagnosis');
            $table->foreignId('diagnosed_by')->nullable()->after('diagnosed_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('diagnosed_by');
            $table->dropColumn(['diagnosis', 'diagnosed_at']);
        });
    }
};
