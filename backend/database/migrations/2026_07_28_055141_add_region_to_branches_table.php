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
        Schema::table('branches', function (Blueprint $table) {
            $table->enum('region', ['wilayah_1', 'wilayah_2'])->nullable()->after('code')
                ->comment('Kelompok wilayah untuk verifikasi Fleet Operations: wilayah_1 = SBY/JKT/PLG/BJM/PTK, wilayah_2 = SUL/SMD.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn('region');
        });
    }
};
