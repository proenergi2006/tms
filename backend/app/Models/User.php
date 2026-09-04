<?php

namespace App\Models;

use App\Modules\AssetRegistry\Models\AssetRegistry;
use App\Modules\MasterData\Models\Branch;
use App\Modules\MasterData\Models\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, SoftDeletes;

    /**
     * Role yang beroperasi lintas-cabang dari Head Office (Admin IT & GA,
     * Admin Sistem, Manajemen) — peran lain (SA, Fleet Operations, Kepala
     * Pool, Tim Logistik) melekat pada satu cabang tertentu dan hanya boleh
     * melihat/mengelola data cabangnya sendiri lewat canAccessBranch().
     *
     * Sejak setiap cabang punya Fleet Operations sendiri (region &
     * leader_operations DIHAPUS TOTAL — lihat migrasi drop_region_from_*),
     * fleet_operations TIDAK lagi di sini: sekarang branch-scoped persis
     * seperti sa/kepala_pool/tim_logistik.
     */
    private const GLOBAL_ROLES = ['admin_it_ga', 'admin_sistem', 'manajemen', 'logistik_ho'];

    /**
     * Autentikasi TMS: dua jalur berdampingan, masing-masing pakai
     * identifier beda (AuthController vs SsoController).
     * - Login manual (email+password): dicocokkan lewat `username`, bukan
     *   `email` — supaya orang yang perlu banyak akun (1 orang pegang
     *   Kepala Pool di beberapa cabang) tidak harus ketik email lengkap
     *   tiap akun, cukup username pendek (mis. "ridho.jkt").
     * - SSO dari SYOP (lihat SsoController): tetap dicocokkan lewat
     *   `email`, TIDAK berubah — payload SSO cuma kirim email, bukan
     *   username, dan email tetap unik per akun supaya SSO tidak ambigu
     *   kalau satu orang punya beberapa akun.
     */
    protected $fillable = [
        'name',
        'email',
        'username',
        'sso_id',
        'password',
        'role_id',
        'branch_id',
        'status',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(AssetRegistry::class, 'pic');
    }

    /**
     * RBAC per-endpoint — Architecture Document Bagian 7.2. Permission
     * granular dipetakan per role lewat role_permissions (lihat
     * RolePermissionSeeder), dicek oleh middleware `permission:<nama>`
     * (App\Http\Middleware\EnsurePermission).
     */
    public function hasPermission(string $permission): bool
    {
        if (! $this->relationLoaded('role') || ! $this->role?->relationLoaded('permissions')) {
            $this->loadMissing('role.permissions');
        }

        return $this->role?->permissions->contains('name', $permission) ?? false;
    }

    /**
     * Apakah user ini harus dibatasi hanya melihat/mengelola data cabangnya
     * sendiri (lihat GLOBAL_ROLES). User tanpa branch_id (mis. data lama)
     * dianggap tidak dibatasi — tidak ada cabang untuk dibatasi ke sana.
     */
    public function isBranchScoped(): bool
    {
        return $this->branch_id !== null && ! in_array($this->role?->name, self::GLOBAL_ROLES, true);
    }

    /**
     * Apakah user boleh mengakses data milik cabang $branchId. Role global
     * selalu boleh; $branchId null (cabang tidak diketahui/tidak berlaku,
     * mis. pengajuan restock tanpa armada spesifik dari requester tanpa
     * cabang) juga diloloskan agar tidak salah blokir.
     */
    public function canAccessBranch(?int $branchId): bool
    {
        return ! $this->isBranchScoped() || $branchId === null || $this->branch_id === $branchId;
    }
}
