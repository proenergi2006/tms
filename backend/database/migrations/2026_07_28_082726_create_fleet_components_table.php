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
        Schema::create('fleet_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fleet_id')->constrained('fleets')->cascadeOnDelete();
            // Sama dengan spareparts.category (ban/oli_pelumas/aki_kelistrikan/
            // rem) — dibatasi ke 4 komponen major, bukan seluruh kategori
            // sparepart, sesuai permintaan fitur "manajemen ban dan komponen
            // major lain (aki, oli, rem)".
            $table->enum('component_type', ['ban', 'oli_pelumas', 'aki_kelistrikan', 'rem']);
            $table->date('last_replaced_at')->nullable();
            $table->unsignedInteger('last_replaced_odometer')->nullable();
            $table->unsignedInteger('interval_km')->nullable();
            $table->unsignedInteger('interval_months')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->unique(['fleet_id', 'component_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fleet_components');
    }
};
