<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Menerima token SSO terenkripsi dari SYOP (Architecture Document Bagian
 * 7.1). Token dienkripsi AES-256-GCM oleh sisi SYOP dengan
 * SYOP_SSO_KEY/SYOP_SSO_AAD, di-base64, urutan byte IV(12) + ciphertext +
 * auth tag(16). Auth tag GCM WAJIB diverifikasi oleh openssl_decrypt (return
 * false kalau gagal) — beda dari sekadar base64_decode+json_decode tanpa
 * verifikasi, yang membuat token bisa dipalsukan bebas oleh siapa pun yang
 * tahu bentuk payload-nya.
 *
 * Catatan: key/AAD (SYOP_SSO_KEY/SYOP_SSO_AAD) sengaja dipakai ulang dari
 * pasangan SYOP v3<->SYOP v4 (keputusan eksplisit, bukan default yang
 * direkomendasikan — key terpisah per aplikasi lebih aman karena membatasi
 * blast radius kalau salah satu bocor).
 */
class SsoController extends Controller
{
    private const IV_LENGTH = 12;

    private const TAG_LENGTH = 16;

    public function login(Request $request)
    {
        $request->validate([
            'token' => ['required', 'string'],
        ]);

        $payload = $this->decryptToken($request->input('token'));

        if (! $payload || empty($payload['email'])) {
            Log::warning('SSO login gagal: token tidak valid atau payload tidak lengkap.');

            throw ValidationException::withMessages([
                'token' => 'Token SSO tidak valid atau sudah kedaluwarsa.',
            ]);
        }

        $user = User::with(['role.permissions', 'branch'])
            ->where('email', $payload['email'])
            ->first();

        if (! $user) {
            return response()->json([
                'message' => 'User dengan email ini belum terdaftar di TMS. Hubungi Admin Sistem.',
            ], 403);
        }

        if ($user->status !== 'aktif') {
            return response()->json([
                'message' => 'Akun ini sudah tidak aktif. Hubungi Admin Sistem.',
            ], 403);
        }

        // Ikat sso_id ke user pada login SSO pertama; kalau sudah terikat ke
        // sso_id lain, tolak — mencegah email yang sama menyamar sebagai
        // identitas SSO berbeda dari yang sebelumnya tercatat.
        if (! empty($payload['sso_id'])) {
            if ($user->sso_id === null) {
                $user->sso_id = $payload['sso_id'];
                $user->save();
            } elseif ($user->sso_id !== $payload['sso_id']) {
                Log::warning("SSO login gagal: sso_id tidak cocok untuk email {$user->email}.");

                return response()->json([
                    'message' => 'Identitas SSO tidak cocok dengan akun TMS ini. Hubungi Admin Sistem.',
                ], 403);
            }
        }

        $token = $user->createToken('sso-login')->plainTextToken;

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

    /**
     * TODO: konfirmasi ke tim SYOP kalau urutan/panjang byte token asli
     * mereka beda dari asumsi IV(12)+ciphertext+tag(16) di sini — kalau
     * beda, decrypt gagal dengan aman (null) tapi login tidak akan pernah
     * berhasil sampai disesuaikan dengan format sebenarnya.
     */
    private function decryptToken(string $token): ?array
    {
        $raw = base64_decode($token, true);

        if ($raw === false || strlen($raw) <= self::IV_LENGTH + self::TAG_LENGTH) {
            return null;
        }

        $key = base64_decode((string) config('services.syop_sso.key'), true);
        $aad = (string) config('services.syop_sso.aad');

        if (! $key || strlen($key) !== 32) {
            Log::error('SSO: SYOP_SSO_KEY belum dikonfigurasi atau bukan 32 byte setelah base64-decode.');

            return null;
        }

        $iv = substr($raw, 0, self::IV_LENGTH);
        $tag = substr($raw, -self::TAG_LENGTH);
        $ciphertext = substr($raw, self::IV_LENGTH, -self::TAG_LENGTH);

        $plaintext = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            $aad
        );

        if ($plaintext === false) {
            return null;
        }

        $payload = json_decode($plaintext, true);

        return is_array($payload) ? $payload : null;
    }
}
