<?php

namespace App\Modules\MasterData\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MasterData\Http\Requests\VendorRequest;
use App\Modules\MasterData\Http\Resources\VendorResource;
use App\Modules\MasterData\Models\Vendor;
use Illuminate\Http\Request;

class VendorController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->string('search')->trim();

        $query = Vendor::query()
            ->with('branch')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->query('type')))
            ->when($search->isNotEmpty(), fn ($q) => $q->where(
                fn ($qq) => $qq->where('name', 'like', "%{$search}%")->orWhere('contact_person', 'like', "%{$search}%")
            ));

        if ($request->user()->isBranchScoped()) {
            // Vendor lama (branch_id null, dibuat sebelum kolom ini ada)
            // dianggap referensi bersama — tetap ikut tampil di semua cabang
            // sampai di-assign manual, supaya data production yang sudah ada
            // tidak mendadak hilang dari daftar begitu fitur ini aktif.
            $branchId = $request->user()->branch_id;
            $query->where(fn ($q) => $q->where('branch_id', $branchId)->orWhereNull('branch_id'));
        } elseif ($request->filled('branch_id')) {
            $query->where('branch_id', $request->query('branch_id'));
        }

        $vendors = $query->orderBy('name')->paginate($request->integer('per_page', 15));

        return VendorResource::collection($vendors);
    }

    public function store(VendorRequest $request)
    {
        $data = $request->validated();
        if ($request->user()->isBranchScoped()) {
            $data['branch_id'] = $request->user()->branch_id;
        }

        $vendor = Vendor::create([
            ...$data,
            'type' => $data['type'] ?? 'bengkel',
            'status' => $data['status'] ?? 'aktif',
        ]);

        return (new VendorResource($vendor))->response()->setStatusCode(201);
    }

    public function show(Vendor $vendor)
    {
        return new VendorResource($vendor->load('branch'));
    }

    public function update(VendorRequest $request, Vendor $vendor)
    {
        if (! $request->user()->canAccessBranch($vendor->branch_id)) {
            abort(403, 'Anda hanya dapat mengelola vendor cabang Anda sendiri.');
        }

        $data = $request->validated();
        if ($request->user()->isBranchScoped()) {
            $data['branch_id'] = $request->user()->branch_id;
        }

        $vendor->update($data);

        return new VendorResource($vendor);
    }

    public function destroy(Request $request, Vendor $vendor)
    {
        if (! $request->user()->canAccessBranch($vendor->branch_id)) {
            abort(403, 'Anda hanya dapat mengelola vendor cabang Anda sendiri.');
        }

        $vendor->delete();

        return response()->noContent();
    }
}
