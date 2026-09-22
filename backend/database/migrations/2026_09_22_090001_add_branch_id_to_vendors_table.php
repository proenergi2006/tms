<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nullable (beda dari branch_id di drivers/mechanics/warehouses yang
     * NOT NULL) karena tabel vendors sudah punya data production tanpa
     * cabang — memaksa NOT NULL di sini akan gagal migrate tanpa backfill
     * manual. Vendor lama (branch_id null) tetap dianggap "referensi
     * bersama semua cabang" (lihat User::canAccessBranch(), null selalu
     * diloloskan) sampai admin assign cabangnya secara manual lewat form
     * edit Master Data.
     */
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('id')->constrained('branches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_id');
        });
    }
};
