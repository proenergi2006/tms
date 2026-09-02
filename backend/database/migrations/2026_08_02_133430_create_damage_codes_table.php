<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Katalog kode kerusakan (konsep kode kerusakan/damage code ala SAP PM)
     * — supaya pengajuan perbaikan bisa dianalisis per jenis kerusakan
     * (mis. "jenis kerusakan tersering"), bukan cuma teks bebas.
     */
    public function up(): void
    {
        Schema::create('damage_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('category', 50);
            $table->string('name', 150);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('damage_codes');
    }
};
