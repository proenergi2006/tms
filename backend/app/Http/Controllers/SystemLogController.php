<?php

namespace App\Http\Controllers;

use App\Services\SystemLogReader;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Log aplikasi (Laravel log) — supaya Admin Sistem tahu kalau ada error di
 * server tanpa perlu akses SSH. Hanya baca, khusus permission
 * system-log.view (lihat RolePermissionSeeder).
 */
class SystemLogController extends Controller
{
    private const ERROR_LEVELS = ['emergency', 'alert', 'critical', 'error'];

    public function index(Request $request, SystemLogReader $reader)
    {
        $entries = collect($reader->entries())
            ->when($request->filled('level'), fn ($c) => $c->where('level', strtolower($request->query('level'))))
            ->when($request->filled('search'), function ($c) use ($request) {
                $needle = strtolower($request->query('search'));

                return $c->filter(fn ($e) => str_contains(strtolower($e['message']), $needle));
            })
            ->when($request->filled('date_from'), fn ($c) => $c->filter(
                fn ($e) => substr($e['timestamp'], 0, 10) >= $request->query('date_from')
            ))
            ->when($request->filled('date_to'), fn ($c) => $c->filter(
                fn ($e) => substr($e['timestamp'], 0, 10) <= $request->query('date_to')
            ))
            ->values();

        $perPage = $request->integer('per_page', 20);
        $page = max(1, $request->integer('page', 1));

        return response()->json([
            'data' => $entries->forPage($page, $perPage)->values(),
            'meta' => ['total' => $entries->count(), 'current_page' => $page, 'per_page' => $perPage],
        ]);
    }

    public function summary(SystemLogReader $reader)
    {
        $since = now()->subDay();

        $recentErrors = collect($reader->entries())
            ->filter(fn ($e) => in_array($e['level'], self::ERROR_LEVELS, true))
            ->filter(fn ($e) => Carbon::parse($e['timestamp'])->greaterThanOrEqualTo($since));

        return response()->json([
            'error_count_24h' => $recentErrors->count(),
            'last_error_at' => $recentErrors->first()['timestamp'] ?? null,
        ]);
    }
}
