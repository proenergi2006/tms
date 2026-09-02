<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Approval Workflow Engine dinamis — rantai approval sekarang data
     * (baris di tabel ini), bukan kondisional PHP hardcoded (lihat
     * ApprovalWorkflowService). sequence_order menentukan urutan eksekusi
     * (makin kecil makin dulu dijalankan), role_name menentukan siapa yang
     * berwenang pada tahap itu, is_active=false berarti tahap tsb dilewati
     * tanpa dihapus (work_orders.approval_step_id masih bisa merujuk baris
     * lama — lihat migrasi add_approval_step_id_to_work_orders_table).
     */
    public function up(): void
    {
        Schema::create('approval_steps', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('sequence_order')->comment('Urutan eksekusi tahap approval, makin kecil makin dulu dijalankan.');
            $table->string('role_name', 50)->comment('Role yang berwenang pada tahap ini, mis. fleet_operations, kepala_pool.');
            $table->string('label', 100)->comment('Label tampilan, mis. "Verifikasi Fleet Operations".');
            $table->boolean('is_active')->default(true)->comment('Tahap nonaktif dilewati saat menentukan approver berikutnya.');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_steps');
    }
};
