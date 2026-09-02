<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Modules\Approval\Services\ApprovalWorkflowService;
use App\Modules\AssetRegistry\Models\AssetRegistry;
use App\Modules\Fleet\Models\Fleet;
use App\Modules\Fleet\Models\FleetLegalDoc;
use App\Modules\Fleet\Models\FleetRevenue;
use App\Modules\Fleet\Models\FuelLog;
use App\Modules\Fleet\Services\FleetDowntimeService;
use App\Modules\Maintenance\Models\Request as RequestModel;
use App\Modules\Maintenance\Models\WorkOrder;
use App\Modules\Maintenance\Services\WorkOrderCompletionService;
use App\Modules\Maintenance\Services\WorkOrderItemService;
use App\Modules\MasterData\Models\Mechanic;
use App\Modules\MasterData\Models\Sparepart;
use App\Modules\MasterData\Models\Vendor;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Data sample untuk eksplorasi/demo — beberapa Pengajuan/Work Order pada
 * berbagai tahap (selesai + stok terealisasi, ditolak di tahap Fleet
 * Operations, ditolak di tahap Kepala Pool, sedang dikerjakan, menunggu
 * verifikasi Fleet Operations, sudah completed tapi belum dieksekusi), fuel
 * log, legalitas armada, pendapatan armada, dan asset registry.
 *
 * Dijalankan LEWAT service layer asli (ApprovalWorkflowService,
 * WorkOrderCompletionService, WorkOrderItemService, FleetDowntimeService,
 * dst) — bukan insert mentah — supaya seluruh efek samping (downtime,
 * notifikasi, audit log, tracking komponen, riwayat & biaya armada) konsisten
 * dengan yang terjadi lewat aplikasi sungguhan. Tidak dipanggil dari
 * DatabaseSeeder (supaya tidak mengotori baseline bersih yang dipakai untuk
 * testing fitur).
 *
 * Sejak setiap cabang punya SA, Fleet Operations, DAN Kepala Pool sendiri
 * (region/leader_operations dihapus total), rantai approval SERAGAM untuk
 * semua skenario di bawah — tidak ada lagi percabangan region/fallback
 * apa pun, approval selalu SA -> Fleet Operations -> Kepala Pool (lihat
 * ApprovalStep). Item sparepart yang diisi SA saat pengajuan dibuat kini
 * cuma PLAN/ESTIMASI (WorkOrderItemService::planItem(), tidak memotong
 * stok) — skenario yang perlu mendemonstrasikan stok BENAR-BENAR berkurang
 * secara eksplisit memanggil realisasi (menghapus item plan lalu memanggil
 * WorkOrderItemService::addItem() untuk tiap item final), sama seperti alur
 * WorkOrderController::realizeItems() di aplikasi sungguhan.
 */
class SeedSampleData extends Command
{
    protected $signature = 'tms:seed-sample-data';

    protected $description = 'Buat data sample (pengajuan, work order, approval, fuel log, legalitas, revenue, asset) untuk demo/eksplorasi.';

    public function __construct(
        private readonly ApprovalWorkflowService $workflow,
        private readonly WorkOrderCompletionService $completion,
        private readonly WorkOrderItemService $itemService,
        private readonly FleetDowntimeService $downtime,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (AssetRegistry::where('asset_code', 'SAMPLE-AST-001')->exists()) {
            $this->warn('Data sample sepertinya sudah pernah dibuat sebelumnya (ditemukan asset_code SAMPLE-AST-001). Dibatalkan supaya tidak dobel.');

            return self::FAILURE;
        }

        $fleetJkt1 = Fleet::where('plate_number', 'B 1234 XYZ')->firstOrFail();
        $fleetJkt2 = Fleet::where('plate_number', 'B 5678 ABC')->firstOrFail();
        $fleetSmd = Fleet::where('plate_number', 'KT 1122 DA')->firstOrFail();

        $mechanicBudi = Mechanic::where('name', 'Budi Santoso')->firstOrFail();
        $mechanicAgus = Mechanic::where('name', 'Agus Wijaya')->firstOrFail();
        $vendor = Vendor::firstOrFail();

        $sparepartOli = Sparepart::where('category', 'oli_pelumas')->firstOrFail();
        $sparepartBan = Sparepart::where('category', 'ban')->firstOrFail();

        $saJkt = $this->user('sa.jkt@tms.test');
        $saSmd = $this->user('sa.smd@tms.test');
        $saPtk = $this->user('sa.ptk@tms.test');
        $saPlg = $this->user('sa.plg@tms.test');

        $foJkt = $this->user('fleet_operations.jkt@tms.test');
        $foSmd = $this->user('fleet_operations.smd@tms.test');
        $foPtk = $this->user('fleet_operations.ptk@tms.test');

        $poolJkt = $this->user('kepala_pool.jkt@tms.test');
        $poolSmd = $this->user('kepala_pool.smd@tms.test');
        $poolPtk = $this->user('kepala_pool.ptk@tms.test');

        $this->info('1/6 Skenario Work Order — servis rutin selesai + realisasi sparepart (Jakarta, stok BENAR-BENAR berkurang di sini)...');
        $wo1 = $this->createRequest(
            type: 'perbaikan',
            fleet: $fleetJkt1,
            requestedBy: $saJkt,
            description: 'Servis rutin: ganti oli mesin dan cek kondisi umum kendaraan.',
            priority: 'low',
            maintenanceNature: 'preventive',
            diagnosis: 'Oli mesin sudah keruh dan volumenya di bawah batas minimum. Filter udara berdebu tapi masih layak pakai. Tidak ditemukan kebocoran oli/air radiator, kondisi umum baik.',
            estimatedDays: 1,
            executionType: 'internal',
            mechanic: $mechanicBudi,
            items: [
                ['sparepart_id' => $sparepartOli->id, 'description' => 'Oli Mesin 15W-40', 'qty' => 2, 'unit_cost' => 85000],
                ['sparepart_id' => null, 'description' => 'Jasa servis rutin', 'qty' => 1, 'unit_cost' => 100000],
            ],
        );
        $this->workflow->approve($wo1, $foJkt);
        $this->workflow->approve($wo1, $poolJkt);
        $this->finishWithRealization($wo1, [
            ['sparepart_id' => $sparepartOli->id, 'description' => 'Oli Mesin 15W-40', 'qty' => 2, 'unit_cost' => 85000],
            ['sparepart_id' => null, 'description' => 'Jasa servis rutin', 'qty' => 1, 'unit_cost' => 100000],
        ], daysAgo: 3);

        $this->info('2/6 Skenario Work Order — ditolak Fleet Operations (Jakarta, ganti ban)...');
        $wo2 = $this->createRequest(
            type: 'perbaikan',
            fleet: $fleetJkt2,
            requestedBy: $saJkt,
            description: 'Ganti ban depan kanan-kiri, kondisi sudah gundul dan berisiko pecah di jalan.',
            priority: 'high',
            maintenanceNature: 'corrective',
            diagnosis: 'Kedua ban depan sudah gundul (kedalaman tapak di bawah 1,6mm), terlihat retak rambut di dinding ban kanan — berisiko blowout kalau tetap dipaksakan jalan. Wajib diganti sepasang sebelum unit dioperasikan kembali.',
            estimatedDays: 1,
            executionType: 'internal',
            mechanic: $mechanicAgus,
            items: [
                ['sparepart_id' => $sparepartBan->id, 'description' => 'Ban Tronton 1000-20', 'qty' => 2, 'unit_cost' => 850000],
            ],
        );
        $this->workflow->reject($wo2, $foJkt, 'Estimasi biaya terlalu tinggi dibanding kondisi fisik ban pada foto laporan — minta SA cek ulang & ajukan kembali dengan estimasi wajar.');

        $this->info('3/6 Skenario Work Order — lolos Fleet Operations tapi ditolak Kepala Pool (Samarinda, vendor eksternal)...');
        $wo3 = $this->createRequest(
            type: 'perbaikan',
            fleet: $fleetSmd,
            requestedBy: $saSmd,
            description: 'Suara mesin kasar, minta dicek ke bengkel eksternal.',
            priority: 'medium',
            maintenanceNature: 'corrective',
            diagnosis: 'Suara ketukan kasar terdengar dari area timing chain saat idle, dicurigai komponen dalam mesin aus — perlu pembongkaran di bengkel eksternal untuk pemeriksaan lebih lanjut.',
            estimatedDays: 3,
            executionType: 'eksternal',
            vendor: $vendor,
            items: [
                ['sparepart_id' => null, 'description' => 'Estimasi jasa pengecekan bengkel eksternal', 'qty' => 1, 'unit_cost' => 1200000],
            ],
        );
        $this->workflow->approve($wo3, $foSmd);
        $this->workflow->reject($wo3, $poolSmd, 'Biaya servis eksternal terlalu tinggi dibanding tarif pasar — cek dulu ke bengkel internal.');

        $this->info('4/6 Skenario Work Order — lolos approval penuh, masih dikerjakan (Jakarta, AC kabin)...');
        $wo4 = $this->createRequest(
            type: 'perbaikan',
            fleet: $fleetJkt1,
            requestedBy: $saJkt,
            description: 'AC kabin tidak dingin, perlu pengecekan freon dan kompresor.',
            priority: 'low',
            maintenanceNature: 'corrective',
            diagnosis: 'Tekanan freon rendah, kemungkinan ada kebocoran di sambungan selang AC. Kompresor masih berfungsi normal saat dites. Sedang ditelusuri titik kebocoran sebelum isi ulang freon.',
            estimatedDays: 1,
            executionType: 'internal',
            mechanic: $mechanicBudi,
            items: [
                ['sparepart_id' => null, 'description' => 'Estimasi jasa pengecekan AC', 'qty' => 1, 'unit_cost' => 150000],
            ],
        );
        $this->workflow->approve($wo4, $foJkt);
        $this->workflow->approve($wo4, $poolJkt);
        $wo4->update(['status' => 'on_progress', 'started_at' => now()->subHours(6)]);
        $this->completion->maybeFinalize($wo4);

        $this->info('5/6 Skenario Pengajuan — baru dibuat, masih menunggu verifikasi Fleet Operations (Palembang, restock, tanpa armada spesifik)...');
        $this->createRequest(
            type: 'restock',
            fleet: null,
            requestedBy: $saPlg,
            description: 'Stok oli mesin di gudang cabang menipis, perlu restock dari pusat.',
            priority: 'medium',
        );

        $this->info('6/6 Skenario Pengajuan — lolos approval penuh (Pontianak, sparepart, belum dieksekusi)...');
        $wo6 = $this->createRequest(
            type: 'sparepart',
            fleet: null,
            requestedBy: $saPtk,
            description: 'Butuh kampas kopling untuk stok darurat, armada tujuan belum ditentukan.',
            priority: 'high',
        );
        $this->workflow->approve($wo6, $foPtk);
        $this->workflow->approve($wo6, $poolPtk);

        $this->info('Melengkapi fuel log, legalitas, pendapatan armada, dan asset registry...');
        $this->seedFuelLogs($fleetJkt1);
        $this->seedFuelLogs($fleetJkt2);
        $this->seedFuelLogs($fleetSmd, entries: 2);
        $this->seedLegalDocs($fleetJkt1, $fleetJkt2, $fleetSmd);
        $this->seedRevenues($fleetJkt1, $fleetJkt2, $fleetSmd);
        $this->seedAssets();

        $this->info('Selesai. Data sample berhasil dibuat.');

        return self::SUCCESS;
    }

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    /**
     * Meniru persis alur RequestController::store(): membuat Request + Work
     * Order pendamping dalam satu langkah, menetapkan tahap approval AWAL
     * lewat ApprovalWorkflowService::initializeApproval() (bukan hardcode),
     * dan — untuk type=perbaikan — langsung menetapkan estimasi lama
     * perbaikan (hari) serta pelaksana internal/eksternal. No. TAR dibuat
     * sistem (Request::generateTarNo()), bukan input. Item di $items HANYA
     * plan/estimasi (WorkOrderItemService::planItem(), TIDAK memotong stok)
     * persis seperti payload `items` pada endpoint pengajuan sungguhan.
     *
     * @param  array<int, array{sparepart_id: int|null, description: string, qty: float, unit_cost: float}>  $items
     */
    private function createRequest(
        string $type,
        ?Fleet $fleet,
        User $requestedBy,
        string $description,
        string $priority = 'medium',
        ?string $maintenanceNature = null,
        ?string $diagnosis = null,
        ?int $estimatedDays = null,
        ?string $executionType = null,
        ?Mechanic $mechanic = null,
        ?Vendor $vendor = null,
        array $items = [],
    ): WorkOrder {
        // Fleet dipakai ulang lintas skenario dalam satu proses artisan yang
        // sama (bukan request HTTP baru tiap kali seperti aplikasi
        // sungguhan) — refresh dulu supaya FleetDowntimeService::open() di
        // bawah melihat status TERKINI dari DB, bukan status basi dari
        // skenario sebelumnya.
        $fleet = $fleet?->fresh();

        $request = RequestModel::create([
            'request_no' => RequestModel::generateRequestNo(),
            'type' => $type,
            'maintenance_nature' => $maintenanceNature,
            'priority' => $priority,
            'fleet_id' => $fleet?->id,
            'tar_no' => $type === 'perbaikan' ? RequestModel::generateTarNo() : null,
            'diagnosis' => $diagnosis,
            'estimated_days' => $estimatedDays,
            'requested_by' => $requestedBy->id,
            'description' => $description,
            'status' => 'submitted',
        ]);

        $workOrder = WorkOrder::create([
            'wo_no' => WorkOrder::generateWoNo(),
            'request_id' => $request->id,
            'execution_type' => $executionType,
            'mechanic_id' => $mechanic?->id,
            'vendor_id' => $vendor?->id,
            'status' => 'waiting',
            'approval_status' => 'submitted',
        ]);

        $this->workflow->initializeApproval($workOrder);

        foreach ($items as $item) {
            $this->itemService->planItem($workOrder, $item);
        }

        if ($type === 'perbaikan' && $fleet) {
            $this->downtime->open($fleet, $workOrder);
        }

        return $workOrder;
    }

    /**
     * Realisasi sparepart (SATU-SATUNYA titik stok gudang benar-benar
     * berkurang untuk item asal pengajuan) diikuti penyelesaian WO secara
     * fisik — meniru persis urutan WorkOrderController::realizeItems() ->
     * updateStatus(status=finished) pada aplikasi sungguhan. Gerbang
     * "Verifikasi Fleet Operations" pasca-selesai sudah dihapus (redundan
     * sejak Fleet Operations masuk rantai approval di awal).
     *
     * @param  array<int, array{sparepart_id: int|null, description: string, qty: float, unit_cost: float}>  $realizedItems
     */
    private function finishWithRealization(WorkOrder $workOrder, array $realizedItems, int $daysAgo): void
    {
        $workOrder->items()->delete();
        foreach ($realizedItems as $item) {
            $this->itemService->addItem($workOrder, $item);
        }
        $workOrder->update(['items_realized_at' => now()->subDays($daysAgo)]);

        $workOrder->update([
            'status' => 'finished',
            'started_at' => now()->subDays($daysAgo + 1),
            'finished_at' => now()->subDays($daysAgo),
        ]);
        $this->completion->maybeFinalize($workOrder);
    }

    private function seedFuelLogs(Fleet $fleet, int $entries = 3): void
    {
        $odometer = 40000;
        for ($i = $entries; $i >= 1; $i--) {
            $odometer += rand(1800, 2600);
            FuelLog::create([
                'fleet_id' => $fleet->id,
                'log_date' => now()->subMonths($i)->startOfMonth()->addDays(4),
                'liters' => rand(180, 260),
                'cost' => rand(180, 260) * 12500,
                'odometer' => $odometer,
                'engine_hours' => null,
            ]);
        }
    }

    private function seedLegalDocs(Fleet ...$fleets): void
    {
        $plan = [
            ['doc_type' => 'STNK', 'expiry_date' => Carbon::now()->addDays(15), 'doc_number' => 'STNK-SAMPLE'],
            ['doc_type' => 'KIR', 'expiry_date' => Carbon::now()->subDays(5), 'doc_number' => 'KIR-SAMPLE'],
            ['doc_type' => 'ASURANSI', 'expiry_date' => Carbon::now()->addMonths(8), 'doc_number' => 'ASR-SAMPLE'],
        ];

        foreach ($fleets as $fleet) {
            foreach ($plan as $doc) {
                // Ganti (bukan tambah) dokumen dengan doc_type yang sama —
                // baseline seeder sudah membuat beberapa legal doc per
                // armada; menambah begitu saja akan menghasilkan STNK/KIR
                // dobel yang membingungkan pada widget Peringatan Legalitas.
                FleetLegalDoc::where('fleet_id', $fleet->id)->where('doc_type', $doc['doc_type'])->delete();

                FleetLegalDoc::create([
                    'fleet_id' => $fleet->id,
                    'doc_type' => $doc['doc_type'],
                    'doc_number' => $doc['doc_number'].'-'.$fleet->id,
                    'issued_date' => Carbon::parse($doc['expiry_date'])->subYear(),
                    'expiry_date' => $doc['expiry_date'],
                ]);
            }
        }
    }

    private function seedRevenues(Fleet ...$fleets): void
    {
        foreach ($fleets as $fleet) {
            foreach ([1, 0] as $monthsAgo) {
                FleetRevenue::create([
                    'fleet_id' => $fleet->id,
                    'period' => now()->subMonths($monthsAgo)->format('Y-m'),
                    'source_po_number' => 'PO-SAMPLE-'.$fleet->id.'-'.$monthsAgo,
                    'amount' => rand(18, 42) * 1_000_000,
                    'synced_at' => now(),
                ]);
            }
        }
    }

    private function seedAssets(): void
    {
        $adminItGa = $this->user('admin_it_ga@tms.test');

        $assets = [
            ['asset_code' => 'SAMPLE-AST-001', 'category' => 'IT', 'name' => 'Laptop Dell Latitude 5440', 'location' => 'Kantor Pusat Jakarta'],
            ['asset_code' => 'SAMPLE-AST-002', 'category' => 'IT', 'name' => 'Printer Epson L3250', 'location' => 'Kantor Pusat Jakarta'],
            ['asset_code' => 'SAMPLE-AST-003', 'category' => 'IT', 'name' => 'CCTV DVR 8 Channel', 'location' => 'Pool Jakarta'],
            ['asset_code' => 'SAMPLE-AST-004', 'category' => 'GA', 'name' => 'AC Split 1.5 PK', 'location' => 'Ruang Kepala Pool Jakarta'],
            ['asset_code' => 'SAMPLE-AST-005', 'category' => 'GA', 'name' => 'Genset 5000 Watt', 'location' => 'Pool Jakarta'],
        ];

        foreach ($assets as $asset) {
            AssetRegistry::create([
                ...$asset,
                'pic' => $adminItGa->id,
                'purchase_date' => now()->subMonths(rand(3, 24)),
                'status' => 'aktif',
            ]);
        }
    }
}
