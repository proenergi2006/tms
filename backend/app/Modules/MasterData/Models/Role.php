<?php

namespace App\Modules\MasterData\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    use HasFactory;

    const UPDATED_AT = null;

    protected $fillable = ['name'];

    /**
     * Role bawaan yang namanya dirujuk langsung sebagai string di berbagai
     * tempat pada kode (mis. ApprovalStep.role_name, User::GLOBAL_ROLES) —
     * mengganti nama atau menghapusnya akan merusak alur bisnis terkait,
     * bukan cuma masalah data. RbacController memblokir rename/delete untuk
     * role ini; permission-nya TETAP bisa diubah bebas lewat UI (itu justru
     * inti fitur RBAC management).
     *
     * Sejak restrukturisasi peran: driver, mekanik, bm, dan finance
     * dihapus total dari sistem. Service Advisor (sa) membuat pengajuan
     * SEKALIGUS Work Order dalam satu langkah (diagnosis, prioritas,
     * estimasi biaya, pelaksana internal/eksternal, No. TAR langsung
     * terisi di awal untuk perbaikan).
     *
     * Setiap cabang sekarang punya SA, Fleet Operations, dan Kepala Pool
     * sendiri (region/leader_operations DIHAPUS TOTAL — lihat migrasi
     * drop_region_from_*). Approval jadi rantai dua tahap yang SERAGAM per
     * cabang dan DINAMIS (data-driven, bukan hardcode): Fleet Operations
     * cabang verifikasi dulu (bisa reject, dan bisa mengedit pengajuan
     * selama giliran tahapnya — lihat RequestController::update()), baru
     * Kepala Pool cabang approval akhir — lihat tabel approval_steps &
     * ApprovalWorkflowService. Tim Logistik tetap per-cabang tapi tidak
     * terlibat approval maupun eksekusi WO (SA yang menjalankan WO start
     * s/d selesai, termasuk realisasi sparepart — lihat
     * WorkOrderController::realizeItems()).
     */
    public const SYSTEM_ROLES = [
        'sa', 'fleet_operations', 'kepala_pool', 'tim_logistik', 'logistik_ho',
        'admin_it_ga', 'admin_sistem', 'manajemen',
    ];

    public function isSystemRole(): bool
    {
        return in_array($this->name, self::SYSTEM_ROLES, true);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
