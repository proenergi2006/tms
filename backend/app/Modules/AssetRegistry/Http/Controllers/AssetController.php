<?php

namespace App\Modules\AssetRegistry\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AssetRegistry\Http\Requests\AssetRequest;
use App\Modules\AssetRegistry\Http\Resources\AssetResource;
use App\Modules\AssetRegistry\Models\AssetRegistry;
use Illuminate\Http\Request;

class AssetController extends Controller
{
    public function index(Request $httpRequest)
    {
        $assets = AssetRegistry::query()
            ->with('picUser')
            ->when($httpRequest->filled('category'), fn ($q) => $q->where('category', $httpRequest->query('category')))
            ->when($httpRequest->filled('status'), fn ($q) => $q->where('status', $httpRequest->query('status')))
            ->orderBy('name')
            ->paginate($httpRequest->integer('per_page', 15));

        return AssetResource::collection($assets);
    }

    public function store(AssetRequest $request)
    {
        $asset = AssetRegistry::create([...$request->validated(), 'status' => $request->input('status', 'aktif')]);

        return (new AssetResource($asset))->response()->setStatusCode(201);
    }

    public function show(AssetRegistry $asset)
    {
        return new AssetResource($asset->load('picUser'));
    }

    public function update(AssetRequest $request, AssetRegistry $asset)
    {
        $asset->update($request->validated());

        return new AssetResource($asset);
    }

    public function destroy(AssetRegistry $asset)
    {
        $asset->delete();

        return response()->noContent();
    }
}
