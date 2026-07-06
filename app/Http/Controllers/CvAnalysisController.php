<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnalyzeCvRequest;
use App\Services\CvAnalysisService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class CvAnalysisController extends Controller
{
    public function show(Request $request): Response
    {
        return Inertia::render('Seeker/CvAnalysis', [
            'profile' => $request->user()->jobSeekerProfile()->firstOrCreate(['user_id' => $request->user()->id]),
        ]);
    }

    public function store(AnalyzeCvRequest $request, CvAnalysisService $service): RedirectResponse
    {
        $profile = $request->user()->jobSeekerProfile()->firstOrCreate(['user_id' => $request->user()->id]);
        $path = $request->file('cv')->store('cvs', 'public');

        try {
            $result = $service->analyze($request->file('cv'), $profile);
        } catch (Throwable) {
            $profile->update([
                'cv_path' => $path,
                'cv_status' => 'failed',
            ]);

            return back()->with('error', 'تعذر تحليل السيرة بالذكاء الاصطناعي. يرجى التأكد من إعداد مفتاح OpenAI أو المحاولة لاحقا.');
        }

        $profile->update([
            'cv_path' => $path,
            'cv_status' => 'analyzed',
            'profile_score' => $result['score'],
            'extracted_skills' => $result['extracted_skills'],
            'missing_skills' => $result['missing_skills'],
            'education_summary' => $result['education_summary'],
            'experience_summary' => $result['experience_summary'],
            'ai_recommendations' => [
                'strengths' => $result['strengths'],
                'recommendations' => $result['recommendations'],
            ],
        ]);

        return back()->with('success', 'تم تحليل السيرة الذاتية وحفظ النتائج.');
    }
}
