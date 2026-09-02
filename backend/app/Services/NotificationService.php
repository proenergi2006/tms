<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Pengiriman notifikasi in-app (FR-11, FR-17 — PRD Bagian 6; Architecture
 * Document Bagian 4.5). Dipakai oleh ApprovalWorkflowService/RequestController
 * (notifikasi approval tertunda, event-driven) dan
 * CheckFleetLegalExpiry (notifikasi legalitas jatuh tempo, terjadwal).
 */
class NotificationService
{
    /**
     * Kirim notifikasi ke seluruh user dengan role tertentu. Bila $branchId
     * diberikan, diutamakan user pada cabang tsb — SEMUA role approval
     * (fleet_operations, kepala_pool, dst) branch-scoped seragam sekarang,
     * lihat User::GLOBAL_ROLES. Bila tidak ada user yang cocok, fallback ke
     * seluruh user dengan role tersebut agar approval tidak terlewat karena
     * data cabang belum lengkap.
     */
    public function notifyRole(string $roleName, string $type, string $message, ?int $branchId = null): void
    {
        $this->usersForRole($roleName, $branchId)->each(
            fn (User $user) => $this->notifyUser($user, $type, $message)
        );
    }

    /**
     * Sama seperti notifyRole(), tapi melewati user yang sudah pernah
     * menerima notifikasi serupa (dicocokkan lewat $dedupNeedle) dalam
     * $withinDays terakhir — dipakai job terjadwal (FR-17) agar tidak
     * mengirim ulang setiap kali job berjalan selama masa berlaku masih sama.
     */
    public function notifyRoleOncePerWindow(
        string $roleName,
        string $type,
        string $message,
        string $dedupNeedle,
        int $withinDays,
        ?int $branchId = null
    ): int {
        $sent = 0;

        $this->usersForRole($roleName, $branchId)->each(function (User $user) use ($type, $message, $dedupNeedle, $withinDays, &$sent) {
            if ($this->alreadyNotifiedRecently($user, $type, $dedupNeedle, $withinDays)) {
                return;
            }
            $this->notifyUser($user, $type, $message);
            $sent++;
        });

        return $sent;
    }

    public function notifyUser(User $user, string $type, string $message): void
    {
        Notification::create([
            'user_id' => $user->id,
            'type' => $type,
            'message' => $message,
            'is_read' => false,
        ]);
    }

    /**
     * Idempotensi sederhana tanpa perlu kolom referensi entitas sumber pada
     * tabel notifications: cek apakah pesan (atau bagian yang identik) sudah
     * pernah dikirim ke user ini dalam N hari terakhir.
     */
    public function alreadyNotifiedRecently(User $user, string $type, string $needle, int $withinDays): bool
    {
        return Notification::where('user_id', $user->id)
            ->where('type', $type)
            ->where('message', 'like', "%{$needle}%")
            ->where('created_at', '>=', now()->subDays($withinDays))
            ->exists();
    }

    /**
     * @return Collection<int, User>
     */
    private function usersForRole(string $roleName, ?int $branchId): Collection
    {
        $query = User::query()->whereHas('role', fn ($q) => $q->where('name', $roleName));

        $users = $branchId !== null ? (clone $query)->where('branch_id', $branchId)->get() : collect();

        return $users->isEmpty() ? $query->get() : $users;
    }
}
