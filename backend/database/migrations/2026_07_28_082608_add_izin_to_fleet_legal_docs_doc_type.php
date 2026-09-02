<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Tambah 'IZIN' (izin trayek/operasional) ke enum doc_type — permintaan
     * fitur eksplisit (reminder dokumen legalitas: STNK, KIR, asuransi,
     * izin). Enum MySQL diubah via raw SQL karena Schema::table() tidak
     * punya API bawaan untuk mengubah daftar nilai enum.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE fleet_legal_docs MODIFY doc_type ENUM('STNK', 'KIR', 'PAJAK', 'ASURANSI', 'IZIN') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE fleet_legal_docs MODIFY doc_type ENUM('STNK', 'KIR', 'PAJAK', 'ASURANSI') NOT NULL");
    }
};
