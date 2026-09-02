<?php

namespace App\Modules\Fleet\Services;

use App\Modules\Fleet\Models\Fleet;
use App\Modules\Fleet\Models\FleetDowntime;
use Illuminate\Support\Collection;

/**
 * Availability rate & MTBF/MTTR — permintaan fitur eksplisit (Pelaporan &
 * Analitik). Dihitung dari fleet_downtimes (sudah ada, diisi otomatis lewat
 * FleetDowntimeService), bukan tabel/tracking baru. Dipakai bersama oleh
 * FleetHistoryController (per armada) dan FleetController (agregat lintas
 * armada untuk Dashboard).
 */
class FleetReliabilityService
{
    /**
     * - Availability rate: persentase waktu armada beroperasi normal sejak
     *   armada terdaftar (fleet.created_at) s/d sekarang.
     * - MTTR (Mean Time To Repair): rata-rata durasi perbaikan yang SUDAH
     *   selesai (ended_at terisi) — window yang masih berjalan tidak masuk
     *   hitungan supaya tidak bias oleh perbaikan yang belum tuntas.
     * - MTBF (Mean Time Between Failures): total waktu beroperasi normal
     *   dibagi jumlah kejadian rusak — definisi standar reliability
     *   engineering, BUKAN jarak antar kejadian (yang salah dihitung kalau
     *   ada downtime yang tumpang-tindih).
     *
     * @param  Collection<int, FleetDowntime>|null  $downtimes  Sudah dimuat
     *                                                          sebelumnya (hindari N+1 saat dipanggil untuk banyak armada sekaligus) —
     *                                                          bila null, diambil sendiri dari relasi.
     */
    public function compute(Fleet $fleet, ?Collection $downtimes = null): array
    {
        $records = $downtimes ?? $fleet->downtimes()->get();

        $totalDowntimeMinutes = $records->sum(
            fn (FleetDowntime $d) => (int) round($d->started_at->diffInMinutes($d->ended_at ?? now()))
        );

        $periodMinutes = max(1, (int) round($fleet->created_at->diffInMinutes(now())));
        $uptimeMinutes = max(0, $periodMinutes - $totalDowntimeMinutes);

        $completed = $records->filter(fn (FleetDowntime $d) => $d->ended_at !== null);
        $completedRepairCount = $completed->count();
        $totalRepairMinutes = $completed->sum(fn (FleetDowntime $d) => $d->started_at->diffInMinutes($d->ended_at));
        $mttrMinutes = $completedRepairCount > 0 ? (int) round($totalRepairMinutes / $completedRepairCount) : null;

        $failureCount = $records->count();
        $mtbfMinutes = $failureCount > 0 ? (int) round($uptimeMinutes / $failureCount) : null;

        return [
            'total_downtime_minutes' => $totalDowntimeMinutes,
            'period_minutes' => $periodMinutes,
            'uptime_minutes' => $uptimeMinutes,
            'failure_count' => $failureCount,
            'completed_repair_count' => $completedRepairCount,
            'total_repair_minutes' => (int) round($totalRepairMinutes),
            'availability_rate' => round($uptimeMinutes / $periodMinutes * 100, 2),
            'mttr_minutes' => $mttrMinutes,
            'mtbf_minutes' => $mtbfMinutes,
        ];
    }
}
