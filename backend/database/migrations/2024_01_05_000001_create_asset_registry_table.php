<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_registry', function (Blueprint $table) {
            $table->id();
            $table->string('asset_code', 30)->unique();
            $table->enum('category', ['IT', 'GA']);
            $table->string('name', 150);
            $table->foreignId('pic')->nullable()->constrained('users')->nullOnDelete()->comment('Penanggung jawab aset');
            $table->string('location', 150)->nullable();
            $table->date('purchase_date')->nullable();
            $table->enum('status', ['aktif', 'rusak', 'dihapuskan'])->default('aktif');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_registry');
    }
};
