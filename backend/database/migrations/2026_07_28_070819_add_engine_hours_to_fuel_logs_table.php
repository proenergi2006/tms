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
        Schema::table('fuel_logs', function (Blueprint $table) {
            $table->unsignedInteger('engine_hours')->nullable()->after('odometer')
                ->comment('Jam operasi mesin (HM) saat pencatatan — sumber data untuk servis berkala berbasis jam operasi.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fuel_logs', function (Blueprint $table) {
            $table->dropColumn('engine_hours');
        });
    }
};
