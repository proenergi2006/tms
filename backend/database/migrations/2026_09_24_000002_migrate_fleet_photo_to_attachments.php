<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Armada sekarang bisa punya lebih dari satu foto (depan/belakang/
     * kanan/kiri) lewat tabel attachments (polymorphic, sudah dipakai
     * Request/WorkOrder) — bukan kolom tunggal fleets.photo_path lagi.
     * Foto yang sudah ada (kalau ada) dipindah dulu ke attachments
     * sebelum kolomnya dihapus supaya tidak ada data yang hilang.
     */
    public function up(): void
    {
        $uploaderId = DB::table('users')->orderBy('id')->value('id');

        if ($uploaderId !== null) {
            $fleetsWithPhoto = DB::table('fleets')->whereNotNull('photo_path')->get(['id', 'photo_path']);

            foreach ($fleetsWithPhoto as $fleet) {
                DB::table('attachments')->insert([
                    'attachable_type' => 'fleet',
                    'attachable_id' => $fleet->id,
                    'file_path' => $fleet->photo_path,
                    'caption' => null,
                    'uploaded_by' => $uploaderId,
                    'uploaded_at' => now(),
                ]);
            }
        }

        Schema::table('fleets', function (Blueprint $table) {
            $table->dropColumn('photo_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fleets', function (Blueprint $table) {
            $table->string('photo_path')->nullable()->after('capacity');
        });

        $photos = DB::table('attachments')->where('attachable_type', 'fleet')->orderBy('id')->get();

        foreach ($photos as $photo) {
            DB::table('fleets')->where('id', $photo->attachable_id)->update(['photo_path' => $photo->file_path]);
        }

        DB::table('attachments')->where('attachable_type', 'fleet')->delete();
    }
};
