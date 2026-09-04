<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Login manual sekarang pakai username, bukan email (email tetap ada,
     * cuma dipakai SSO — lihat App\Http\Controllers\SsoController). Kolom
     * ditambah nullable dulu, di-backfill dari local-part email untuk user
     * yang sudah ada (dengan dedup kalau ada tabrakan), baru dikunci unik +
     * NOT NULL — supaya migration ini aman dijalankan di database yang
     * sudah berisi data (bukan cuma database kosong).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 50)->nullable()->after('email');
        });

        $seen = [];
        foreach (DB::table('users')->orderBy('id')->get(['id', 'email']) as $user) {
            // Str::slug() SENGAJA tidak dipakai di sini — dia menghapus
            // underscore sepenuhnya (mis. "admin_sistem" jadi "adminsistem"),
            // beda dari pola username yang dipakai DatabaseSeeder (yang
            // mempertahankan underscore & titik apa adanya, mis.
            // "kepala_pool.jkt") — kalau dua pola beda, username hasil
            // backfill untuk user existing tidak akan konsisten dengan
            // seeder untuk user baru.
            $localPart = strtolower(strstr($user->email, '@', true) ?: $user->email);
            $base = preg_replace('/[^a-z0-9._-]+/', '', $localPart);
            $base = trim($base, '.-_');
            $base = $base !== '' ? $base : 'user'.$user->id;

            $candidate = $base;
            $suffix = 1;
            while (isset($seen[$candidate])) {
                $suffix++;
                $candidate = "{$base}{$suffix}";
            }
            $seen[$candidate] = true;

            DB::table('users')->where('id', $user->id)->update(['username' => $candidate]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 50)->nullable(false)->unique()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('username');
        });
    }
};
