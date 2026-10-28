<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreJobCategoryRequest;
use App\Models\JobCategory;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AdminJobCategoryController extends Controller
{
    public const LINKED_JOBS_MESSAGE = 'لا يمكن حذف هذه الفئة لأنها مرتبطة بوظائف. انقل الوظائف إلى فئة أخرى أولًا.';

    public function index(Request $request): Response
    {
        $this->ensureAdministrator($request);

        $categories = JobCategory::query()
            ->withCount('subcategories')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (JobCategory $category): array => [
                'id' => $category->id,
                'key' => $category->key,
                'label' => $category->name_ar,
                'label_en' => $category->name_en,
                'jobs_count' => $category->linkedJobsQuery()->count(),
                'subcategories_count' => $category->subcategories_count,
            ])
            ->values();

        return Inertia::render('Admin/JobCategories', [
            'categories' => $categories,
        ]);
    }

    public function store(StoreJobCategoryRequest $request): RedirectResponse
    {
        $name = $request->validated('name');

        try {
            DB::transaction(function () use ($name): void {
                JobCategory::query()->create([
                    'key' => $this->uniqueKey($name),
                    'name_ar' => $name,
                    'name_en' => null,
                    'sort_order' => ((int) JobCategory::query()->max('sort_order')) + 1,
                ]);
            });
        } catch (QueryException $exception) {
            if (JobCategory::query()->where('name_ar', $name)->exists()) {
                throw ValidationException::withMessages([
                    'name' => 'اسم الفئة مستخدم بالفعل.',
                ]);
            }

            throw $exception;
        }

        return redirect()->route('admin.job-categories.index')->with(
            'success',
            'تمت إضافة فئة الوظائف بنجاح.',
        );
    }

    public function destroy(Request $request, JobCategory $jobCategory): RedirectResponse
    {
        $this->ensureAdministrator($request);

        try {
            $linkedJobs = DB::transaction(function () use ($jobCategory): int {
                $category = JobCategory::query()
                    ->whereKey($jobCategory->id)
                    ->lockForUpdate()
                    ->firstOrFail();
                $linkedJobs = $category->linkedJobsQuery()->count();

                if ($linkedJobs === 0) {
                    $category->deleteOrFail();
                }

                return $linkedJobs;
            });
        } catch (QueryException $exception) {
            Log::warning('Job category deletion was blocked by the database.', [
                'job_category_id' => $jobCategory->id,
                'exception_class' => $exception::class,
            ]);

            return $this->linkedJobsResponse();
        }

        if ($linkedJobs > 0) {
            return $this->linkedJobsResponse($linkedJobs);
        }

        return redirect()->route('admin.job-categories.index')->with(
            'success',
            'تم حذف فئة الوظائف بنجاح.',
        );
    }

    private function ensureAdministrator(Request $request): void
    {
        abort_unless($request->user()?->role === 'admin', 403);
    }

    private function uniqueKey(string $name): string
    {
        $base = Str::slug($name);
        $base = $base !== '' ? Str::limit($base, 200, '') : 'category';

        return $base.'-'.Str::lower((string) Str::uuid());
    }

    private function linkedJobsResponse(?int $count = null): RedirectResponse
    {
        $message = self::LINKED_JOBS_MESSAGE;

        if ($count !== null) {
            $message .= " عدد الوظائف المرتبطة: {$count}.";
        }

        return redirect()->route('admin.job-categories.index')->with('error', $message);
    }
}
