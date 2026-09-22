<?php

namespace App\Modules\MasterData\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MasterData\Http\Requests\BranchRequest;
use App\Modules\MasterData\Http\Resources\BranchResource;
use App\Modules\MasterData\Models\Branch;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->string('search')->trim();

        $branches = Branch::query()
            ->when($search->isNotEmpty(), fn ($q) => $q->where(
                fn ($qq) => $qq->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")
            ))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 15));

        return BranchResource::collection($branches);
    }

    public function store(BranchRequest $request)
    {
        $branch = Branch::create($request->validated());

        return (new BranchResource($branch))->response()->setStatusCode(201);
    }

    public function show(Branch $branch)
    {
        return new BranchResource($branch);
    }

    public function update(BranchRequest $request, Branch $branch)
    {
        $branch->update($request->validated());

        return new BranchResource($branch);
    }

    public function destroy(Branch $branch)
    {
        $branch->delete();

        return response()->noContent();
    }
}
