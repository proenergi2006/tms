<?php

namespace App\Modules\MasterData\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MasterData\Http\Requests\JobTypeRequest;
use App\Modules\MasterData\Http\Resources\JobTypeResource;
use App\Modules\MasterData\Models\JobType;
use Illuminate\Http\Request;

class JobTypeController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->string('search')->trim();

        $jobTypes = JobType::query()
            ->when($search->isNotEmpty(), fn ($q) => $q->where(
                fn ($qq) => $qq->where('name', 'like', "%{$search}%")->orWhere('category', 'like', "%{$search}%")
            ))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 15));

        return JobTypeResource::collection($jobTypes);
    }

    public function store(JobTypeRequest $request)
    {
        $jobType = JobType::create($request->validated());

        return (new JobTypeResource($jobType))->response()->setStatusCode(201);
    }

    public function show(JobType $jobType)
    {
        return new JobTypeResource($jobType);
    }

    public function update(JobTypeRequest $request, JobType $jobType)
    {
        $jobType->update($request->validated());

        return new JobTypeResource($jobType);
    }

    public function destroy(JobType $jobType)
    {
        $jobType->delete();

        return response()->noContent();
    }
}
