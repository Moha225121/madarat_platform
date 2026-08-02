<?php

namespace App\Http\Controllers;

use App\Models\CompanyProfile;
use App\Models\CourseEnrollment;
use App\Models\CourseRecommendation;
use App\Models\CourseUserFeedback;
use App\Models\JobSeekerProfile;
use App\Models\TrainingProviderProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminDirectoryController extends Controller
{
    public function seekers(Request $request): Response
    {
        $filters = $request->only(['q', 'city', 'field', 'cv_status']);

        $seekers = User::query()
            ->where('role', 'job_seeker')
            ->with(['jobSeekerProfile'])
            ->withCount(['applications', 'courseEnrollments'])
            ->when($filters['q'] ?? null, function (Builder $query, string $value): void {
                $query->where(function (Builder $inner) use ($value): void {
                    $inner
                        ->where('name', 'like', "%{$value}%")
                        ->orWhere('email', 'like', "%{$value}%")
                        ->orWhereHas('jobSeekerProfile', function (Builder $profile) use ($value): void {
                            $profile
                                ->where('headline', 'like', "%{$value}%")
                                ->orWhere('field', 'like', "%{$value}%")
                                ->orWhere('city', 'like', "%{$value}%");
                        });
                });
            })
            ->when($filters['city'] ?? null, fn (Builder $query, string $value) => $query->whereHas('jobSeekerProfile', fn (Builder $profile) => $profile->where('city', $value)))
            ->when($filters['field'] ?? null, fn (Builder $query, string $value) => $query->whereHas('jobSeekerProfile', fn (Builder $profile) => $profile->where('field', $value)))
            ->when($filters['cv_status'] ?? null, fn (Builder $query, string $value) => $query->whereHas('jobSeekerProfile', fn (Builder $profile) => $profile->where('cv_status', $value)))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Admin/JobSeekers', [
            'seekers' => $seekers,
            'filters' => $filters,
            'stats' => [
                'total' => User::where('role', 'job_seeker')->count(),
                'withProfile' => JobSeekerProfile::count(),
                'cvUploaded' => JobSeekerProfile::whereNotNull('cv_path')->count(),
                'withApplications' => User::where('role', 'job_seeker')->has('applications')->count(),
            ],
            'filterOptions' => [
                'cities' => JobSeekerProfile::query()->whereNotNull('city')->distinct()->orderBy('city')->pluck('city'),
                'fields' => JobSeekerProfile::query()->whereNotNull('field')->distinct()->orderBy('field')->pluck('field'),
                'cvStatuses' => JobSeekerProfile::query()->whereNotNull('cv_status')->distinct()->orderBy('cv_status')->pluck('cv_status'),
            ],
        ]);
    }

    public function seeker(User $seeker): Response
    {
        abort_unless($seeker->role === 'job_seeker', 404);

        $seeker->load([
            'jobSeekerProfile',
            'applications.job.companyProfile',
            'applications.interviewInvitation',
            'courseEnrollments.course.provider',
        ]);

        return Inertia::render('Admin/JobSeekerDetails', [
            'seeker' => $seeker,
            'stats' => [
                'applications' => $seeker->applications->count(),
                'shortlisted' => $seeker->applications->where('status', 'shortlisted')->count(),
                'interviews' => $seeker->applications->filter(fn ($application) => $application->interviewInvitation !== null)->count(),
                'courses' => $seeker->courseEnrollments->count(),
                'savedCourses' => CourseUserFeedback::query()->where('user_id', $seeker->id)->where('saved', true)->count(),
                'recommendedCourses' => CourseRecommendation::query()->where('job_seeker_id', $seeker->id)->count(),
            ],
        ]);
    }

    public function companies(Request $request): Response
    {
        $filters = $request->only(['q', 'verification_status', 'industry']);

        $companies = CompanyProfile::query()
            ->with(['user'])
            ->withCount(['jobs'])
            ->withCount([
                'jobs as published_jobs_count' => fn (Builder $query) => $query->where('status', 'published'),
                'jobs as pending_jobs_count' => fn (Builder $query) => $query->where('status', 'pending_review'),
            ])
            ->when($filters['q'] ?? null, function (Builder $query, string $value): void {
                $query->where(function (Builder $inner) use ($value): void {
                    $inner
                        ->where('company_name', 'like', "%{$value}%")
                        ->orWhere('industry', 'like', "%{$value}%")
                        ->orWhere('headquarters', 'like', "%{$value}%")
                        ->orWhereHas('user', fn (Builder $user) => $user->where('name', 'like', "%{$value}%")->orWhere('email', 'like', "%{$value}%"));
                });
            })
            ->when($filters['verification_status'] ?? null, fn (Builder $query, string $value) => $query->where('verification_status', $value))
            ->when($filters['industry'] ?? null, fn (Builder $query, string $value) => $query->where('industry', $value))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Admin/Companies', [
            'companies' => $companies,
            'filters' => $filters,
            'stats' => [
                'total' => CompanyProfile::count(),
                'verified' => CompanyProfile::where('verification_status', 'verified')->count(),
                'pending' => CompanyProfile::where('verification_status', 'pending')->count(),
                'jobs' => CompanyProfile::withCount('jobs')->get()->sum('jobs_count'),
            ],
            'filterOptions' => [
                'industries' => CompanyProfile::query()->whereNotNull('industry')->distinct()->orderBy('industry')->pluck('industry'),
                'verificationStatuses' => CompanyProfile::query()->whereNotNull('verification_status')->distinct()->orderBy('verification_status')->pluck('verification_status'),
            ],
        ]);
    }

    public function company(CompanyProfile $company): Response
    {
        $company->load(['user']);

        $jobs = $company->jobs()
            ->withCount('applications')
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Admin/CompanyDetails', [
            'company' => $company,
            'jobs' => $jobs,
            'stats' => [
                'jobs' => $company->jobs()->count(),
                'published' => $company->jobs()->where('status', 'published')->count(),
                'pending' => $company->jobs()->where('status', 'pending_review')->count(),
                'applications' => $company->jobs()->withCount('applications')->get()->sum('applications_count'),
            ],
        ]);
    }

    public function trainers(Request $request): Response
    {
        $filters = $request->only(['q', 'verification_status', 'provider_type', 'city']);

        $trainers = TrainingProviderProfile::query()
            ->with(['user'])
            ->withCount(['courses'])
            ->withCount([
                'courses as published_courses_count' => fn (Builder $query) => $query->where('status', 'published'),
                'courses as pending_courses_count' => fn (Builder $query) => $query->where('status', 'pending_review'),
            ])
            ->when($filters['q'] ?? null, function (Builder $query, string $value): void {
                $query->where(function (Builder $inner) use ($value): void {
                    $inner
                        ->where('display_name', 'like', "%{$value}%")
                        ->orWhere('legal_name', 'like', "%{$value}%")
                        ->orWhere('email', 'like', "%{$value}%")
                        ->orWhere('city', 'like', "%{$value}%")
                        ->orWhereHas('user', fn (Builder $user) => $user->where('name', 'like', "%{$value}%")->orWhere('email', 'like', "%{$value}%"));
                });
            })
            ->when($filters['verification_status'] ?? null, fn (Builder $query, string $value) => $query->where('verification_status', $value))
            ->when($filters['provider_type'] ?? null, fn (Builder $query, string $value) => $query->where('provider_type', $value))
            ->when($filters['city'] ?? null, fn (Builder $query, string $value) => $query->where('city', $value))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Admin/Trainers', [
            'trainers' => $trainers,
            'filters' => $filters,
            'stats' => [
                'total' => TrainingProviderProfile::count(),
                'verified' => TrainingProviderProfile::where('verification_status', 'verified')->count(),
                'pending' => TrainingProviderProfile::where('verification_status', 'pending')->count(),
                'courses' => TrainingProviderProfile::withCount('courses')->get()->sum('courses_count'),
            ],
            'filterOptions' => [
                'providerTypes' => TrainingProviderProfile::query()->whereNotNull('provider_type')->distinct()->orderBy('provider_type')->pluck('provider_type'),
                'verificationStatuses' => TrainingProviderProfile::query()->whereNotNull('verification_status')->distinct()->orderBy('verification_status')->pluck('verification_status'),
                'cities' => TrainingProviderProfile::query()->whereNotNull('city')->distinct()->orderBy('city')->pluck('city'),
            ],
        ]);
    }

    public function trainer(TrainingProviderProfile $provider): Response
    {
        $provider->load(['user']);

        $courses = $provider->courses()
            ->withCount(['enrollments', 'feedback'])
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Admin/TrainerDetails', [
            'provider' => $provider,
            'courses' => $courses,
            'stats' => [
                'courses' => $provider->courses()->count(),
                'published' => $provider->courses()->where('status', 'published')->count(),
                'pending' => $provider->courses()->where('status', 'pending_review')->count(),
                'enrollments' => CourseEnrollment::query()->whereIn('course_id', $provider->courses()->pluck('id'))->count(),
            ],
        ]);
    }
}
