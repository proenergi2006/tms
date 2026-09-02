<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_orders', function (Blueprint $table) {
            $table->id();
            $table->string('wo_no', 30)->unique();
            $table->foreignId('request_id')->constrained('requests')->restrictOnDelete();
            $table->enum('execution_type', ['internal', 'eksternal'])->nullable()->comment('Diisi Tim Logistik saat menetapkan pelaksana, lihat Design Document 3.3');
            $table->foreignId('mechanic_id')->nullable()->constrained('mechanics')->nullOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->enum('status', ['waiting', 'on_progress', 'finished'])
                ->default('waiting')
                ->comment('Status pelaksanaan pekerjaan');
            $table->enum('approval_status', [
                'submitted', 'pool_verified', 'bm_verified', 'logistik_verified',
                'finance_approved', 'completed', 'rejected',
            ])->default('submitted')->comment('State machine approval, lihat Design Document 4.2');
            $table->dateTime('started_at')->nullable();
            $table->dateTime('finished_at')->nullable();
            $table->timestamps();

            $table->index('status', 'idx_wo_status');
            $table->index('approval_status', 'idx_wo_approval_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_orders');
    }
};
