<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\CompanyProfile;
use App\Models\Job;
use App\Models\JobCategory;
use App\Models\JobSubcategory;
use App\Models\User;
use Database\Seeders\JobCategoryBackfillSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminJobCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_exact_administrator_can_view_database_categories_and_all_status_job_counts(): void
    {
        $admin = $this->user('admin');
        $company = $this->company();
        $this->job($company, ['status' => 'published']);
        $this->job($company, ['title' => 'مسودة مالية', 'status' => 'draft', 'subcategory' => null]);

        $this->actingAs($admin)
            ->get(route('admin.job-categories.index', absolute: false))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/JobCategories')
                ->has('categories', 8)
                ->where('categories', function ($categories): bool {
                    $finance = collect($categories)->firstWhere('key', 'finance');

                    return $finance !== null
                        && $finance['label'] === 'المالية'
                        && $finance['label_en'] === 'Finance'
                        && $finance['jobs_count'] === 2
                        && $finance['subcategories_count'] === 4;
                }));
    }

    public function test_guests_and_every_non_admin_role_cannot_manage_categories(): void
    {
        $category = $this->category('guest-protected', 'فئة محمية من الضيف');
        $index = route('admin.job-categories.index', absolute: false);
        $store = route('admin.job-categories.store', absolute: false);
        $destroy = route('admin.job-categories.destroy', $category, absolute: false);

        $this->get($index)->assertRedirect('/login');
        $this->post($store, ['name' => 'فئة ضيف'])->assertRedirect('/login');
        $this->delete($destroy)->assertRedirect('/login');

        foreach (['job_seeker', 'employer', 'training_provider', 'administrator'] as $role) {
            $actor = $this->user($role);
            $this->actingAs($actor)->get($index)->assertForbidden();
            $this->actingAs($actor)->post($store, ['name' => "فئة {$role}"])->assertForbidden();
            $this->actingAs($actor)->delete($destroy)->assertForbidden();
        }

        $this->assertDatabaseHas('job_categories', ['id' => $category->id]);
        $this->assertDatabaseMissing('job_categories', ['name_ar' => 'فئة ضيف']);
    }

    public function test_administrator_adds_a_trimmed_unique_category_and_invalid_names_are_rejected(): void
    {
        $admin = $this->user('admin');
        $store = route('admin.job-categories.store', absolute: false);

        $this->actingAs($admin)
            ->post($store, ['name' => '  التقنية والبرمجة  '])
            ->assertRedirect(route('admin.job-categories.index', absolute: false));

        $created = JobCategory::query()->where('name_ar', 'التقنية والبرمجة')->firstOrFail();
        $this->assertNotSame('', $created->key);
        $this->assertDatabaseCount('job_categories', 9);

        $this->actingAs($admin)
            ->post($store, ['name' => ' التقنية والبرمجة '])
            ->assertSessionHasErrors('name');
        $this->actingAs($admin)
            ->post($store, ['name' => '   '])
            ->assertSessionHasErrors('name');

        $this->assertDatabaseCount('job_categories', 9);
    }

    public function test_new_category_immediately_flows_through_employer_create_edit_and_public_jobs_pages(): void
    {
        $admin = $this->user('admin');
        [$employer] = $this->employerAndCompany();

        $this->actingAs($admin)->post(
            route('admin.job-categories.store', absolute: false),
            ['name' => 'التقنية والبرمجة'],
        )->assertRedirect();

        $category = JobCategory::query()->where('name_ar', 'التقنية والبرمجة')->firstOrFail();

        $this->actingAs($employer)
            ->get(route('employer.jobs.create', absolute: false))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('categories', fn ($categories): bool => collect($categories)->contains(fn ($item): bool => $item['key'] === $category->key
                    && $item['label'] === 'التقنية والبرمجة'
                    && $item['subcategories'] === [])));

        $this->actingAs($employer)
            ->post(route('employer.jobs.store', absolute: false), $this->jobPayload([
                'title' => 'محاسب اختار فئة تقنية صراحة',
                'category' => $category->key,
                'subcategory' => null,
            ]))
            ->assertRedirect();

        $job = Job::query()->where('title', 'محاسب اختار فئة تقنية صراحة')->firstOrFail();
        $this->assertSame($category->key, $job->category);
        $this->assertNull($job->subcategory);

        $this->actingAs($employer)
            ->get(route('employer.jobs.edit', $job, absolute: false))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('job.category', $category->key)
                ->where('job.subcategory', null));

        $job->update(['status' => 'published']);

        $this->get(route('jobs.index', ['category' => $category->key], absolute: false))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('jobs.total', 1)
                ->where('jobs.data.0.id', $job->id)
                ->where('categories', fn ($categories): bool => collect($categories)->contains(fn ($item): bool => $item['key'] === $category->key
                    && $item['count'] === 1)));
    }

    public function test_stale_employer_form_cannot_submit_a_deleted_category(): void
    {
        $admin = $this->user('admin');
        [$employer, $company] = $this->employerAndCompany();
        $category = $this->category('stale-category', 'فئة نموذج قديم');
        $existingJob = $this->job($company, [
            'title' => 'وظيفة تبقى على فئتها الأصلية',
            'status' => 'draft',
        ]);

        $this->actingAs($employer)
            ->get(route('employer.jobs.create', absolute: false))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('categories', fn ($categories): bool => collect($categories)->contains('key', $category->key)));

        $this->actingAs($admin)
            ->delete(route('admin.job-categories.destroy', $category, absolute: false))
            ->assertRedirect();

        $this->actingAs($employer)
            ->post(route('employer.jobs.store', absolute: false), $this->jobPayload([
                'category' => $category->key,
                'subcategory' => null,
            ]))
            ->assertSessionHasErrors([
                'category' => 'الفئة الوظيفية المحددة لم تعد متاحة. حدّث الصفحة واختر فئة أخرى.',
            ]);

        $this->assertDatabaseMissing('jobs', ['title' => 'وظيفة اختبار الفئات']);

        $this->actingAs($employer)
            ->put(route('employer.jobs.update', $existingJob, absolute: false), $this->jobPayload([
                'title' => $existingJob->title,
                'status' => 'draft',
                'category' => $category->key,
                'subcategory' => null,
            ]))
            ->assertSessionHasErrors([
                'category' => 'الفئة الوظيفية المحددة لم تعد متاحة. حدّث الصفحة واختر فئة أخرى.',
            ]);

        $existingJob->refresh();
        $this->assertSame('finance', $existingJob->category);
        $this->assertSame('accounting', $existingJob->subcategory);
    }

    public function test_edit_requires_a_category_and_rejected_updates_preserve_the_original_job(): void
    {
        [$employer, $company] = $this->employerAndCompany();
        $job = $this->job($company, ['status' => 'draft']);
        $before = $job->fresh()->getAttributes();

        foreach ([null, 'nonexistent-category'] as $category) {
            $this->actingAs($employer)
                ->put(route('employer.jobs.update', $job, absolute: false), $this->jobPayload([
                    'category' => $category,
                    'subcategory' => null,
                    'status' => 'draft',
                ]))
                ->assertSessionHasErrors('category');

            $this->assertSame($before, $job->fresh()->getAttributes());
        }
    }

    public function test_unused_category_is_deleted_with_its_subcategories_without_touching_jobs(): void
    {
        $admin = $this->user('admin');
        $unrelatedJob = $this->job($this->company());
        $category = $this->category('unused-category', 'فئة غير مستخدمة');
        $subcategory = $this->subcategory($category, 'unused-child', 'فرعية غير مستخدمة');

        $this->actingAs($admin)
            ->delete(route('admin.job-categories.destroy', $category, absolute: false))
            ->assertRedirect(route('admin.job-categories.index', absolute: false));

        $this->assertDatabaseMissing('job_categories', ['id' => $category->id]);
        $this->assertDatabaseMissing('job_subcategories', ['id' => $subcategory->id]);
        $this->assertDatabaseHas('jobs', ['id' => $unrelatedJob->id]);
    }

    public function test_category_linked_to_jobs_is_blocked_and_jobs_applications_and_assignments_are_preserved(): void
    {
        $admin = $this->user('admin');
        $category = $this->category('linked-category', 'فئة مرتبطة');
        $subcategory = $this->subcategory($category, 'linked-child', 'فرعية مرتبطة');
        $company = $this->company();
        $job = $this->job($company, [
            'category' => $category->key,
            'subcategory' => $subcategory->key,
        ]);
        $seeker = $this->user('job_seeker');
        $application = Application::query()->create([
            'job_id' => $job->id,
            'user_id' => $seeker->id,
            'cover_letter' => 'طلب يجب الحفاظ عليه.',
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.job-categories.destroy', $category, absolute: false))
            ->assertRedirect(route('admin.job-categories.index', absolute: false))
            ->assertSessionHas('error', 'لا يمكن حذف هذه الفئة لأنها مرتبطة بوظائف. انقل الوظائف إلى فئة أخرى أولًا. عدد الوظائف المرتبطة: 1.');

        $this->assertDatabaseHas('job_categories', ['id' => $category->id]);
        $this->assertDatabaseHas('job_subcategories', ['id' => $subcategory->id]);
        $this->assertDatabaseHas('jobs', [
            'id' => $job->id,
            'category' => $category->key,
            'subcategory' => $subcategory->key,
        ]);
        $this->assertDatabaseHas('applications', ['id' => $application->id, 'job_id' => $job->id]);
    }

    public function test_database_restricts_direct_deletion_of_a_linked_category(): void
    {
        $category = $this->category('database-protected', 'فئة محمية بقاعدة البيانات');
        $this->job($this->company(), ['category' => $category->key, 'subcategory' => null]);
        $caught = false;

        try {
            DB::transaction(fn () => JobCategory::query()->whereKey($category->id)->delete());
        } catch (QueryException $exception) {
            $this->assertSame(DB::connection()->getDriverName() === 'pgsql' ? '23001' : '23000', $exception->getCode());
            $caught = true;
        }

        $this->assertTrue($caught, 'The database foreign key must restrict deletion of a category linked to a job.');
        $this->assertDatabaseHas('job_categories', ['id' => $category->id]);
        $this->assertDatabaseHas('jobs', ['category' => $category->key]);
    }

    public function test_duplicate_delete_request_cannot_delete_another_category(): void
    {
        $admin = $this->user('admin');
        $deleted = $this->category('delete-once', 'فئة تحذف مرة واحدة');
        $preserved = $this->category('preserve-category', 'فئة محفوظة');
        $url = route('admin.job-categories.destroy', $deleted, absolute: false);

        $this->actingAs($admin)->delete($url)->assertRedirect();
        $this->actingAs($admin)->delete($url)->assertNotFound();

        $this->assertDatabaseMissing('job_categories', ['id' => $deleted->id]);
        $this->assertDatabaseHas('job_categories', ['id' => $preserved->id]);
    }

    public function test_deleted_builtin_category_is_not_recreated_by_page_loads_or_backfill(): void
    {
        $admin = $this->user('admin');
        [$employer, $company] = $this->employerAndCompany();
        $education = JobCategory::query()->where('key', 'education')->firstOrFail();

        $this->actingAs($admin)
            ->delete(route('admin.job-categories.destroy', $education, absolute: false))
            ->assertRedirect();

        $teacher = $this->job($company, [
            'title' => 'معلم لغة عربية',
            'category' => null,
            'subcategory' => null,
        ]);

        $this->actingAs($employer)
            ->get(route('employer.jobs.create', absolute: false))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('categories', fn ($categories): bool => ! collect($categories)->contains('key', 'education')));
        $this->get(route('jobs.index', absolute: false))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('categories', fn ($categories): bool => ! collect($categories)->contains('key', 'education')));

        $this->seed(JobCategoryBackfillSeeder::class);

        $this->assertDatabaseMissing('job_categories', ['key' => 'education']);
        $this->assertNull($teacher->fresh()->category);
        $this->assertNull($teacher->fresh()->subcategory);
    }

    private function user(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'email' => Str::lower(Str::random(12)).'@category.test',
        ]);
    }

    private function company(): CompanyProfile
    {
        [, $company] = $this->employerAndCompany();

        return $company;
    }

    /** @return array{0: User, 1: CompanyProfile} */
    private function employerAndCompany(): array
    {
        $employer = $this->user('employer');
        $company = CompanyProfile::query()->create([
            'user_id' => $employer->id,
            'company_name' => 'شركة فئات '.Str::random(6),
            'verification_status' => 'verified',
        ]);

        return [$employer, $company];
    }

    private function category(string $key, string $name): JobCategory
    {
        return JobCategory::query()->create([
            'key' => $key,
            'name_ar' => $name,
            'name_en' => null,
            'sort_order' => (int) JobCategory::query()->max('sort_order') + 1,
        ]);
    }

    private function subcategory(JobCategory $category, string $key, string $name): JobSubcategory
    {
        return JobSubcategory::query()->create([
            'category_key' => $category->key,
            'key' => $key,
            'name_ar' => $name,
            'name_en' => null,
            'sort_order' => 1,
        ]);
    }

    private function job(CompanyProfile $company, array $overrides = []): Job
    {
        return Job::query()->create(array_merge([
            'company_profile_id' => $company->id,
            'title' => 'وظيفة مرتبطة بفئة',
            'slug' => 'admin-category-job-'.Str::lower(Str::random(12)),
            'description' => 'وصف وظيفي.',
            'responsibilities' => ['مسؤولية'],
            'required_skills' => ['مهارة'],
            'status' => 'published',
            'category' => 'finance',
            'subcategory' => 'accounting',
        ], $overrides));
    }

    private function jobPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'وظيفة اختبار الفئات',
            'description' => 'وصف وظيفي واضح.',
            'responsibilities' => ['مسؤولية'],
            'required_skills' => ['مهارة'],
            'location' => 'طرابلس',
            'job_type' => 'دوام كامل',
            'contract_type' => 'عقد سنوي',
            'experience_level' => 'متوسط',
            'status' => 'published',
            'category' => 'finance',
            'subcategory' => 'accounting',
        ], $overrides);
    }
}
