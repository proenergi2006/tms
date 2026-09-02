<?php

namespace App\Modules\MasterData\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MasterData\Models\Role;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Manajemen Role & Permission (RBAC) — PRD Bagian 4, permission
 * `rbac.manage` (lihat RolePermissionSeeder). Role bawaan (Role::SYSTEM_ROLES)
 * tidak boleh di-rename/dihapus karena namanya dirujuk langsung di kode
 * (alur approval, branch-scoping, dst) — tapi permission-nya tetap bebas
 * diubah, itu justru fungsi utama fitur ini.
 */
class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::withCount('users')->with('permissions:id,name')->orderBy('name')->get();

        return response()->json([
            'data' => $roles->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'is_system' => $role->isSystemRole(),
                'user_count' => $role->users_count,
                'permissions' => $role->permissions->pluck('id'),
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:50', 'unique:roles,name',
                'regex:/^[a-z][a-z0-9_]*$/',
            ],
        ], [
            'name.regex' => 'Nama role harus huruf kecil, angka, dan underscore saja (mis. quality_control), tanpa spasi.',
        ]);

        $role = Role::create($data);

        return $this->show($role);
    }

    public function show(Role $role)
    {
        $role->loadCount('users')->load('permissions:id,name');

        return response()->json([
            'data' => [
                'id' => $role->id,
                'name' => $role->name,
                'is_system' => $role->isSystemRole(),
                'user_count' => $role->users_count,
                'permissions' => $role->permissions->pluck('id'),
            ],
        ]);
    }

    public function update(Request $request, Role $role)
    {
        if ($role->isSystemRole()) {
            throw ValidationException::withMessages([
                'name' => 'Role bawaan sistem tidak dapat diganti nama — namanya dipakai langsung pada alur approval/otorisasi di kode.',
            ]);
        }

        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:50',
                Rule::unique('roles', 'name')->ignore($role->id),
                'regex:/^[a-z][a-z0-9_]*$/',
            ],
        ], [
            'name.regex' => 'Nama role harus huruf kecil, angka, dan underscore saja (mis. quality_control), tanpa spasi.',
        ]);

        $role->update($data);

        return $this->show($role);
    }

    /**
     * Mengganti seluruh permission milik role ini — TERSEDIA UNTUK SEMUA
     * ROLE termasuk role bawaan sistem (inti fitur RBAC management, beda
     * dari update() yang memblokir rename role bawaan).
     */
    public function syncPermissions(Request $request, Role $role)
    {
        $data = $request->validate([
            'permission_ids' => ['present', 'array'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
        ]);

        $role->permissions()->sync($data['permission_ids']);

        return $this->show($role);
    }

    public function destroy(Role $role)
    {
        if ($role->isSystemRole()) {
            throw ValidationException::withMessages([
                'name' => 'Role bawaan sistem tidak dapat dihapus.',
            ]);
        }

        try {
            $role->delete();
        } catch (QueryException) {
            // FK restrictOnDelete pada users.role_id — role ini masih
            // dipakai oleh user aktif.
            throw ValidationException::withMessages([
                'name' => 'Role ini masih dipakai oleh satu atau lebih pengguna, tidak dapat dihapus.',
            ]);
        }

        return response()->noContent();
    }
}
