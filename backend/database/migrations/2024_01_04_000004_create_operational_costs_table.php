<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operational_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fleet_id')->constrained('fleets')->restrictOnDelete();
            $table->foreignId('cost_type_id')->constrained('cost_types')->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->enum('source_type', ['work_order', 'manual'])->default('manual');
            $table->unsignedBigInteger('source_id')->nullable()->comment('FK ke work_orders.id bila source_type=work_order');
            $table->date('incurred_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['fleet_id', 'incurred_at'], 'idx_oc_fleet_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_costs');
    }
};
