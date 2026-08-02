<?php

namespace App\Http\Controllers;

use App\Models\CompanyProfile;
use App\Models\Job;
use App\Models\TrainingCourse;
use App\Models\TrainingProviderProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $userFilters = $request->only(['q', 'role']);

        $users = User::query()
            ->when($userFilters['q'] ?? null, function (Builder $query, string $value): void {
                $query->where(function (Builder $inner) use ($value): void {
                    $inner
                        ->where('name', 'like', "%{$value}%")
                        ->orWhere('email', 'like', "%{$value}%")
                        ->orWhere('phone', 'like', "%{$value}%");
                });
            })
            ->when($userFilters['role'] ?? null, fn (Builder $query, string $role) => $query->where('role', $role))
            ->latest()
            ->paginate(15, ['id', 'name', 'email', 'phone', 'role', 'created_at'])
            ->withQueryString();

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'users' => User::count(),
                'admins' => User::where('role', 'admin')->count(),
                'seekers' => User::where('role', 'job_seeker')->count(),
                'employers' => User::where('role', 'employer')->count(),
                'trainingProviders' => User::where('role', 'training_provider')->count(),
                'companies' => CompanyProfile::count(),
                'trainers' => TrainingProviderProfile::count(),
                'jobs' => Job::count(),
                'pendingJobs' => Job::where('status', 'pending_review')->count(),
                'pendingCompanies' => CompanyProfile::where('verification_status', 'pending')->count(),
                'pendingCourses' => TrainingCourse::where('status', 'pending_review')->count(),
                'pendingTrainers' => TrainingProviderProfile::where('verification_status', 'pending')->count(),
            ],
            'pendingJobs' => Job::with('companyProfile')->where('status', 'pending_review')->latest()->get(),
            'pendingCompanies' => CompanyProfile::with('user')->where('verification_status', 'pending')->latest('verification_requested_at')->get(),
            'pendingCourses' => TrainingCourse::with('provider')->where('status', 'pending_review')->latest('submitted_at')->get(),
            'latestSeekers' => User::with('jobSeekerProfile')->where('role', 'job_seeker')->latest()->limit(5)->get(),
            'latestCompanies' => CompanyProfile::with('user')->latest()->limit(5)->get(),
            'latestTrainers' => TrainingProviderProfile::with('user')->latest()->limit(5)->get(),
            'users' => $users,
            'userFilters' => $userFilters,
            'roleOptions' => User::query()->whereNotNull('role')->distinct()->orderBy('role')->pluck('role'),
            'growth' => [12, 18, 15, 26, 31, 36, 44],
        ]);
    }
}
