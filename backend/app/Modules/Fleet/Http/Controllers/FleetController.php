<?php

namespace App\Modules\Fleet\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Fleet\Http\Requests\FleetRequest;
use App\Modules\Fleet\Http\Resources\FleetResource;
use App\Modules\Fleet\Models\Fleet;
use App\Modules\Fleet\Models\FleetLegalDoc;
use App\Modules\Fleet\Services\FleetReliabilityService;
use App\Modules\MasterData\Models\Branch;
use App\Modules\SyopIntegration\Services\SyopSyncService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FleetController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->string('search')->trim();

        $query = Fleet::query()
            ->with('branch')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($search->isNotEmpty(), fn ($q) => $q->where(
                fn ($qq) => $qq->where('plate_number', 'like', "%{$search}%")
                    ->orWhere('brand', 'like', "%{$search}%")
                    ->orWhere('model', 'like', "%{$search}%")
            ));

        // Role bercabang (Driver, Mekanik, Kepala Pool, BM, Tim Logistik)
        // hanya melihat armada cabangnya sendiri — lihat User::isBranchScoped().
        // Role Head Office tetap bisa memfilter lintas-cabang lewat ?branch_id=.
        if ($request->user()->isBranchScoped()) {
            $query->where('branch_id', $request->user()->branch_id);
        } elseif ($request->filled('branch_id')) {
            $query->where('branch_id', $request->query('branch_id'));
        }

        $fleets = $query->orderBy('plate_number')->paginate($request->integer('per_page', 15));

        return FleetResource::collection($fleets);
    }

    /**
     * Ringkasan status operasional armada untuk widget Dashboard: ready
     * (siap pakai), breakdown (dilaporkan rusak, menunggu masuk workshop),
     * service (sedang dikerjakan mekanik di workshop), nonaktif. Diturunkan
     * dari Request bertipe 'perbaikan' yang belum selesai + WorkOrder terkait
     * — bukan kolom terpisah, supaya selalu konsisten dengan alur
     * pengajuan/approval yang sudah ada (Design Document Bagian 4.1).
     */
    public function statusSummary(Request $request)
    {
        $query = Fleet::query()
            ->with([
                'branch:id,code,name',
                'requests' => fn ($q) => $q->where('type', 'perbaikan')
                    ->where('status', '!=', 'rejected')
                    ->with('workOrder:id,request_id,status,approval_status'),
            ]);

        if ($request->user()->isBranchScoped()) {
            $query->where('branch_id', $request->user()->branch_id);
        } elseif ($request->filled('branch_id')) {
            $query->where('branch_id', $request->query('branch_id'));
        }

        $buckets = ['ready' => [], 'breakdown' => [], 'service' => [], 'nonaktif' => []];

        $query->orderBy('plate_number')->get()->each(function (Fleet $fleet) use (&$buckets) {
            $buckets[$this->operationalStatus($fleet)][] = [
                'id' => $fleet->id,
                'plate_number' => $fleet->plate_number,
                'branch' => $fleet->branch?->name,
            ];
        });

        return response()->json([
            'data' => $buckets,
            'counts' => array_map('count', $buckets),
        ]);
    }

    private function operationalStatus(Fleet $fleet): string
    {
        if ($fleet->status === 'nonaktif') {
            return 'nonaktif';
        }

        $workOrders = $fleet->requests->map->workOrder->filter();

        if ($workOrders->contains(fn ($wo) => $wo->status === 'on_progress')) {
            return 'service';
        }

        $hasOpenIssue = $fleet->requests->contains(
            fn ($req) => ! $req->workOrder
                || $req->workOrder->status !== 'finished'
                || $req->workOrder->approval_status !== 'completed'
        );

        return $hasOpenIssue ? 'breakdown' : 'ready';
    }

    /**
     * Dokumen legalitas armada yang mendekati jatuh tempo, untuk widget
     * Dashboard (FR-17). Dulunya frontend memanggil
     * GET /fleets/{id}/legal-docs SEKALI PER ARMADA (N+1) — tidak masalah
     * saat armada masih data demo (3 baris), tapi jadi request storm nyata
     * begitu sinkron SYOP mengisi >100 armada sungguhan (lihat
     * FleetController::syncFromSyop()) dan membanjiri PHP dev server
     * single-threaded. Satu query agregat di sini menggantikannya.
     */
    public function legalWarnings(Request $request)
    {
        $query = Fleet::query()->select('id', 'plate_number', 'branch_id');

        if ($request->user()->isBranchScoped()) {
            $query->where('branch_id', $request->user()->branch_id);
        } elseif ($request->filled('branch_id')) {
            $query->where('branch_id', $request->query('branch_id'));
        }

        $plateByFleetId = $query->pluck('plate_number', 'id');
        $days = $request->integer('days', 30);

        $docs = FleetLegalDoc::whereIn('fleet_id', $plateByFleetId->keys())
            ->whereNotNull('expiry_date')
            ->whereBetween('expiry_date', [now()->toDateString(), now()->addDays($days)->toDateString()])
            ->orderBy('expiry_date')
            ->get();

        return response()->json([
            'data' => $docs->map(fn (FleetLegalDoc $doc) => [
                'id' => $doc->id,
                'fleet_id' => $doc->fleet_id,
                'fleet_plate' => $plateByFleetId->get($doc->fleet_id),
                'doc_type' => $doc->doc_type,
                'expiry_date' => $doc->expiry_date,
            ])->values(),
        ]);
    }

    /**
     * Availability rate & MTBF/MTTR lintas-armada untuk widget Dashboard
     * (Pelaporan & Analitik — lihat FleetReliabilityService untuk definisi
     * tiap metrik). Agregat fleet-wide dihitung dari total uptime/downtime/
     * kegagalan SEMUA armada gabung (bukan rata-rata dari rata-rata tiap
     * armada), supaya armada dengan riwayat lebih panjang/lebih banyak
     * kejadian tidak diperlakukan sama bobotnya dengan armada yang baru
     * terdaftar kemarin. Daftar per-armada diurutkan dari availability rate
     * TERENDAH — permukaan otomatis unit paling tidak andal.
     */
    public function reliabilitySummary(Request $request, FleetReliabilityService $reliability)
    {
        $query = Fleet::query()->with(['branch:id,code,name', 'downtimes']);

        if ($request->user()->isBranchScoped()) {
            $query->where('branch_id', $request->user()->branch_id);
        } elseif ($request->filled('branch_id')) {
            $query->where('branch_id', $request->query('branch_id'));
        }

        $rows = $query->get()->map(function (Fleet $fleet) use ($reliability) {
            $metrics = $reliability->compute($fleet, $fleet->downtimes);

            return [
                'fleet_id' => $fleet->id,
                'plate_number' => $fleet->plate_number,
                'branch' => $fleet->branch?->name,
                ...$metrics,
            ];
        })->sortBy('availability_rate')->values();

        $totalUptime = $rows->sum('uptime_minutes');
        $totalPeriod = $rows->sum('period_minutes');
        $totalFailures = $rows->sum('failure_count');
        $totalCompletedRepairs = $rows->sum('completed_repair_count');
        $totalRepairMinutes = $rows->sum('total_repair_minutes');

        return response()->json([
            'data' => [
                'fleets' => $rows,
                'aggregate' => [
                    'availability_rate' => $totalPeriod > 0 ? round($totalUptime / $totalPeriod * 100, 2) : null,
                    'mtbf_minutes' => $totalFailures > 0 ? (int) round($totalUptime / $totalFailures) : null,
                    'mttr_minutes' => $totalCompletedRepairs > 0 ? (int) round($totalRepairMinutes / $totalCompletedRepairs) : null,
                ],
            ],
        ]);
    }

    /**
     * Tarik armada dari SYOP native (pro_master_transportir_mobil) — hanya
     * yang berasal dari transportir Pro Energi sendiri atau TDS (lihat
     * SyopNativeAdapter::getEligibleFleets()), disaring per cabang. Sama
     * seperti DriverController::syncFromSyop(): upsert berdasar
     * syop_fleet_id supaya aman dijalankan berulang. Field yang TIDAK ada
     * padanannya di SYOP (fleet_type, brand, model, dst — wajib diisi
     * manual oleh Tim Logistik) sengaja HANYA diisi placeholder saat armada
     * baru pertama kali dibuat, dan tidak pernah ditimpa lagi pada sync
     * berikutnya (beda dari plate_number/capacity yang memang milik SYOP).
     */
    public function syncFromSyop(Request $request, SyopSyncService $syncService)
    {
        if ($request->user()->isBranchScoped()) {
            $branchId = $request->user()->branch_id;
        } else {
            $request->validate(['branch_id' => ['required', 'exists:branches,id']]);
            $branchId = $request->integer('branch_id');
        }

        $branch = Branch::findOrFail($branchId);
        $result = $syncService->syncFleetsForBranch($branch);

        return response()->json(['data' => [...$result, 'branch' => $branch->code]]);
    }

    public function store(FleetRequest $request)
    {
        $data = $request->validated();
        if ($request->user()->isBranchScoped()) {
            $data['branch_id'] = $request->user()->branch_id;
        }

        // status/ownership/mutation_status diisi eksplisit (bukan mengandalkan
        // DEFAULT DB) agar response langsung mencerminkan nilai sebenarnya
        // tanpa perlu reload.
        $fleet = Fleet::create([
            ...$data,
            'status' => $data['status'] ?? 'aktif',
            'ownership' => $data['ownership'] ?? 'milik_sendiri',
            'mutation_status' => $data['mutation_status'] ?? 'tidak_ada',
        ]);

        return (new FleetResource($fleet))->response()->setStatusCode(201);
    }

    public function show(Request $request, Fleet $fleet)
    {
        if (! $request->user()->canAccessBranch($fleet->branch_id)) {
            abort(403, 'Anda hanya dapat melihat armada cabang Anda sendiri.');
        }

        return new FleetResource($fleet->load('branch'));
    }

    public function update(FleetRequest $request, Fleet $fleet)
    {
        if (! $request->user()->canAccessBranch($fleet->branch_id)) {
            abort(403, 'Anda hanya dapat mengelola armada cabang Anda sendiri.');
        }

        $data = $request->validated();
        if ($request->user()->isBranchScoped()) {
            $data['branch_id'] = $request->user()->branch_id;
        }

        $fleet->update($data);

        return new FleetResource($fleet);
    }

    /**
     * Foto armada — ditampilkan di kartu daftar & header halaman detail.
     * Endpoint terpisah dari update() biasa (yang mengirim JSON) supaya
     * upload multipart tidak perlu mengubah FleetRequest/alur form utama.
     * Menimpa (bukan menumpuk) foto lama — hanya satu foto per armada.
     */
    public function uploadPhoto(Request $request, Fleet $fleet)
    {
        if (! $request->user()->canAccessBranch($fleet->branch_id)) {
            abort(403, 'Anda hanya dapat mengelola armada cabang Anda sendiri.');
        }

        // 10MB — cukup longgar untuk foto langsung dari kamera HP (biasanya
        // 3-8MB), yang lewat storeFleetPhoto() di bawah tetap dikompres
        // jadi jauh lebih kecil sebelum disimpan.
        $request->validate([
            'photo' => ['required', 'image', 'max:10240'],
        ]);

        if ($fleet->photo_path) {
            Storage::disk('public')->delete($fleet->photo_path);
        }

        $fleet->update([
            'photo_path' => $this->storeFleetPhoto($request->file('photo')),
        ]);

        return new FleetResource($fleet->fresh('branch'));
    }

    /**
     * Foto asli dari kamera HP sering berukuran besar (resolusi 4000x3000+,
     * beberapa MB) dan sebagian punya tag EXIF orientation (potret/lanskap)
     * yang TIDAK otomatis dihormati GD — kalau disimpan mentah, foto bisa
     * tampil miring/kesamping di kartu daftar & detail meski `object-fit:
     * cover` di frontend sudah benar. Di sini foto diluruskan sesuai EXIF,
     * di-resize ke maksimum 1600px sisi terpanjang (foto yang sudah lebih
     * kecil tidak di-upscale), lalu dikonversi ke JPEG kualitas 82 supaya
     * ukuran file konsisten & ringan.
     */
    private function storeFleetPhoto(UploadedFile $file): string
    {
        $image = @imagecreatefromstring(file_get_contents($file->getRealPath()));

        if ($image === false) {
            // Format yang tidak dikenali GD (jarang terjadi karena validasi
            // 'image' di atas) — simpan apa adanya daripada gagal total.
            return $file->store('fleets', 'public');
        }

        $image = $this->applyExifOrientation($image, $file);

        $width = imagesx($image);
        $height = imagesy($image);
        $maxDimension = 1600;

        if (max($width, $height) > $maxDimension) {
            $ratio = $maxDimension / max($width, $height);
            $newWidth = (int) round($width * $ratio);
            $newHeight = (int) round($height * $ratio);

            $resized = imagecreatetruecolor($newWidth, $newHeight);
            imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            imagedestroy($image);
            $image = $resized;
        }

        ob_start();
        imagejpeg($image, null, 82);
        $contents = ob_get_clean();
        imagedestroy($image);

        $path = 'fleets/'.Str::uuid().'.jpg';
        Storage::disk('public')->put($path, $contents);

        return $path;
    }

    private function applyExifOrientation($image, UploadedFile $file)
    {
        // exif_read_data hanya mengenali JPEG — format lain (PNG/WebP) tidak
        // punya tag orientation sama sekali, panggil exif_read_data untuk
        // itu cuma menghasilkan warning tanpa manfaat.
        if ($file->getMimeType() !== 'image/jpeg') {
            return $image;
        }

        $exif = @exif_read_data($file->getRealPath());
        $orientation = $exif['Orientation'] ?? null;

        return match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };
    }

    public function destroy(Request $request, Fleet $fleet)
    {
        if (! $request->user()->canAccessBranch($fleet->branch_id)) {
            abort(403, 'Anda hanya dapat mengelola armada cabang Anda sendiri.');
        }

        $fleet->delete();

        return response()->noContent();
    }

    /**
     * Daftar armada yang sudah di-soft-delete — TIDAK ada UI/API sebelumnya
     * untuk melihat/memulihkan ini sama sekali (satu-satunya jalan dulu:
     * php artisan tinker langsung di server). Role bercabang hanya melihat
     * milik cabangnya sendiri (branch_id SEBELUM dihapus), sama seperti
     * index() biasa.
     */
    public function trashed(Request $request)
    {
        $query = Fleet::onlyTrashed()->with('branch');

        if ($request->user()->isBranchScoped()) {
            $query->where('branch_id', $request->user()->branch_id);
        } elseif ($request->filled('branch_id')) {
            $query->where('branch_id', $request->query('branch_id'));
        }

        $fleets = $query->orderByDesc('deleted_at')->paginate($request->integer('per_page', 15));

        return FleetResource::collection($fleets);
    }

    /**
     * Pulihkan armada yang sudah dihapus — SEKALIGUS boleh pindah cabang
     * dalam satu aksi (kasus nyata: armada dihapus manual karena salah
     * cabang, ternyata butuh dipulihkan ke cabang yang benar, bukan cabang
     * asalnya). Role bercabang dipaksa ke cabangnya sendiri (konsisten
     * dengan store()/update()) — cuma role global yang bebas memilih
     * cabang tujuan.
     */
    public function restore(Request $request, int $id)
    {
        $fleet = Fleet::onlyTrashed()->findOrFail($id);

        if (! $request->user()->canAccessBranch($fleet->branch_id)) {
            abort(403, 'Anda hanya dapat memulihkan armada cabang Anda sendiri.');
        }

        $data = $request->validate([
            'branch_id' => ['nullable', 'exists:branches,id'],
        ]);

        $fleet->restore();

        if ($request->user()->isBranchScoped()) {
            $fleet->update(['branch_id' => $request->user()->branch_id]);
        } elseif (! empty($data['branch_id'])) {
            $fleet->update(['branch_id' => $data['branch_id']]);
        }

        return new FleetResource($fleet->fresh('branch'));
    }
}
