<?php

namespace App\Http\Controllers;

use App\Models\Job;
use App\Models\JobSeekerProfile;
use App\Services\MatchingService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MatchingController extends Controller
{
    public function show(Job $job, Request $request, MatchingService $matching): Response
    {
        abort_unless($job->companyProfile->user_id === $request->user()->id, 403);
        $job->load('applications.user.jobSeekerProfile', 'applications.interviewInvitation');
        $candidates = JobSeekerProfile::with('user')->get()->map(fn ($profile) => [
            'profile' => $profile,
            'user' => $profile->user,
            'match' => $matching->match($job, $profile),
        ])->sortByDesc('match.score')->values();

        return Inertia::render('Employer/Matches', [
            'job' => $job,
            'applications' => $job->applications,
            'candidates' => $candidates,
        ]);
    }
}
