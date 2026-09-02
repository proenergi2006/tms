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
            $table->timestamp('last_inspection_at')->nullable()->after('status')
                ->comment('Waktu terakhir armada masuk workshop (WO selesai) — dasar pengecekan wajib 2 minggu.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fleets', function (Blueprint $table) {
            $table->dropColumn('last_inspection_at');
        });
    }
};
