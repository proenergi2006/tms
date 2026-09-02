<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menandai kapan SA melakukan "realisasi" sparepart pada Work Order ini
     * (lihat WorkOrderController::realizeItems()) — stok gudang baru
     * berkurang di titik ini, BUKAN saat pengajuan dibuat. Null berarti
     * belum direalisasi; WorkOrderController::updateStatus() menolak
     * transisi ke status 'finished' selama kolom ini masih null, bahkan
     * untuk WO tanpa sparepart sama sekali (SA tetap wajib memanggil
     * realize-items dengan array items kosong untuk menegaskan "tidak ada
     * sparepart terpakai").
     */
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dateTime('items_realized_at')->nullable()->after('finished_at');
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropColumn('items_realized_at');
        });
    }
};
