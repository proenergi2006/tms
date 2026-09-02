<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_no', 30)->unique();
            $table->enum('type', ['perbaikan', 'sparepart', 'restock', 'pembelian', 'lainnya']);
            $table->foreignId('fleet_id')->nullable()->constrained('fleets')->nullOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete()->comment('FK ke users.id (driver/mekanik)');
            $table->text('description');
            $table->enum('status', [
                'submitted', 'pool_verified', 'bm_verified', 'logistik_verified',
                'finance_approved', 'completed', 'rejected',
            ])->default('submitted');
            $table->timestamps();

            $table->index('status', 'idx_requests_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requests');
    }
};
