<?php

namespace App\Http\Controllers;

use App\Models\CvDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CvBuilderController extends Controller
{
    public function show(Request $request): Response
    {
        $document = $request->user()?->isRole('job_seeker')
            ? $request->user()->cvDocument()->first()
            : null;

        return Inertia::render('CvBuilder', ['savedCv' => $document]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'template' => ['required', 'string', 'max:80'],
            'resume_data' => ['required', 'array'],
            'resume_data.name' => ['nullable', 'string', 'max:150'],
            'resume_data.headline' => ['nullable', 'string', 'max:200'],
            'resume_data.email' => ['nullable', 'email', 'max:255'],
            'resume_data.phone' => ['nullable', 'string', 'max:50'],
            'resume_data.location' => ['nullable', 'string', 'max:150'],
            'resume_data.summary' => ['nullable', 'string', 'max:5000'],
            'resume_data.skills' => ['nullable', 'string', 'max:5000'],
            'resume_data.experience' => ['nullable', 'string', 'max:20000'],
            'resume_data.education' => ['nullable', 'string', 'max:10000'],
            'resume_data.languages' => ['nullable', 'string', 'max:5000'],
            'resume_data.photo' => ['nullable', 'string', 'max:5000000'],
            'resume_data.photoX' => ['nullable', 'string', 'max:3'],
            'resume_data.photoY' => ['nullable', 'string', 'max:3'],
            'resume_data.photoZoom' => ['nullable', 'string', 'max:3'],
            'colors' => ['nullable', 'array'],
            'colors.primary' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'colors.secondary' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $document = CvDocument::updateOrCreate(
            ['user_id' => $request->user()->id],
            $data,
        );

        return response()->json(['saved' => true, 'updated_at' => $document->updated_at?->toISOString()]);
    }
}
