<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Modules\MasterData\Models\Branch;
use Illuminate\Http\Request;

/**
 * Ringkasan insiden keselamatan/perilaku driver untuk Manajemen/Operational
 * Manager. Command CheckSosAlerts/CheckFlaggedPhotoAlerts/
 * CheckDriverBehaviorAlerts SUDAH mendeteksi & mengirim notifikasi ini
 * harian, tapi selalu ke role tim_logistik per cabang — tidak ada satu pun
 * tempat yang merangkum "berapa total insiden bulan ini, cabang mana paling
 * sering" lintas seluruh perusahaan. NotificationController::index() tidak
 * bisa dipakai untuk ini karena selalu di-scope ke user_id yang login
 * (lihat komentar di controller itu), sedangkan yang dibutuhkan di sini
 * adalah agregat company-wide TERLEPAS dari siapa penerima notifikasinya.
 */
class DriverIncidentReportController extends Controller
{
    private const TYPES = [
        'pod_sos_alert', 'pod_photo_location', 'pod_behavior_rest', 'pod_behavior_harsh', 'pod_behavior_fatigue',
    ];

    public function index(Request $httpRequest)
    {
        $user = $httpRequest->user();
        $branchId = $user->isBranchScoped() ? $user->branch_id : $httpRequest->query('branch_id');
        $dateFrom = $httpRequest->query('date_from');
        $dateTo = $httpRequest->query('date_to');

        // Notification tidak punya branch_id sendiri — didekati lewat cabang
        // PENERIMA notifikasi (users.branch_id), yang untuk tipe-tipe ini
        // selalu tim_logistik CABANG terkait (lihat NotificationService::
        // usersForRole() & CheckSosAlerts dkk.), jadi proxy ini akurat untuk
        // kelima tipe di atas walau bukan kolom terstruktur asli.
        $rows = Notification::query()
            ->join('users', 'users.id', '=', 'notifications.user_id')
            ->whereIn('notifications.type', self::TYPES)
            ->when($branchId, fn ($q) => $q->where('users.branch_id', $branchId))
            ->when($dateFrom, fn ($q) => $q->whereDate('notifications.created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('notifications.created_at', '<=', $dateTo))
            ->selectRaw('users.branch_id as branch_id, notifications.type as type, COUNT(*) as total')
            ->groupBy('branch_id', 'type')
            ->get();

        $branches = Branch::query()->whereIn('id', $rows->pluck('branch_id')->unique()->filter())->pluck('name', 'id');

        $byBranch = $rows->groupBy('branch_id')->map(function ($group, $branchId) use ($branches) {
            $counts = $group->pluck('total', 'type');

            return [
                'branch_id' => $branchId,
                'branch' => $branches->get($branchId) ?? '-',
                'pod_sos_alert' => (int) ($counts['pod_sos_alert'] ?? 0),
                'pod_photo_location' => (int) ($counts['pod_photo_location'] ?? 0),
                'pod_behavior_rest' => (int) ($counts['pod_behavior_rest'] ?? 0),
                'pod_behavior_harsh' => (int) ($counts['pod_behavior_harsh'] ?? 0),
                'pod_behavior_fatigue' => (int) ($counts['pod_behavior_fatigue'] ?? 0),
                'total' => (int) $group->sum('total'),
            ];
        })->sortByDesc('total')->values();

        $totals = collect(self::TYPES)->mapWithKeys(
            fn ($type) => [$type => (int) $rows->where('type', $type)->sum('total')]
        );

        return response()->json([
            'data' => [
                'by_branch' => $byBranch,
                'totals' => [...$totals->toArray(), 'total' => (int) $rows->sum('total')],
            ],
        ]);
    }
}
