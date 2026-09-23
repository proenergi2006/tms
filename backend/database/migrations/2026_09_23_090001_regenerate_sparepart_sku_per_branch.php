<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Backfill satu kali: format SKU lama (SP-00001, urut global lintas
     * cabang) diganti ke format baru SP-{KODE_CABANG}-00001 (urut reset per
     * cabang), mengikuti keputusan produk saat penomoran SKU ditinjau ulang
     * (lihat SparepartController::generateSku()). Termasuk baris yang sudah
     * soft-deleted (query builder mentah di sini tidak menerapkan
     * SoftDeletingScope, otomatis ikut ke-update) — konsisten dengan cara
     * generateSku() menghitung "nomor berikutnya" lewat withTrashed().
     *
     * Diurutkan per id (bukan nama/tanggal) semata supaya sparepart yang
     * lebih dulu dibuat tetap dapat nomor lebih kecil dalam cabangnya —
     * murni kosmetik, tidak ada makna bisnis pada urutannya.
     */
    public function up(): void
    {
        $rows = DB::table('spareparts')
            ->join('warehouses', 'warehouses.id', '=', 'spareparts.warehouse_id')
            ->join('branches', 'branches.id', '=', 'warehouses.branch_id')
            ->orderBy('spareparts.id')
            ->select('spareparts.id', 'branches.code as branch_code')
            ->get();

        $counters = [];

        foreach ($rows as $row) {
            $counters[$row->branch_code] = ($counters[$row->branch_code] ?? 0) + 1;
            $sku = 'SP-'.$row->branch_code.'-'.str_pad((string) $counters[$row->branch_code], 5, '0', STR_PAD_LEFT);

            DB::table('spareparts')->where('id', $row->id)->update(['sku' => $sku]);
        }
    }

    /**
     * Tidak reversible dengan aman — format lama (nomor urut global) tidak
     * bisa direkonstruksi dari data baru tanpa menyimpan mapping SKU
     * lama↔baru terpisah, dan tidak ada kebutuhan bisnis untuk rollback ini.
     */
    public function down(): void
    {
        // Sengaja kosong — lihat catatan di atas.
    }
};
