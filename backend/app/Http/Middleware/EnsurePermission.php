<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * RBAC per-endpoint — Architecture Document Bagian 7.2. Dipasang di route
 * sebagai `->middleware('permission:nama.permission')`, dijalankan setelah
 * `auth:sanctum`. Daftar permission & pemetaan ke role ada di
 * RolePermissionSeeder. Bisa juga `permission:a,b` (OR — lolos kalau punya
 * SALAH SATU) untuk endpoint yang aturannya sama-sama berlaku bagi lebih
 * dari satu permission (otorisasi lebih granular per-role tetap dilakukan
 * di controller, ini hanya gerbang awal "punya akses sama sekali atau tidak").
 */
class EnsurePermission
{
    /**
     * `middleware('permission:a,b')` — Laravel SUDAH memecah string setelah
     * `:` berdasar koma menjadi argumen TERPISAH sebelum memanggil handle()
     * (bukan satu string "a,b" yang perlu di-explode manual di sini) —
     * makanya parameternya variadic ($permissions), bukan string tunggal.
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $hasAny = collect($permissions)->contains(fn ($p) => $request->user()?->hasPermission($p));

        abort_unless($hasAny, 403, 'Anda tidak memiliki izin untuk mengakses resource ini.');

        return $next($request);
    }
}
