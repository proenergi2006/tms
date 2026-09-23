<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Field perencanaan stok tambahan (Opsi A dari analisis Lead Time s/d
     * Safety Stock — lihat diskusi sesi ini): hanya yang genuinely
     * kompatibel dengan model data sekarang (kolom manual di baris
     * sparepart). "Suggest Order Min" dari Excel sengaja TIDAK dibuatkan
     * kolom baru — itu sudah persis sama dengan `min_stock` yang sudah ada.
     * "Stock In/Out"/"First"/"Last" (butuh buku besar transaksi stok
     * berbasis periode, belum ada infrastrukturnya) juga sengaja tidak
     * diikutkan di sini — dicatat sebagai pengembangan terpisah, bukan
     * "tambah kolom" sederhana.
     */
    public function up(): void
    {
        Schema::table('spareparts', function (Blueprint $table) {
            $table->decimal('lead_time_months', 4, 1)->nullable()->after('criteria');
            $table->integer('max_stock')->nullable()->after('min_stock');
            $table->integer('safety_stock')->nullable()->after('max_stock');
        });
    }

    public function down(): void
    {
        Schema::table('spareparts', function (Blueprint $table) {
            $table->dropColumn(['lead_time_months', 'max_stock', 'safety_stock']);
        });
    }
};
