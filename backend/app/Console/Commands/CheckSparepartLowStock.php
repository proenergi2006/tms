<?php

namespace App\Console\Commands;

use App\Modules\MasterData\Models\Sparepart;
use App\Services\NotificationService;
use Illuminate\Console\Command;

/**
 * Notifikasi stok sparepart di bawah ambang minimum (min_stock) — permintaan
 * fitur eksplisit ("minimum stock alert", lihat catatan batch di memori
 * project). Dikirim ke Tim Logistik cabang gudang terkait (sparepart tidak
 * punya branch_id sendiri, diturunkan dari warehouse — sama seperti
 * SparepartController::guardBranch()). Dijadwalkan harian lewat
 * routes/console.php.
 *
 * Sejak field `status` (AMAN/ORDER !!/DEAD STOCK/NON AKTIF) ditambahkan,
 * command ini JUGA jadi satu-satunya penulis otomatis transisi
 * AMAN <-> ORDER !! (dua arah — termasuk memulihkan ke AMAN begitu stok
 * naik lagi), supaya kolom Status di Master Data tidak pernah basi
 * dibanding notifikasi yang sudah lebih dulu terkirim. DEAD STOCK/NON AKTIF
 * SENGAJA tidak pernah disentuh di sini — keduanya keputusan manual
 * (dead stock = tidak bergerak, bukan sekadar "kurang stok"; non aktif =
 * sengaja dihentikan) yang tidak bisa diturunkan dari angka stok semata.
 * `safety_stock` tidak dipakai sebagai ambang tambahan di sini — cuma ada
 * 4 nilai status, dan ambang reorder standar (Min Stock) yang menentukan
 * kapan harus "ORDER !!", bukan safety stock (itu buffer, bukan pemicu).
 */
class CheckSparepartLowStock extends Command
{
    protected $signature = 'spareparts:check-low-stock {--remind-every=3 : Jarak minimal (hari) antar notifikasi untuk sparepart yang sama}';

    protected $description = 'Sinkronkan status AMAN/ORDER !! sparepart berdasarkan stok, dan kirim notifikasi ke Tim Logistik untuk yang di bawah ambang minimum';

    public function handle(NotificationService $notifications): int
    {
        $remindEvery = (int) $this->option('remind-every');
        $totalLow = 0;
        $totalSent = 0;
        $totalRecovered = 0;

        Sparepart::query()
            ->with('warehouse')
            // DEAD STOCK/NON AKTIF dikecualikan total dari sinkronisasi
            // otomatis ini — lihat catatan class.
            ->whereNotIn('status', ['dead_stock', 'non_aktif'])
            ->get()
            ->each(function (Sparepart $sparepart) use ($notifications, $remindEvery, &$totalLow, &$totalSent, &$totalRecovered) {
                $isLow = $sparepart->stock_qty < $sparepart->min_stock;

                if (! $isLow) {
                    if ($sparepart->status !== 'aman') {
                        $sparepart->update(['status' => 'aman']);
                        $totalRecovered++;
                    }

                    return;
                }

                $totalLow++;

                if ($sparepart->status !== 'order') {
                    $sparepart->update(['status' => 'order']);
                }

                if (! $sparepart->warehouse) {
                    return;
                }

                $dedupNeedle = "stok {$sparepart->name} ({$sparepart->sku})";
                $message = "{$dedupNeedle} tersisa {$sparepart->stock_qty} {$sparepart->unit}, di bawah stok minimum ({$sparepart->min_stock} {$sparepart->unit}).";

                $totalSent += $notifications->notifyRoleOncePerWindow(
                    'tim_logistik',
                    'low_stock',
                    $message,
                    $dedupNeedle,
                    $remindEvery,
                    $sparepart->warehouse->branch_id
                );
            });

        $this->info("Sparepart di bawah stok minimum: {$totalLow}. Notifikasi terkirim: {$totalSent}. Status dipulihkan ke AMAN: {$totalRecovered}.");

        return self::SUCCESS;
    }
}
