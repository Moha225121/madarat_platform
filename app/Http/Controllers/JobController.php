<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreJobRequest;
use App\Models\CompanyProfile;
use App\Models\Job;
use App\Models\JobCategory;
use App\Models\JobSubcategory;
use App\Services\MatchingService;
use App\Support\JobCategoryCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class JobController extends Controller
{
    public function index(Request $request, MatchingService $matching, JobCategoryCatalog $catalog): Response
    {
        $filters = $this->indexFilters($request, $catalog);
        $baseJobs = Job::query()
            ->where('status', 'published')
            ->when($filters['search'], fn (Builder $query, string $search) => $query->where(fn (Builder $nested) => $nested
                ->where('title', 'like', "%{$search}%")
                ->orWhere('location', 'like', "%{$search}%")
                ->orWhereHas('companyProfile', fn (Builder $company) => $company->where('company_name', 'like', "%{$search}%"))))
            ->when($filters['location'], fn (Builder $query, string $value) => $query->where('location', $value))
            ->when($filters['job_type'], fn (Builder $query, string $value) => $query->where('job_type', $value))
            ->when($filters['experience_level'], fn (Builder $query, string $value) => $query->where('experience_level', $value));

        $categoryKeys = $catalog->categoryKeys();
        $categoryCounts = (clone $baseJobs)
            ->whereIn('category', $categoryKeys)
            ->selectRaw('category, COUNT(*) as aggregate')
            ->groupBy('category')
            ->pluck('aggregate', 'category')
            ->map(fn ($count): int => (int) $count)
            ->all();
        $subcategoryCounts = (clone $baseJobs)
            ->whereIn('category', $categoryKeys)
            ->whereIn('subcategory', $catalog->subcategoryKeys())
            ->selectRaw('category, subcategory, COUNT(*) as aggregate')
            ->groupBy('category', 'subcategory')
            ->get()
            ->reduce(function (array $counts, Job $row) use ($catalog): array {
                if ($catalog->hasSubcategory($row->category, $row->subcategory)) {
                    $counts[$row->subcategory] = (int) $row->aggregate;
                }

                return $counts;
            }, []);
        $totalCount = (clone $baseJobs)->count();
        $otherCount = (clone $baseJobs)
            ->where(fn (Builder $query) => $query
                ->whereNull('category')
                ->orWhereNotIn('category', $categoryKeys))
            ->count();

        $jobs = (clone $baseJobs)
            ->with('companyProfile')
            ->when($filters['category'], fn (Builder $query, string $category) => $query->where('category', $category))
            ->when($filters['subcategory'], fn (Builder $query, string $subcategory) => $query->where('subcategory', $subcategory))
            ->orderByDesc(
                CompanyProfile::selectRaw("CASE WHEN verification_status = 'verified' THEN 1 ELSE 0 END")
                    ->whereColumn('company_profiles.id', 'jobs.company_profile_id')
                    ->limit(1)
            )
            ->latest()
            ->paginate(12)
            ->withQueryString();

        $profile = $request->user()?->role === 'job_seeker' ? $request->user()->jobSeekerProfile : null;
        $jobs->through(fn (Job $job): array => [
            ...$this->jobPayload($job, $catalog),
            'match' => $profile ? $matching->match($job, $profile) : null,
        ]);

        return Inertia::render('Jobs/Index', [
            'jobs' => $jobs,
            'categories' => $catalog->forFrontend($categoryCounts, $subcategoryCounts),
            'counts' => [
                'total' => $totalCount,
                'other' => $otherCount,
            ],
            'filters' => $filters,
        ]);
    }

    public function show(
        Job $job,
        Request $request,
        MatchingService $matching,
        JobCategoryCatalog $catalog,
    ): Response {
        abort_if($job->status !== 'published' && $request->user()?->role !== 'admin', 404);
        $job->load('companyProfile');
        $profile = $request->user()?->role === 'job_seeker' ? $request->user()->jobSeekerProfile : null;

        return Inertia::render('Jobs/Show', [
            'job' => $this->jobPayload($job, $catalog),
            'match' => $profile ? $matching->match($job, $profile) : null,
            'alreadyApplied' => $request->user()?->applications()->where('job_id', $job->id)->exists() ?? false,
        ]);
    }

    public function create(Request $request, JobCategoryCatalog $catalog): Response
    {
        $company = $request->user()->companyProfile;

        return Inertia::render('Employer/JobForm', [
            'job' => null,
            'companyVerified' => $company?->isVerified() ?? false,
            'categories' => $catalog->forFrontend(),
        ]);
    }

    public function store(StoreJobRequest $request): RedirectResponse
    {
        $company = $request->user()->companyProfile()->firstOrCreate(
            ['user_id' => $request->user()->id],
            [
                'company_name' => $request->user()->name,
                'verification_status' => 'unverified',
            ]
        );

        $data = $this->normalizedJobData($request->validated());
        $data['company_profile_id'] = $company->id;
        $data['slug'] = $this->uniqueSlug($data['title']);
        $data['status'] = $this->resolvedStatus($data['status'], $company);

        try {
            DB::transaction(function () use ($data): void {
                $this->lockCurrentCategoryAssignment($data);
                Job::create($data);
            });
        } catch (QueryException $exception) {
            $this->throwIfCategoryAssignmentBecameStale($data);

            throw $exception;
        }

        return redirect()->route('employer.dashboard')->with('success', 'تم إرسال الوظيفة لمراجعة الإدارة. ستتاح المطابقة الذكية بعد الموافقة والنشر.');
    }

    public function edit(Job $job, Request $request, JobCategoryCatalog $catalog): Response
    {
        abort_unless($job->companyProfile->user_id === $request->user()->id, 403);

        return Inertia::render('Employer/JobForm', [
            'job' => $job,
            'companyVerified' => $job->companyProfile->isVerified(),
            'categories' => $catalog->forFrontend(),
        ]);
    }

    public function update(StoreJobRequest $request, Job $job): RedirectResponse
    {
        abort_unless($job->companyProfile->user_id === $request->user()->id, 403);
        $data = $this->normalizedJobData($request->validated());
        $data['status'] = $this->resolvedStatus($data['status'], $job->companyProfile);

        try {
            DB::transaction(function () use ($job, $data): void {
                $this->lockCurrentCategoryAssignment($data);
                $job->update($data);
            });
        } catch (QueryException $exception) {
            $this->throwIfCategoryAssignmentBecameStale($data);

            throw $exception;
        }

        return back()->with('success', 'تم تحديث الوظيفة.');
    }

    public function review(Job $job, Request $request): Response
    {
        abort_unless($job->status === 'pending_review', 404);

        $job->load('companyProfile.user');
        $request->session()->put("admin.reviewed_jobs.{$job->id}", true);

        return Inertia::render('Admin/JobReview', [
            'job' => $job,
        ]);
    }

    public function approve(Job $job, Request $request): RedirectResponse
    {
        $this->ensureJobWasReviewed($job, $request);
        $job->update(['status' => 'published']);
        $request->session()->forget("admin.reviewed_jobs.{$job->id}");

        return redirect()->route('admin.jobs.pending')->with('success', 'تمت الموافقة على الوظيفة ونشرها.');
    }

    public function reject(Job $job, Request $request): RedirectResponse
    {
        $this->ensureJobWasReviewed($job, $request);
        $job->update(['status' => 'closed']);
        $request->session()->forget("admin.reviewed_jobs.{$job->id}");

        return redirect()->route('admin.jobs.pending')->with('success', 'تم رفض الوظيفة وإغلاقها.');
    }

    private function ensureJobWasReviewed(Job $job, Request $request): void
    {
        abort_unless($job->status === 'pending_review', 422);
        abort_unless((bool) $request->session()->get("admin.reviewed_jobs.{$job->id}", false), 403, 'يجب عرض تفاصيل الوظيفة قبل اتخاذ القرار.');
    }

    private function normalizedJobData(array $data): array
    {
        foreach (['required_skills', 'responsibilities'] as $field) {
            $value = $data[$field] ?? [];
            $data[$field] = is_array($value)
                ? array_values(array_filter(array_map('trim', $value)))
                : collect(preg_split('/[\n,]+/', (string) $value))->map(fn ($item) => trim($item))->filter()->values()->all();
        }

        return $data;
    }

    /** @param array<string, mixed> $data */
    private function lockCurrentCategoryAssignment(array $data): void
    {
        $category = JobCategory::query()
            ->where('key', $data['category'])
            ->lockForUpdate()
            ->first();

        if ($category === null) {
            throw ValidationException::withMessages([
                'category' => 'الفئة الوظيفية المحددة لم تعد متاحة. حدّث الصفحة واختر فئة أخرى.',
            ]);
        }

        $subcategory = $data['subcategory'] ?? null;

        if ($subcategory !== null && $subcategory !== '' && ! JobSubcategory::query()
            ->where('category_key', $category->key)
            ->where('key', $subcategory)
            ->lockForUpdate()
            ->exists()) {
            throw ValidationException::withMessages([
                'subcategory' => 'الفئة الفرعية المحددة لم تعد متاحة. حدّث الصفحة واختر فئة أخرى.',
            ]);
        }
    }

    /** @param array<string, mixed> $data */
    private function throwIfCategoryAssignmentBecameStale(array $data): void
    {
        $categoryExists = JobCategory::query()
            ->where('key', $data['category'])
            ->exists();

        if (! $categoryExists) {
            throw ValidationException::withMessages([
                'category' => 'الفئة الوظيفية المحددة لم تعد متاحة. حدّث الصفحة واختر فئة أخرى.',
            ]);
        }

        $subcategory = $data['subcategory'] ?? null;

        if ($subcategory !== null && $subcategory !== '' && ! JobSubcategory::query()
            ->where('category_key', $data['category'])
            ->where('key', $subcategory)
            ->exists()) {
            throw ValidationException::withMessages([
                'subcategory' => 'الفئة الفرعية المحددة لم تعد متاحة. حدّث الصفحة واختر فئة أخرى.',
            ]);
        }
    }

    /**
     * @return array{search: ?string, location: ?string, job_type: ?string, experience_level: ?string, category: ?string, subcategory: ?string}
     */
    private function indexFilters(Request $request, JobCategoryCatalog $catalog): array
    {
        $category = $this->scalarQueryFilter($request, 'category');
        if (! $catalog->hasCategory($category)) {
            $category = null;
        }

        $subcategory = $this->scalarQueryFilter($request, 'subcategory');
        if (! $catalog->hasSubcategory($category, $subcategory)) {
            $subcategory = null;
        }

        return [
            'search' => $this->scalarQueryFilter($request, 'search'),
            'location' => $this->scalarQueryFilter($request, 'location'),
            'job_type' => $this->scalarQueryFilter($request, 'job_type'),
            'experience_level' => $this->scalarQueryFilter($request, 'experience_level'),
            'category' => $category,
            'subcategory' => $subcategory,
        ];
    }

    private function scalarQueryFilter(Request $request, string $key): ?string
    {
        $value = $request->query($key);

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /** @return array<string, mixed> */
    private function jobPayload(Job $job, JobCategoryCatalog $catalog): array
    {
        return [
            ...$job->toArray(),
            'company_profile' => $job->companyProfile,
            'category_label' => $catalog->categoryLabel($job->category),
            'category_label_en' => $catalog->categoryLabel($job->category, 'en'),
            'subcategory_label' => $catalog->subcategoryLabel($job->category, $job->subcategory),
            'subcategory_label_en' => $catalog->subcategoryLabel($job->category, $job->subcategory, 'en'),
            'is_other' => ! $catalog->hasCategory($job->category),
        ];
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: Str::random(8);
        $slug = $base;
        $i = 2;
        while (Job::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    private function resolvedStatus(string $requestedStatus, CompanyProfile $company): string
    {
        if ($requestedStatus === 'draft') {
            return 'draft';
        }

        return 'pending_review';
    }
}
