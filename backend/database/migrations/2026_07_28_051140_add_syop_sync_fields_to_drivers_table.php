<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mendukung sinkronisasi driver dari SYOP native (pro_master_transportir_sopir):
     * - syop_driver_id: dipakai untuk upsert idempoten (sync ulang tidak
     *   membuat duplikat), menyimpan id_master dari SYOP.
     * - license_number dilonggarkan jadi nullable: data SYOP tidak punya
     *   No. SIM sama sekali — dibiarkan kosong sampai diisi manual, bukan
     *   diisi nilai palsu.
     */
    public function up(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->unsignedInteger('syop_driver_id')->nullable()->unique()->after('id');
            $table->string('license_number', 30)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->dropColumn('syop_driver_id');
            $table->string('license_number', 30)->nullable(false)->change();
        });
    }
};
