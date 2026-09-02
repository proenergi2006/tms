<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->dropColumn('estimated_cost');
            $table->unsignedSmallInteger('estimated_days')->nullable()->after('diagnosis')
                ->comment('Estimasi lama perbaikan dalam hari, diisi SA saat pengajuan (type=perbaikan).');
        });
    }

    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->dropColumn('estimated_days');
            $table->decimal('estimated_cost', 15, 2)->nullable()->after('diagnosis');
        });
    }
};
