<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fleets', function (Blueprint $table) {
            $table->id();
            $table->string('plate_number', 20)->unique()->comment('Nomor polisi');
            $table->string('fleet_type', 50)->comment('mis. Tronton, Tangki');
            $table->string('brand', 50)->nullable();
            $table->string('model', 50)->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->decimal('capacity', 10, 2)->nullable()->comment('Kapasitas angkut (ton/liter)');
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->enum('status', ['aktif', 'maintenance', 'nonaktif'])->default('aktif');
            $table->softDeletes();
            $table->timestamps();

            $table->index('status', 'idx_fleets_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fleets');
    }
};
