<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fleet_id')->constrained('fleets')->restrictOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained('work_orders')->nullOnDelete();
            $table->foreignId('job_type_id')->nullable()->constrained('job_types')->nullOnDelete();
            $table->string('description')->nullable();
            $table->decimal('cost', 15, 2)->default(0);
            $table->date('performed_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index('fleet_id', 'idx_mh_fleet');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_history');
    }
};
