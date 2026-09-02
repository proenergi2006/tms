<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Mekanisme login sementara sebelum SSO tersedia (PRD Bagian 11 & Architecture
 * Document Bagian 10). HANYA aktif pada environment local/testing — jangan
 * pernah diaktifkan di staging/production karena tidak memvalidasi password
 * apa pun (SYOP/TMS belum punya SSO provider bersama untuk divalidasi).
 */
class DevAuthController extends Controller
{
    public function users()
    {
        abort_unless(app()->environment(['local', 'testing']), 404);

        return response()->json([
            'data' => User::with('role')
                ->orderBy('name')
                ->get()
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role?->name,
                ]),
        ]);
    }

    public function login(Request $request)
    {
        abort_unless(app()->environment(['local', 'testing']), 404);

        $request->validate(['email' => ['required', 'email']]);

        $user = User::with(['role.permissions', 'branch'])->where('email', $request->input('email'))->first();

        if (! $user) {
            throw ValidationException::withMessages(['email' => 'Pengguna tidak ditemukan.']);
        }

        $token = $user->createToken('dev-login')->plainTextToken;

        return response()->json([
            'data' => [
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role ? ['id' => $user->role->id, 'name' => $user->role->name] : null,
                    // branch_id dipakai frontend untuk default & mengunci
                    // field cabang pada form Master Data/Armada bagi role
                    // bercabang (lihat User::isBranchScoped()) — bukan sumber
                    // otorisasi, backend tetap memvalidasi ulang tiap request.
                    'branch' => $user->branch ? ['id' => $user->branch->id, 'name' => $user->branch->name] : null,
                    // Dipakai frontend hanya untuk gating tampilan (sembunyikan
                    // menu/aksi yang jelas tidak relevan) — otorisasi sungguhan
                    // tetap di backend lewat middleware permission:*.
                    'permissions' => $user->role?->permissions->pluck('name') ?? [],
                ],
            ],
        ]);
    }
}
