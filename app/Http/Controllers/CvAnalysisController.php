<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnalyzeCvRequest;
use App\Jobs\AnalyzeCv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CvAnalysisController extends Controller
{
    public function show(Request $request): Response
    {
        return Inertia::render('Seeker/CvAnalysis', [
            'profile' => $request->user()->jobSeekerProfile()->firstOrCreate(['user_id' => $request->user()->id]),
        ]);
    }

    public function store(AnalyzeCvRequest $request): RedirectResponse
    {
        $profile = $request->user()->jobSeekerProfile()->firstOrCreate(['user_id' => $request->user()->id]);
        $uploadedFile = $request->file('cv');
        $path = $uploadedFile->store('cvs', 'public');

        $profile->update([
            'cv_path' => $path,
            'cv_status' => 'processing',
        ]);

        AnalyzeCv::dispatchAfterResponse($profile->id, $path, $uploadedFile->getClientOriginalName());

        return back()->with('success', 'تم رفع السيرة وبدأ تحليلها. حدّث الصفحة بعد قليل لرؤية النتيجة.');
    }
}
