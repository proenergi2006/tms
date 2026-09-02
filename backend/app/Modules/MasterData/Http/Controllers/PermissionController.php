<?php

namespace App\Modules\MasterData\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MasterData\Models\Permission;

/**
 * Daftar permission BACAAN SAJA — permission bersesuaian 1:1 dengan
 * middleware `permission:<nama>` yang benar-benar dipasang di kode (lihat
 * EnsurePermission), jadi tidak masuk akal dibuat CRUD lewat UI: permission
 * baru yang dibuat manual tidak akan menggerbangi apa pun sampai
 * ditambahkan juga di rute terkait. Yang bisa diubah lewat RBAC UI hanya
 * ROLE mana yang memilikinya (lihat RoleController::syncPermissions()).
 */
class PermissionController extends Controller
{
    public function index()
    {
        return response()->json([
            'data' => Permission::orderBy('name')->get(['id', 'name']),
        ]);
    }
}
