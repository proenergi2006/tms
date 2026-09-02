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
        return CostTypeResource::collection(CostType::orderBy('name')->paginate($request->integer('per_page', 15)));
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
