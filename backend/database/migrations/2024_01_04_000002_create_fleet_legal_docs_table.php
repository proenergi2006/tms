<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fleet_legal_docs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fleet_id')->constrained('fleets')->cascadeOnDelete();
            $table->enum('doc_type', ['STNK', 'KIR', 'PAJAK', 'ASURANSI']);
            $table->string('doc_number', 50)->nullable();
            $table->date('issued_date')->nullable();
            $table->date('expiry_date');
            $table->string('file_path')->nullable();
            $table->timestamps();

            $table->index('expiry_date', 'idx_fld_expiry');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_legal_docs');
    }
};
