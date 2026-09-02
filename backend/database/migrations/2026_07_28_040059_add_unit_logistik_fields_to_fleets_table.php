<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Field master data unit logistik tambahan (chasis, engine, no. keur,
     * kepemilikan, No. B3-Dishub, status mutasi, status leasing) —
     * melengkapi field yang sudah ada (plate_number, fleet_type, brand,
     * model, year, capacity, branch_id, status).
     */
    public function up(): void
    {
        Schema::table('fleets', function (Blueprint $table) {
            $table->string('chassis_number', 100)->nullable()->after('model')->comment('No. Rangka/Chasis');
            $table->string('engine_number', 100)->nullable()->after('chassis_number')->comment('No. Mesin/Engine');
            $table->string('keur_number', 100)->nullable()->after('engine_number')->comment('No. Uji Kir/Keur');
            $table->enum('ownership', ['milik_sendiri', 'sewa', 'leasing'])->default('milik_sendiri')->after('capacity');
            $table->string('leasing_status', 100)->nullable()->after('ownership')->comment('Relevan bila ownership=leasing');
            $table->string('b3_dishub_number', 100)->nullable()->after('leasing_status')->comment('No. Izin B3-Dishub');
            $table->enum('mutation_status', ['tidak_ada', 'pindah', 'jual', 'ganti_nopol'])
                ->default('tidak_ada')
                ->after('b3_dishub_number')
                ->comment('Status mutasi armada');
        });
    }

    public function down(): void
    {
        Schema::table('fleets', function (Blueprint $table) {
            $table->dropColumn([
                'chassis_number', 'engine_number', 'keur_number',
                'ownership', 'leasing_status', 'b3_dishub_number', 'mutation_status',
            ]);
        });
    }
};
