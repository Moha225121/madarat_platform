<?php

namespace App\Http\Controllers;

use App\Models\CompanyProfile;
use App\Models\Job;
use App\Models\TrainingCourse;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'seekers' => User::where('role', 'job_seeker')->count(),
                'companies' => CompanyProfile::count(),
                'jobs' => Job::count(),
                'pendingJobs' => Job::where('status', 'pending_review')->count(),
                'pendingCompanies' => CompanyProfile::where('verification_status', 'pending')->count(),
                'pendingCourses' => TrainingCourse::where('status', 'pending_review')->count(),
            ],
            'pendingJobs' => Job::with('companyProfile')->where('status', 'pending_review')->latest()->get(),
            'pendingCompanies' => CompanyProfile::with('user')->where('verification_status', 'pending')->latest('verification_requested_at')->get(),
            'pendingCourses' => TrainingCourse::with('provider')->where('status', 'pending_review')->latest('submitted_at')->get(),
            'growth' => [12, 18, 15, 26, 31, 36, 44],
        ]);
    }
}
