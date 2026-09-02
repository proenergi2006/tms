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
        Schema::table('fleets', function (Blueprint $table) {
            // Ambang servis berkala — nullable, dimensi mana pun yang tidak
            // diisi berarti tidak dipakai untuk armada ini (mis. armada tanpa
            // pencatatan jam operasi cukup diatur km & bulan saja).
            $table->unsignedInteger('service_interval_km')->nullable()->after('last_inspection_at')
                ->comment('Interval servis berkala berdasar jarak tempuh (km).');
            $table->unsignedInteger('service_interval_engine_hours')->nullable()->after('service_interval_km')
                ->comment('Interval servis berkala berdasar jam operasi mesin.');
            $table->unsignedInteger('service_interval_months')->nullable()->after('service_interval_engine_hours')
                ->comment('Interval servis berkala berdasar waktu (bulan).');

            // Baseline servis terakhir — dipakai bersama interval di atas
            // untuk menghitung jadwal servis berikutnya (whichever comes
            // first). Diisi otomatis saat Work Order selesai, sama seperti
            // last_inspection_at (lihat WorkOrderCompletionService).
            $table->date('last_service_at')->nullable()->after('service_interval_months');
            $table->unsignedInteger('last_service_odometer')->nullable()->after('last_service_at');
            $table->unsignedInteger('last_service_engine_hours')->nullable()->after('last_service_odometer');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fleets', function (Blueprint $table) {
            $table->dropColumn([
                'service_interval_km', 'service_interval_engine_hours', 'service_interval_months',
                'last_service_at', 'last_service_odometer', 'last_service_engine_hours',
            ]);
        });
    }
};
