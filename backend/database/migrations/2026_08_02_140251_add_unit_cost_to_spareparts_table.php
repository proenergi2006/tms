<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Harga satuan referensi sparepart — supaya saat dipakai di item Work
     * Order (WorkOrderController::storeItem()), harganya bisa auto-terisi
     * dari sini (bukan diketik ulang manual tiap kali) sekaligus jadi
     * acuan konsisten lintas Work Order untuk part yang sama.
     */
    public function up(): void
    {
        Schema::table('spareparts', function (Blueprint $table) {
            $table->decimal('unit_cost', 15, 2)->default(0)->after('unit');
        });
    }

    public function down(): void
    {
        Schema::table('spareparts', function (Blueprint $table) {
            $table->dropColumn('unit_cost');
        });
    }
};
