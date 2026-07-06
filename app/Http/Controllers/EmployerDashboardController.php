<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmployerDashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $company = $request->user()->companyProfile()->with(['jobs.applications.user.jobSeekerProfile'])->firstOrCreate(
            ['user_id' => $request->user()->id],
            [
                'company_name' => $request->user()->name,
                'verification_status' => 'unverified',
            ]
        );
        $jobs = $company->jobs;
        $applications = $jobs->flatMap->applications->sortByDesc('created_at')->values();

        return Inertia::render('Employer/Dashboard', [
            'company' => $company,
            'jobs' => $jobs,
            'recentApplications' => $applications->take(8)->values(),
            'stats' => [
                'publishedJobs' => $jobs->where('status', 'published')->count(),
                'totalApplicants' => $applications->count(),
                'shortlisted' => $applications->where('status', 'shortlisted')->count(),
            ],
        ]);
    }
}
