<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreJobSeekerProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class JobSeekerProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('Seeker/Profile', [
            'profile' => $request->user()->jobSeekerProfile()->firstOrCreate(['user_id' => $request->user()->id]),
        ]);
    }

    public function store(StoreJobSeekerProfileRequest $request): RedirectResponse
    {
        $request->user()->jobSeekerProfile()->updateOrCreate(['user_id' => $request->user()->id], $request->validated());

        return back()->with('success', 'تم حفظ الملف المهني بنجاح.');
    }
}
