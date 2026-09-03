<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Menerima token SSO terenkripsi dari SYOP (Architecture Document Bagian
 * 7.1). Token dienkripsi AES-256-GCM oleh sisi SYOP dengan
 * SYOP_SSO_KEY/SYOP_SSO_AAD, base64url (tanpa padding), urutan byte
 * IV(12) + TAG(16) + ciphertext — dikonfirmasi langsung dari kode
 * enkripsi SYOP v3 (action/sso-tms.php) yang sebenarnya, bukan asumsi.
 * Auth tag GCM WAJIB diverifikasi oleh openssl_decrypt (return false
 * kalau gagal) — beda dari sekadar base64_decode+json_decode tanpa
 * verifikasi, yang membuat token bisa dipalsukan bebas oleh siapa pun yang
 * tahu bentuk payload-nya.
 *
 * Payload dari SYOP v3: {iss, email, wilayah_id, iat, exp, nonce} — TIDAK
 * ada field sso_id (beda dari asumsi awal), exp = iat + 60 (token cuma
 * valid 60 detik). email sudah di-lowercase+trim di sisi SYOP.
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

    private function decryptToken(string $token): ?array
    {
        // Token asli dari SYOP ternyata base64url (pakai '-'/'_', tanpa
        // padding '=') — dikonfirmasi langsung dari token production nyata,
        // bukan base64 standar seperti asumsi awal. base64_decode(strict)
        // menolak karakter '-'/'_' dan langsung return false untuk base64url,
        // jadi harus dinormalisasi dulu. strtr terhadap string yang sudah
        // base64 standar tidak berefek (aman untuk keduanya).
        $normalized = strtr($token, '-_', '+/');
        $padding = strlen($normalized) % 4;
        if ($padding > 0) {
            $normalized .= str_repeat('=', 4 - $padding);
        }

        $raw = base64_decode($normalized, true);

        if ($raw === false) {
            Log::warning('SSO decrypt gagal: base64 tidak valid setelah normalisasi base64url.');

            return null;
        }

        if (strlen($raw) <= self::IV_LENGTH + self::TAG_LENGTH) {
            Log::warning("SSO decrypt gagal: hasil base64-decode cuma ".strlen($raw)." byte, minimal harus lebih dari ".(self::IV_LENGTH + self::TAG_LENGTH)." byte (IV 12 + tag 16).");

            return null;
        }

        $key = base64_decode((string) config('services.syop_sso.key'), true);
        $aad = (string) config('services.syop_sso.aad');

        if (! $key || strlen($key) !== 32) {
            Log::error('SSO decrypt gagal: SYOP_SSO_KEY belum dikonfigurasi atau bukan 32 byte setelah base64-decode. Panjang key saat ini: '.($key ? strlen($key) : 0).' byte.');

            return null;
        }

        // Urutan byte SYOP v3 (dikonfirmasi dari kode aslinya): IV(12) +
        // TAG(16) + ciphertext — tag di TENGAH, bukan di paling belakang.
        $iv = substr($raw, 0, self::IV_LENGTH);
        $tag = substr($raw, self::IV_LENGTH, self::TAG_LENGTH);
        $ciphertext = substr($raw, self::IV_LENGTH + self::TAG_LENGTH);

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
            Log::warning('SSO decrypt gagal: openssl_decrypt AES-256-GCM gagal (auth tag tidak cocok). Panjang raw='.strlen($raw)." byte, IV=".self::IV_LENGTH.' byte + TAG='.self::TAG_LENGTH.' byte + ciphertext='.strlen($ciphertext).' byte, AAD="'.$aad.'".');

            return null;
        }

        $payload = json_decode($plaintext, true);

        if (! is_array($payload)) {
            Log::warning('SSO decrypt gagal: hasil dekripsi bukan JSON object yang valid. Panjang plaintext='.strlen($plaintext).' byte.');

            return null;
        }

        // Token SYOP v3 sengaja berumur pendek (60 detik, lihat docblock
        // kelas) — exp WAJIB dicek di sini, openssl_decrypt tidak tahu soal
        // waktu, cuma soal integritas ciphertext.
        if (isset($payload['exp']) && time() > (int) $payload['exp']) {
            Log::warning('SSO decrypt gagal: token sudah kedaluwarsa (exp='.$payload['exp'].', now='.time().').');

            return null;
        }

        return $payload;
    }
}
