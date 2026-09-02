<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fleet_revenues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fleet_id')->constrained('fleets')->restrictOnDelete();
            $table->char('period', 7)->comment('Format YYYY-MM');
            $table->string('source_po_number', 50)->nullable()->comment('No. PO dari SYOP');
            $table->decimal('amount', 15, 2);
            $table->dateTime('synced_at')->comment('Waktu sinkronisasi terakhir dari syop_db');

            $table->unique(['fleet_id', 'period', 'source_po_number'], 'uq_fleet_period_po');
            $table->index('period', 'idx_fr_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_revenues');
    }
};
