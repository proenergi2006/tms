<?php

namespace App\Modules\MasterData\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Manajemen Pengguna — PRD Bagian 4, permission `user.manage` (khusus Admin
 * Sistem, lihat RolePermissionSeeder). Ini jalur satu-satunya untuk membuat
 * akun TMS baru (email+password, lihat AuthController) & menetapkan
 * role/cabangnya — sebelum fitur ini akun hanya bisa dibuat lewat seeder.
 */
class UserController extends Controller
{
    public function index(Request $request)
    {
        $users = User::query()
            ->with(['role:id,name', 'branch:id,name'])
            ->when($request->string('search')->trim()->isNotEmpty(), fn ($query) => $query->where(
                fn ($q) => $q->where('name', 'like', '%'.$request->string('search')->trim().'%')
                    ->orWhere('email', 'like', '%'.$request->string('search')->trim().'%')
                    ->orWhere('username', 'like', '%'.$request->string('search')->trim().'%')
            ))
            ->when($request->filled('role_id'), fn ($query) => $query->where('role_id', $request->integer('role_id')))
            ->when($request->filled('branch_id'), fn ($query) => $query->where('branch_id', $request->integer('branch_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $users->map($this->present(...))]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request, isCreate: true);

        $user = User::create($data);

        return $this->show($user->load(['role:id,name', 'branch:id,name']));
    }

    public function show(User $user)
    {
        $user->loadMissing(['role:id,name', 'branch:id,name']);

        return response()->json(['data' => $this->present($user)]);
    }

    public function update(Request $request, User $user)
    {
        $data = $this->validateData($request, isCreate: false, user: $user);

        $user->update($data);

        return $this->show($user);
    }

    public function destroy(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            throw ValidationException::withMessages([
                'name' => 'Anda tidak dapat menghapus akun Anda sendiri.',
            ]);
        }

        // Soft delete (users pakai SoftDeletes supaya riwayat yang merujuk
        // user ini — requests.requested_by, approval_logs.approver_user_id,
        // keduanya restrictOnDelete — tetap utuh) tidak otomatis
        // membebaskan email/username/sso_id karena unique constraint
        // ketiganya berlaku untuk semua baris termasuk yang sudah
        // soft-deleted. Mangle di sini supaya nilai yang sama bisa dipakai
        // lagi untuk akun baru — username & sso_id dibatasi panjangnya
        // (VARCHAR 50/100), jadi mangle-nya TIDAK menyisipkan nilai asli
        // mentah-mentah (bisa kepanjangan & terpotong DB), cukup id +
        // random unik. sso_id SEMPAT KELUPAAN di sini (bug nyata: baris
        // lama yang masih pegang sso_id seed-* bikin DatabaseSeeder gagal
        // insert user baru dengan email sama karena bentrok unique sso_id,
        // walau email-nya sendiri sudah bebas) — sekarang ikut di-mangle.
        $user->update([
            'email' => "deleted-{$user->id}-{$user->email}",
            'username' => "deleted-{$user->id}-".Str::random(8),
            'sso_id' => $user->sso_id ? "deleted-{$user->id}-".Str::random(8) : null,
        ]);
        $user->delete();

        return response()->noContent();
    }

    private function validateData(Request $request, bool $isCreate, ?User $user = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            // alpha_dash bawaan Laravel TIDAK mengizinkan titik, padahal
            // pola username di app ini pakai titik (mis. "sa.jkt",
            // "kepala_pool.jkt", lihat DatabaseSeeder) — regex manual
            // sebagai gantinya: huruf/angka/titik/underscore/dash saja.
            'username' => [
                'required', 'string', 'max:50', 'regex:/^[a-zA-Z0-9._-]+$/',
                Rule::unique('users', 'username')->ignore($user?->id),
            ],
            'email' => [
                'required', 'email', 'max:150',
                Rule::unique('users', 'email')->ignore($user?->id),
            ],
            'password' => [$isCreate ? 'required' : 'nullable', 'string', 'min:8'],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'status' => ['required', Rule::in(['aktif', 'nonaktif'])],
        ]);

        // Password kosong saat update berarti "tidak diubah" — jangan
        // kirim key ini ke update() supaya kolom lama tidak tertimpa null.
        if (! $isCreate && blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        return $data;
    }

    private function present(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'role' => $user->role ? ['id' => $user->role->id, 'name' => $user->role->name] : null,
            'branch' => $user->branch ? ['id' => $user->branch->id, 'name' => $user->branch->name] : null,
            'status' => $user->status,
            'created_at' => $user->created_at,
        ];
    }
}
