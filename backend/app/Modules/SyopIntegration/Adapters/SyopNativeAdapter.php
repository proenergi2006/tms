<?php

namespace App\Modules\SyopIntegration\Adapters;

use App\Modules\SyopIntegration\Contracts\SyopDataProviderInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Implementasi SyopDataProviderInterface untuk SYOP native (PHP7 + MySQL).
 * Seluruh query di sini menggunakan koneksi 'syop' (read-only) yang
 * dikonfigurasi pada config/database.php, mengarah ke database "proenergi".
 *
 * Kolom & nama tabel di bawah sudah diverifikasi langsung terhadap skema
 * "proenergi" saat ini (bukan asumsi) — namun mapping bisnis PO/pendapatan
 * ke armada tertentu (getRealisasiPO/getPendapatan) dan data perjalanan
 * (getDataPerjalanan) BELUM final: skema "proenergi" tidak memiliki kolom
 * yang secara eksplisit menghubungkan PO dengan id_mobil. Perlu dikonfirmasi
 * dengan tim SYOP tabel penghubungnya (kandidat: pro_po_ds, pro_pod_tracking,
 * pro_driver_location) sebelum dipakai untuk perhitungan profitabilitas riil
 * (lihat Architecture Document Bagian 6.2 & PRD Bagian 12 — Risiko).
 */
class SyopNativeAdapter implements SyopDataProviderInterface
{
    protected function connection()
    {
        return DB::connection('syop');
    }

    public function getMasterArmada(): Collection
    {
        return $this->connection()
            ->table('pro_master_mobil')
            ->select([
                'id_mobil as syop_id',
                'nama_mobil as name',
                'plat_mobil as plate_number',
                'is_active',
                'id_cabang as syop_branch_id',
            ])
            ->get();
    }

    public function getMasterDriver(): Collection
    {
        return $this->connection()
            ->table('pro_master_transportir_sopir')
            ->select([
                'id_master as syop_id',
                'id_transportir as syop_transportir_id',
                'nama_sopir as name',
                'no_telepon as phone',
                'is_active',
            ])
            ->get();
    }

    /**
     * Pemetaan nama_transportir/lokasi_suplier (SYOP) -> kode cabang TMS.
     * Diverifikasi langsung terhadap data pro_master_transportir: baris
     * PE-* (satu per cabang, PE-SLW & PE-PALU sama-sama Sulawesi) + baris
     * TDS* (TDS polos untuk Jakarta/Surabaya-Gresik/Palembang, TDS-SMD
     * untuk Samarinda — dicocokkan lewat awalan "TDS" + lokasi, BUKAN nama
     * persis, supaya varian TDS-<cabang> lain yang mungkin muncul di masa
     * depan otomatis tertangani tanpa perlu ubah kode). Transportir lain
     * (vendor pihak ketiga) sengaja tidak dipetakan di sini —
     * getEligibleDrivers()/getEligibleFleets() sudah menyaringnya duluan.
     */
    private function mapBranchCode(string $transportirName, string $location): ?string
    {
        $isTds = str_starts_with($transportirName, 'TDS');

        return match (true) {
            $transportirName === 'PE-JKT' => 'JKT',
            $transportirName === 'PE-SBY' => 'SBY',
            $transportirName === 'PE-SMD' => 'SMD',
            $transportirName === 'PE-PLB' => 'PLG',
            $transportirName === 'PE-PTK' => 'PTK',
            $transportirName === 'PE-BJM' => 'BJM',
            in_array($transportirName, ['PE-SLW', 'PE-PALU'], true) => 'SUL',
            $isTds && str_contains($location, 'Jakarta') => 'JKT',
            $isTds && str_contains($location, 'Surabaya') => 'SBY',
            $isTds && str_contains($location, 'Palembang') => 'PLG',
            $isTds && str_contains($location, 'Samarinda') => 'SMD',
            default => null,
        };
    }

    /**
     * Filter transportir Pro Energi sendiri (PE-*) atau TDS (termasuk varian
     * seperti TDS-SMD — lihat mapBranchCode()), dipakai bersama oleh
     * getEligibleDrivers() dan getEligibleFleets().
     */
    private function whereEligibleTransportir($query): void
    {
        $query->where(function ($q) {
            $q->where('t.nama_transportir', 'like', 'PE-%')
                ->orWhere('t.nama_transportir', 'like', 'TDS%');
        });
    }

    public function getEligibleDrivers(): Collection
    {
        $query = $this->connection()
            ->table('pro_master_transportir_sopir as s')
            ->join('pro_master_transportir as t', 't.id_master', '=', 's.id_transportir')
            ->where('s.is_active', 1);
        $this->whereEligibleTransportir($query);

        return $query
            ->select([
                's.id_master as syop_id',
                's.nama_sopir as name',
                's.no_telepon as phone',
                't.nama_transportir as transportir_name',
                't.lokasi_suplier as location',
            ])
            ->get()
            ->map(function ($row) {
                $row->branch_code = $this->mapBranchCode($row->transportir_name, $row->location);

                return $row;
            })
            ->filter(fn ($row) => $row->branch_code !== null)
            ->values();
    }

    public function getEligibleFleets(): Collection
    {
        $query = $this->connection()
            ->table('pro_master_transportir_mobil as m')
            ->join('pro_master_transportir as t', 't.id_master', '=', 'm.id_transportir')
            ->where('m.is_active', 1);
        $this->whereEligibleTransportir($query);

        return $query
            ->select([
                'm.id_master as syop_id',
                'm.nomor_plat as plate_number',
                'm.max_kap as capacity',
                't.nama_transportir as transportir_name',
                't.lokasi_suplier as location',
            ])
            ->get()
            ->map(function ($row) {
                $row->branch_code = $this->mapBranchCode($row->transportir_name, $row->location);

                return $row;
            })
            ->filter(fn ($row) => $row->branch_code !== null)
            // Data SYOP tidak selalu bersih: satu plat nomor fisik kadang
            // terdaftar dobel di bawah id_master berbeda (mis. dipindah ke
            // transportir lain tanpa menonaktifkan baris lama — diverifikasi
            // langsung, puluhan kasus). Diambil satu baris per plat nomor
            // (syop_id terbesar = pendaftaran terbaru) supaya sync tidak
            // gagal karena bentrok unique constraint plate_number di TMS.
            ->sortByDesc('syop_id')
            ->unique('plate_number')
            ->values();
    }

    public function getRealisasiPO(array $filters = []): Collection
    {
        // TODO: mapping PO -> armada tertentu belum dikonfirmasi (lihat catatan class).
        $query = $this->connection()
            ->table('pro_po_customer')
            ->select([
                'id_poc as syop_id',
                'nomor_poc as po_number',
                'tanggal_poc as po_date',
                'harga_poc as price',
                'volume_poc as volume',
                'poc_approved as is_approved',
            ])
            ->where('poc_approved', 1);

        if (! empty($filters['date_from'])) {
            $query->where('tanggal_poc', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->where('tanggal_poc', '<=', $filters['date_to']);
        }

        return $query->get();
    }

    public function getPendapatan(array $filters = []): Collection
    {
        // Agregat sederhana dari realisasi PO. Belum dipecah per armada —
        // lihat TODO pada getRealisasiPO().
        return $this->getRealisasiPO($filters)
            ->groupBy(fn ($row) => substr((string) $row->po_date, 0, 7))
            ->map(fn ($rows, $period) => (object) [
                'period' => $period,
                'total_amount' => $rows->sum('price'),
                'po_count' => $rows->count(),
            ])
            ->values();
    }

    public function getDataPerjalanan(array $filters = []): Collection
    {
        // Belum diimplementasikan: tabel sumber data perjalanan/distribusi
        // per armada perlu dikonfirmasi dengan tim SYOP (lihat catatan class).
        return collect();
    }
}
