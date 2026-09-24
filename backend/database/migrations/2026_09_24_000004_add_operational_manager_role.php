<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Role baru "Operational Manager" — fungsinya diminta identik dengan
     * "Manajemen" (permission & akses lintas-cabang sama persis). Permission-
     * nya disalin langsung dari role manajemen yang sudah ada di database
     * (bukan ditulis ulang manual dari RolePermissionSeeder) supaya benar-
     * benar identik walau permission manajemen sempat diubah manual lewat
     * RBAC UI dan sudah menyimpang dari katalog seeder.
     */
    public function up(): void
    {
        $roleId = DB::table('roles')->where('name', 'operational_manager')->value('id');

        if ($roleId === null) {
            $roleId = DB::table('roles')->insertGetId([
                'name' => 'operational_manager',
                'created_at' => now(),
            ]);
        }

        $manajemenId = DB::table('roles')->where('name', 'manajemen')->value('id');

        if ($manajemenId === null) {
            return;
        }

        $existing = DB::table('role_permissions')->where('role_id', $roleId)->pluck('permission_id');

        $toInsert = DB::table('role_permissions')
            ->where('role_id', $manajemenId)
            ->pluck('permission_id')
            ->diff($existing)
            ->map(fn ($permissionId) => ['role_id' => $roleId, 'permission_id' => $permissionId])
            ->all();

        if ($toInsert !== []) {
            DB::table('role_permissions')->insert($toInsert);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $roleId = DB::table('roles')->where('name', 'operational_manager')->value('id');

        if ($roleId === null) {
            return;
        }

        DB::table('role_permissions')->where('role_id', $roleId)->delete();
        DB::table('roles')->where('id', $roleId)->delete();
    }
};
