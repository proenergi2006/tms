<?php

namespace App\Modules\MasterData\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MasterData\Http\Requests\SparepartRequest;
use App\Modules\MasterData\Http\Resources\SparepartResource;
use App\Modules\MasterData\Models\Sparepart;
use App\Modules\MasterData\Models\Warehouse;
use Illuminate\Http\Request;

class SparepartController extends Controller
{
    /**
     * Sparepart tidak punya kolom branch_id sendiri, tapi stoknya melekat
     * pada satu gudang (warehouse_id) yang branch-nya sudah pasti — jadi
     * cabang sparepart diturunkan dari situ, sama seperti pengajuan
     * diturunkan dari armadanya (lihat RequestController).
     */
    private function guardBranch(Request $request, Sparepart $sparepart): void
    {
        $sparepart->loadMissing('warehouse');
        if (! $request->user()->canAccessBranch($sparepart->warehouse?->branch_id)) {
            abort(403, 'Anda hanya dapat mengakses sparepart gudang cabang Anda sendiri.');
        }
    }

    public function index(Request $request)
    {
        $query = Sparepart::query()
            ->with('warehouse')
            ->when($request->filled('warehouse_id'), fn ($q) => $q->where('warehouse_id', $request->query('warehouse_id')))
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->query('category')))
            ->when($request->boolean('below_minimum'), fn ($q) => $q->whereColumn('stock_qty', '<', 'min_stock'));

        if ($request->user()->isBranchScoped()) {
            $branchId = $request->user()->branch_id;
            $query->whereHas('warehouse', fn ($q) => $q->where('branch_id', $branchId));
        }

        $spareparts = $query->orderBy('name')->paginate($request->integer('per_page', 15));

        return SparepartResource::collection($spareparts);
    }

    public function store(SparepartRequest $request)
    {
        $warehouseBranchId = Warehouse::find($request->validated('warehouse_id'))?->branch_id;
        if (! $request->user()->canAccessBranch($warehouseBranchId)) {
            abort(403, 'Anda hanya dapat menambahkan sparepart untuk gudang cabang Anda sendiri.');
        }

        $sparepart = Sparepart::create([
            ...$request->validated(),
            'sku' => $this->generateSku(),
            'unit' => $request->input('unit', 'pcs'),
            'unit_cost' => $request->input('unit_cost', 0),
            'stock_qty' => $request->input('stock_qty', 0),
            'min_stock' => $request->input('min_stock', 0),
        ]);

        return (new SparepartResource($sparepart))->response()->setStatusCode(201);
    }

    /**
     * SKU wajib dibuat otomatis (bukan input manual) — format SP-00001,
     * berurutan berdasarkan jumlah sparepart yang pernah dibuat (termasuk
     * yang sudah dihapus/soft-deleted, supaya nomor tidak pernah dipakai
     * ulang). Diverifikasi unik dalam loop kecil untuk berjaga-jaga dari
     * race condition dua pembuatan bersamaan.
     */
    private function generateSku(): string
    {
        $next = Sparepart::withTrashed()->count() + 1;

        do {
            $candidate = 'SP-'.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
            $next++;
        } while (Sparepart::withTrashed()->where('sku', $candidate)->exists());

        return $candidate;
    }

    public function show(Request $request, Sparepart $sparepart)
    {
        $this->guardBranch($request, $sparepart);

        return new SparepartResource($sparepart->load('warehouse'));
    }

    public function update(SparepartRequest $request, Sparepart $sparepart)
    {
        $this->guardBranch($request, $sparepart);

        $warehouseBranchId = Warehouse::find($request->validated('warehouse_id'))?->branch_id;
        if (! $request->user()->canAccessBranch($warehouseBranchId)) {
            abort(403, 'Anda hanya dapat memindahkan sparepart ke gudang cabang Anda sendiri.');
        }

        $sparepart->update($request->validated());

        return new SparepartResource($sparepart);
    }

    public function destroy(Request $request, Sparepart $sparepart)
    {
        $this->guardBranch($request, $sparepart);

        $sparepart->delete();

        return response()->noContent();
    }
}
