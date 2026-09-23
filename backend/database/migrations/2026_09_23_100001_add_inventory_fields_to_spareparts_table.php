<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Field manajemen inventory tambahan diminta user: Brand, Part
     * Number/Merk, Kriteria (klasifikasi fast/slow moving), Lokasi (rak/
     * posisi fisik di gudang), Status (kesehatan stok). Semua nullable/
     * punya default supaya tidak mengganggu data sparepart yang sudah ada
     * (pola sama dengan penambahan field armada sebelumnya).
     *
     * `criteria`/`status` pakai SQL enum asli (bukan string bebas seperti
     * `category`/`unit` yang sudah ada) — mengikuti pola `vendors.status`/
     * `fleets.status`, karena nilainya memang set tertutup, bukan teks
     * bebas.
     */
    public function up(): void
    {
        Schema::table('spareparts', function (Blueprint $table) {
            $table->string('brand', 100)->nullable()->after('name');
            $table->string('part_number', 100)->nullable()->after('brand');
            $table->enum('criteria', ['very_fast', 'fast', 'medium', 'slow', 'very_slow', 'non'])
                ->nullable()->after('category');
            $table->string('location', 100)->nullable()->after('warehouse_id');
            $table->enum('status', ['aman', 'order', 'dead_stock', 'non_aktif'])
                ->default('aman')->after('min_stock');
        });
    }

    public function down(): void
    {
        Schema::table('spareparts', function (Blueprint $table) {
            $table->dropColumn(['brand', 'part_number', 'criteria', 'location', 'status']);
        });
    }
};
