<?php

namespace App\Modules\MasterData\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MasterData\Http\Requests\CostTypeRequest;
use App\Modules\MasterData\Http\Resources\CostTypeResource;
use App\Modules\MasterData\Models\CostType;
use Illuminate\Http\Request;

class CostTypeController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->string('search')->trim();

        $costTypes = CostType::query()
            ->when($search->isNotEmpty(), fn ($q) => $q->where(
                fn ($qq) => $qq->where('name', 'like', "%{$search}%")->orWhere('category', 'like', "%{$search}%")
            ))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 15));

        return CostTypeResource::collection($costTypes);
    }

    public function store(CostTypeRequest $request)
    {
        $costType = CostType::create($request->validated());

        return (new CostTypeResource($costType))->response()->setStatusCode(201);
    }

    public function show(CostType $costType)
    {
        return new CostTypeResource($costType);
    }

    public function update(CostTypeRequest $request, CostType $costType)
    {
        $costType->update($request->validated());

        return new CostTypeResource($costType);
    }

    public function destroy(CostType $costType)
    {
        $costType->delete();

        return response()->noContent();
    }
}
