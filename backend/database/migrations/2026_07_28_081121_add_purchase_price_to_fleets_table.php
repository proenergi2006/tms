<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('fleets', function (Blueprint $table) {
            $table->decimal('purchase_price', 15, 2)->nullable()->after('capacity')
                ->comment('Nilai perolehan/beli armada — dasar perbandingan biaya perbaikan vs nilai unit untuk keputusan ganti/pertahankan.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fleets', function (Blueprint $table) {
            $table->dropColumn('purchase_price');
        });
    }
};
