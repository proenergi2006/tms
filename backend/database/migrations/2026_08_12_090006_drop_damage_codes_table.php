<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Katalog Kode Kerusakan dihapus total — lihat migrasi
     * 2026_08_12_090005_drop_damage_code_id_from_requests_table (harus
     * jalan lebih dulu, sudah dijamin oleh urutan timestamp file).
     */
    public function up(): void
    {
        Schema::dropIfExists('damage_codes');
    }

    public function down(): void
    {
        Schema::create('damage_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('category', 50);
            $table->string('name', 150);
            $table->timestamps();
        });
    }
};
