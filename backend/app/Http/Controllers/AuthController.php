<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Login kredensial TMS (email + password) — jalur utama sebelum SSO
 * tersedia, dan tetap dibutuhkan SETELAHNYA untuk pengguna yang tidak
 * punya akun SYOP (mis. sebagian driver/mekanik cabang). Berbeda dari
 * DevAuthController (login tanpa password, hanya aktif di local/testing
 * untuk kebutuhan pengembangan) — controller ini aktif di semua environment.
 */
class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::with(['role.permissions', 'branch'])
            ->where('email', $request->input('email'))
            ->first();

        // Pesan generik (tidak membedakan "email tidak ada" vs "password
        // salah") supaya tidak bisa dipakai untuk menebak email terdaftar.
        if (! $user || ! $user->password || ! Hash::check($request->input('password'), $user->password)) {
            throw ValidationException::withMessages([
                'email' => 'Email atau password salah.',
            ]);
        }

        if ($user->status !== 'aktif') {
            throw ValidationException::withMessages([
                'email' => 'Akun ini sudah tidak aktif. Hubungi Admin Sistem.',
            ]);
        }

        $token = $user->createToken('login')->plainTextToken;

        return response()->json([
            'data' => [
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
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
