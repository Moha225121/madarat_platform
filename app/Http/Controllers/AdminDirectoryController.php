<?php

namespace App\Http\Controllers;

use App\Models\CompanyProfile;
use App\Models\CourseEnrollment;
use App\Models\CourseRecommendation;
use App\Models\CourseUserFeedback;
use App\Models\JobSeekerProfile;
use App\Models\TrainingCourse;
use App\Models\TrainingProviderProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Throwable;

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

    public function destroySeeker(User $seeker): RedirectResponse
    {
        abort_unless($seeker->role === 'job_seeker', 404);

        $seeker->delete();

        return redirect()->route('admin.seekers.index')->with('success', 'تم حذف الباحث عن عمل بنجاح.');
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

    public function destroyCompany(Request $request, CompanyProfile $company): RedirectResponse
    {
        $employer = $company->user;

        abort_unless(
            $employer !== null
                && $employer->role === 'employer'
                && $company->user_id === $employer->id
                && ! $employer->is($request->user()),
            404,
        );

        $filePaths = $this->safePublicFilePaths(
            [$company->logo_path],
            ['company-logos'],
        );
        $filePaths = collect($filePaths)
            ->reject(fn (string $path): bool => $this->publicFilePathIsReferencedElsewhere(
                $path,
                companyId: $company->id,
            ))
            ->values()
            ->all();

        try {
            DB::transaction(function () use ($company, $employer): void {
                $jobIds = $company->jobs()->pluck('id')->all();

                if ($jobIds !== []) {
                    $recommendationIds = CourseRecommendation::query()
                        ->whereNotNull('target_job_ids')
                        ->get(['id', 'target_job_ids'])
                        ->filter(fn (CourseRecommendation $recommendation): bool => collect($recommendation->target_job_ids ?? [])
                            ->intersect($jobIds)
                            ->isNotEmpty())
                        ->pluck('id');

                    CourseRecommendation::query()->whereIn('id', $recommendationIds->all())->delete();
                }

                $this->deleteUserAccount($employer);
            });
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('admin.companies.index')->with(
                'error',
                'تعذر حذف حساب صاحب العمل. يرجى المحاولة مرة أخرى.',
            );
        }

        try {
            $this->deletePublicFiles($filePaths);
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('admin.companies.index')->with(
                'error',
                'تم حذف حساب صاحب العمل، لكن تعذر حذف بعض الملفات المرتبطة به. يرجى مراجعة السجلات.',
            );
        }

        return redirect()->route('admin.companies.index')->with(
            'success',
            'تم حذف حساب صاحب العمل وجميع بياناته المرتبطة بنجاح.',
        );
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

    public function destroyTrainer(Request $request, TrainingProviderProfile $provider): RedirectResponse
    {
        $trainingProvider = $provider->user;

        abort_unless(
            $trainingProvider !== null
                && $trainingProvider->role === 'training_provider'
                && $provider->user_id === $trainingProvider->id
                && ! $trainingProvider->is($request->user()),
            404,
        );

        $providerFilePaths = $this->safePublicFilePaths(
            [
                $provider->logo_path,
                $provider->profile_image_path,
            ],
            ['training-providers'],
        );
        $courseFilePaths = $this->safePublicFilePaths(
            $provider->courses()
                ->whereNotNull('cover_image_path')
                ->pluck('cover_image_path')
                ->all(),
            ['training-courses'],
        );
        $filePaths = collect([...$providerFilePaths, ...$courseFilePaths])
            ->unique()
            ->reject(fn (string $path): bool => $this->publicFilePathIsReferencedElsewhere(
                $path,
                providerId: $provider->id,
            ))
            ->values()
            ->all();

        try {
            DB::transaction(fn () => $this->deleteUserAccount($trainingProvider));
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('admin.trainers.index')->with(
                'error',
                'تعذر حذف حساب مزود التدريب. يرجى المحاولة مرة أخرى.',
            );
        }

        try {
            $this->deletePublicFiles($filePaths);
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('admin.trainers.index')->with(
                'error',
                'تم حذف حساب مزود التدريب، لكن تعذر حذف بعض الملفات المرتبطة به. يرجى مراجعة السجلات.',
            );
        }

        return redirect()->route('admin.trainers.index')->with(
            'success',
            'تم حذف حساب مزود التدريب وجميع بياناته المرتبطة بنجاح.',
        );
    }

    /**
     * Keep deletion scoped to files created by this application on the public disk.
     *
     * @param  array<int, mixed>  $paths
     * @param  array<int, string>  $allowedDirectories
     * @return array<int, string>
     */
    private function safePublicFilePaths(array $paths, array $allowedDirectories): array
    {
        return collect($paths)
            ->filter(fn (mixed $path): bool => is_string($path) && filled($path))
            ->filter(function (string $path) use ($allowedDirectories): bool {
                if ($path !== trim($path) || str_contains($path, "\0") || str_contains($path, '\\')) {
                    return false;
                }

                $segments = explode('/', $path);

                return count($segments) > 1
                    && in_array($segments[0], $allowedDirectories, true)
                    && collect($segments)->every(fn (string $segment): bool => $segment !== '' && $segment !== '.' && $segment !== '..');
            })
            ->unique()
            ->values()
            ->all();
    }

    private function publicFilePathIsReferencedElsewhere(
        string $path,
        ?int $companyId = null,
        ?int $providerId = null,
    ): bool {
        $companies = CompanyProfile::query()->where('logo_path', $path);
        if ($companyId !== null) {
            $companies->where('id', '!=', $companyId);
        }

        $providers = TrainingProviderProfile::query()
            ->where(fn (Builder $query) => $query
                ->where('logo_path', $path)
                ->orWhere('profile_image_path', $path));
        if ($providerId !== null) {
            $providers->where('id', '!=', $providerId);
        }

        $courses = TrainingCourse::query()->where('cover_image_path', $path);
        if ($providerId !== null) {
            $courses->where('training_provider_id', '!=', $providerId);
        }

        return $companies->exists() || $providers->exists() || $courses->exists();
    }

    /** @param array<int, string> $paths */
    private function deletePublicFiles(array $paths): void
    {
        if ($paths !== [] && ! Storage::disk('public')->delete($paths)) {
            throw new RuntimeException('One or more account-owned files could not be deleted.');
        }
    }

    private function deleteUserAccount(User $user): void
    {
        DB::table('assistant_messages')->where('user_id', $user->id)->delete();
        DB::table('sessions')->where('user_id', $user->id)->delete();
        DB::table('password_reset_tokens')->where('email', $user->email)->delete();
        $user->delete();
    }
}
