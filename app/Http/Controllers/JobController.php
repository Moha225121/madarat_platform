<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreJobRequest;
use App\Models\CompanyProfile;
use App\Models\Job;
use App\Services\MatchingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class JobController extends Controller
{
    public function index(Request $request, MatchingService $matching): Response
    {
        $jobs = Job::with('companyProfile')
            ->where('status', 'published')
            ->when($request->search, fn ($q, $search) => $q->where(fn ($qq) => $qq
                ->where('title', 'like', "%{$search}%")
                ->orWhere('location', 'like', "%{$search}%")
                ->orWhereHas('companyProfile', fn ($c) => $c->where('company_name', 'like', "%{$search}%"))))
            ->when($request->location, fn ($q, $v) => $q->where('location', $v))
            ->when($request->job_type, fn ($q, $v) => $q->where('job_type', $v))
            ->when($request->experience_level, fn ($q, $v) => $q->where('experience_level', $v))
            ->orderByDesc(
                CompanyProfile::selectRaw("CASE WHEN verification_status = 'verified' THEN 1 ELSE 0 END")
                    ->whereColumn('company_profiles.id', 'jobs.company_profile_id')
                    ->limit(1)
            )
            ->latest()
            ->get();

        $profile = $request->user()?->role === 'job_seeker' ? $request->user()->jobSeekerProfile : null;

        return Inertia::render('Jobs/Index', [
            'jobs' => $jobs->map(fn (Job $job) => [
                ...$job->toArray(),
                'company_profile' => $job->companyProfile,
                'match' => $profile ? $matching->match($job, $profile) : null,
            ]),
            'filters' => $request->only(['search', 'location', 'job_type', 'experience_level']),
        ]);
    }

    public function show(Job $job, Request $request, MatchingService $matching): Response
    {
        abort_if($job->status !== 'published' && $request->user()?->role !== 'admin', 404);
        $job->load('companyProfile');
        $profile = $request->user()?->role === 'job_seeker' ? $request->user()->jobSeekerProfile : null;

        return Inertia::render('Jobs/Show', [
            'job' => $job,
            'match' => $profile ? $matching->match($job, $profile) : null,
            'alreadyApplied' => $request->user()?->applications()->where('job_id', $job->id)->exists() ?? false,
        ]);
    }

    public function create(): Response
    {
        $company = request()->user()->companyProfile;

        return Inertia::render('Employer/JobForm', [
            'job' => null,
            'companyVerified' => $company?->isVerified() ?? false,
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

        $job = Job::create($data);

        return redirect()->route('employer.dashboard')->with('success', 'تم إرسال الوظيفة لمراجعة الإدارة. ستتاح المطابقة الذكية بعد الموافقة والنشر.');
    }

    public function edit(Job $job, Request $request): Response
    {
        abort_unless($job->companyProfile->user_id === $request->user()->id, 403);

        return Inertia::render('Employer/JobForm', [
            'job' => $job,
            'companyVerified' => $job->companyProfile->isVerified(),
        ]);
    }

    public function update(StoreJobRequest $request, Job $job): RedirectResponse
    {
        abort_unless($job->companyProfile->user_id === $request->user()->id, 403);
        $data = $this->normalizedJobData($request->validated());
        $data['status'] = $this->resolvedStatus($data['status'], $job->companyProfile);

        $job->update($data);

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
