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
        $vendors = Vendor::query()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->query('type')))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 15));

        return VendorResource::collection($vendors);
    }

    public function store(VendorRequest $request)
    {
        $vendor = Vendor::create([
            ...$request->validated(),
            'type' => $request->input('type', 'bengkel'),
            'status' => $request->input('status', 'aktif'),
        ]);

        return (new VendorResource($vendor))->response()->setStatusCode(201);
    }

    public function show(Vendor $vendor)
    {
        return new VendorResource($vendor);
    }

    public function update(VendorRequest $request, Vendor $vendor)
    {
        $vendor->update($request->validated());

        return new VendorResource($vendor);
    }

    public function destroy(Vendor $vendor)
    {
        $vendor->delete();

        return response()->noContent();
    }
}
