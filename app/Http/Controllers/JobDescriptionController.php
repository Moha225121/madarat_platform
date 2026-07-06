<?php

namespace App\Http\Controllers;

use App\Http\Requests\GenerateJobDescriptionRequest;
use App\Services\JobDescriptionGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JobDescriptionController extends Controller
{
    public function store(GenerateJobDescriptionRequest $request, JobDescriptionGeneratorService $service): JsonResponse
    {
        $data = $request->validated();
        $skills = $data['required_skills'] ?? [];
        $data['required_skills'] = is_array($skills) ? $skills : collect(explode(',', (string) $skills))->map(fn ($item) => trim($item))->filter()->values()->all();

        return response()->json($service->generate($data));
    }
}
