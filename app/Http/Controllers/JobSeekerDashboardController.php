<?php

namespace App\Http\Controllers;

use App\Models\InterviewInvitation;
use App\Models\Job;
use App\Services\MatchingService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class JobSeekerDashboardController extends Controller
{
    public function __invoke(Request $request, MatchingService $matching): Response
    {
        $profile = $request->user()->jobSeekerProfile()->firstOrCreate(['user_id' => $request->user()->id]);
        $jobs = Job::with('companyProfile')->where('status', 'published')->latest()->take(6)->get();

        return Inertia::render('Seeker/Dashboard', [
            'profile' => $profile,
            'recommendedJobs' => $jobs->map(fn (Job $job) => [
                ...$job->toArray(),
                'company_profile' => $job->companyProfile,
                'match' => $matching->match($job, $profile),
            ]),
            'applicationCount' => $request->user()->applications()->count(),
            'interviewCount' => InterviewInvitation::whereHas('application', fn ($q) => $q->where('user_id', $request->user()->id))->count(),
        ]);
    }
}
