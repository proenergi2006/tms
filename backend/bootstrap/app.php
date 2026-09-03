<?php

use App\Http\Middleware\EnsurePermission;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'permission' => EnsurePermission::class,
        ]);

        // API murni — tidak ada route web bernama "login" untuk di-redirect.
        // Default Laravel (Authenticate::redirectTo()) manggil route('login')
        // UNCONDITIONAL kalau tidak ada callback terdaftar, untuk request yang
        // tidak dianggap expectsJson() (mis. request nyata di production
        // tanpa header Accept: application/json yang eksplisit) — karena
        // route itu tidak pernah ada di app ini, route('login') melempar
        // RouteNotFoundException SEBELUM AuthenticationException-nya sendiri
        // sempat terbentuk (jadi render(AuthenticationException) di bawah
        // tidak pernah kena). redirectGuestsTo(null) memotong itu tepat di
        // sumbernya lewat Authenticate::redirectUsing().
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Pesan default Laravel ("Too Many Attempts.") berbahasa Inggris —
        // ganti supaya konsisten dengan seluruh pesan error lain di API ini
        // (Bahasa Indonesia, lihat AuthController/RoleController dst).
        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Terlalu banyak percobaan login. Coba lagi dalam beberapa saat.',
                ], 429, $e->getHeaders());
            }
        });

        // Lapisan jaga-jaga kalau Handler default Laravel untuk
        // AuthenticationException masih mencoba redirect (bukan JSON) untuk
        // request non-JSON walau redirectTo() sudah dipastikan null di atas
        // — paksa selalu JSON 401 untuk seluruh path api/*.
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Sesi Anda sudah berakhir. Silakan login kembali.',
                ], 401);
            }
        });
    })->create();
