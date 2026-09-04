<?php

namespace App\Console\Commands;

use Closure;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Support\Facades\DB;

/**
 * Reset data operasional/master sebelum go-live production — membersihkan
 * data uji/seed yang menumpuk selama development & testing.
 *
 * TIDAK disentuh sama sekali: branches, users (SEMUA role termasuk
 * admin_sistem), roles/permissions/role_permissions, approval_steps,
 * notifications, audit_logs (jejak audit historis tetap utuh — tidak ada
 * FK yang mengharuskan dibersihkan).
 *
 * Urutan hapus WAJIB seperti ini — dicek langsung dari
 * restrictOnDelete()/cascadeOnDelete() di migration, bukan asumsi:
 * - work_orders harus duluan sebelum requests (work_orders.request_id
 *   restrictOnDelete ke requests).
 * - maintenance_history/operational_costs/fleet_revenues harus duluan
 *   sebelum fleets (ketiganya restrictOnDelete ke fleets).
 * - operational_costs harus duluan sebelum cost_types (restrictOnDelete).
 * - spareparts harus duluan sebelum warehouses (restrictOnDelete).
 * Tabel dengan cascadeOnDelete (work_order_items, approval_logs,
 * fleet_legal_docs, fuel_logs, fleet_downtimes, fleet_components) TIDAK
 * perlu dihapus manual — otomatis ikut terhapus MySQL saat parent-nya
 * dihapus. attachments bersifat polymorphic (tidak ada FK DB-level ke
 * request/work_order), jadi dibersihkan manual berdasar attachable_type.
 */
class ResetOperationalData extends Command
{
    use ConfirmableTrait;

    protected $signature = 'tms:reset-operational-data {--force : Jalankan tanpa konfirmasi interaktif}';

    protected $description = 'Hapus data pengajuan/WO, master data (kecuali cabang), armada, driver, dan asset registry — TIDAK menyentuh branches/users';

    public function handle(): int
    {
        if (! $this->confirmToProceed(
            'PERINGATAN: ini akan MENGHAPUS PERMANEN data pengajuan/WO, master data '
            .'(kecuali cabang), armada, driver, dan asset registry di database ini. '
            .'branches dan users TIDAK akan disentuh. Pastikan sudah backup database '
            .'sebelum lanjut.'
        )) {
            return self::FAILURE;
        }

        DB::transaction(function () {
            $this->components->info('1/4 — Pengajuan & Work Order');
            $this->deleteAndReport('attachments', fn ($q) => $q->whereIn('attachable_type', ['request', 'work_order', 'work_order_item']));
            $this->deleteAndReport('work_orders');
            $this->deleteAndReport('requests');

            $this->components->info('2/4 — Data yang terikat ke armada/jenis biaya (harus sebelum keduanya dihapus)');
            $this->deleteAndReport('maintenance_history');
            $this->deleteAndReport('operational_costs');
            $this->deleteAndReport('fleet_revenues');

            $this->components->info('3/4 — Master data (kecuali branches)');
            $this->deleteAndReport('spareparts');
            $this->deleteAndReport('drivers');
            $this->deleteAndReport('mechanics');
            $this->deleteAndReport('vendors');
            $this->deleteAndReport('warehouses');
            $this->deleteAndReport('fleets');
            $this->deleteAndReport('cost_types');
            $this->deleteAndReport('job_types');

            $this->components->info('4/4 — Asset Registry');
            $this->deleteAndReport('asset_registry');
        });

        $this->newLine();
        $this->components->info('Selesai. branches, users, roles/permissions, approval_steps, notifications, audit_logs TIDAK disentuh.');

        return self::SUCCESS;
    }

    private function deleteAndReport(string $table, ?Closure $scope = null): void
    {
        $query = DB::table($table);
        if ($scope) {
            $scope($query);
        }
        $count = $query->count();
        $query->delete();
        $this->line("  {$table}: {$count} baris dihapus");
    }
}
