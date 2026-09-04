<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Login kredensial TMS (username + password) — jalur utama sebelum SSO
 * tersedia, dan tetap dibutuhkan SETELAHNYA untuk pengguna yang tidak
 * punya akun SYOP (mis. sebagian driver/mekanik cabang). Berbeda dari
 * DevAuthController (login tanpa password, hanya aktif di local/testing
 * untuk kebutuhan pengembangan) — controller ini aktif di semua environment.
 *
 * Dicocokkan lewat `username`, BUKAN `email` — lihat catatan di
 * App\Models\User::$fillable. SSO (SsoController) tetap pakai email,
 * jalur ini tidak menyentuhnya sama sekali.
 */
class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $user = User::with(['role.permissions', 'branch'])
            ->where('username', $request->input('username'))
            ->first();

        // Pesan generik (tidak membedakan "username tidak ada" vs "password
        // salah") supaya tidak bisa dipakai untuk menebak username terdaftar.
        if (! $user || ! $user->password || ! Hash::check($request->input('password'), $user->password)) {
            throw ValidationException::withMessages([
                'username' => 'Username atau password salah.',
            ]);
        }

        if ($user->status !== 'aktif') {
            throw ValidationException::withMessages([
                'username' => 'Akun ini sudah tidak aktif. Hubungi Admin Sistem.',
            ]);
        }

        $token = $user->createToken('login')->plainTextToken;

        return response()->json([
            'data' => [
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'email' => $user->email,
                    'role' => $user->role ? ['id' => $user->role->id, 'name' => $user->role->name] : null,
                    'branch' => $user->branch ? ['id' => $user->branch->id, 'name' => $user->branch->name] : null,
                    'permissions' => $user->role?->permissions->pluck('name') ?? [],
                ],
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }
}
