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
        Schema::create('fleet_downtimes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fleet_id')->constrained('fleets')->cascadeOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained('work_orders')->nullOnDelete()
                ->comment('WO perbaikan yang menyebabkan armada ini tidak beroperasi.');
            $table->dateTime('started_at')->comment('Saat pengajuan perbaikan disubmit — armada dianggap mulai tidak beroperasi.');
            $table->dateTime('ended_at')->nullable()->comment('Saat WO selesai+lolos approval+terverifikasi Fleet Ops, atau ditolak. NULL = masih downtime berjalan.');
            $table->timestamps();

            $table->index('fleet_id', 'idx_fleet_downtimes_fleet');
            $table->index('ended_at', 'idx_fleet_downtimes_ended_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fleet_downtimes');
    }
};
