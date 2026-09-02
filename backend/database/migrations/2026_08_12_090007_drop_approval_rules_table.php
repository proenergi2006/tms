<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mekanisme ambang nominal Finance dihapus total bersama role Finance
     * itu sendiri (satu-satunya kegunaan approval_rules) — approval sekarang
     * satu tahap (Kepala Pool/Leader Operations), lihat
     * ApprovalWorkflowService::currentStage().
     */
    public function up(): void
    {
        Schema::dropIfExists('approval_rules');
    }

    public function down(): void
    {
        Schema::create('approval_rules', function (Blueprint $table) {
            $table->id();
            $table->string('role', 50)->comment('Role yang wajib approve, mis. finance');
            $table->decimal('min_amount', 15, 2)->default(0);
            $table->decimal('max_amount', 15, 2)->nullable()->comment('NULL = tanpa batas atas');
            $table->unsignedSmallInteger('sequence_order')->comment('Urutan tahap approval');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }
};
