<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\Job;
use App\Models\JobCategory;
use App\Models\JobSubcategory;
use App\Models\User;
use App\Support\JobCategoryCatalog;
use Database\Seeders\JobCategoryBackfillSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class JobCategoryBrowsingTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_catalog_contains_the_eight_stable_categories_and_requested_arabic_structure(): void
    {
        $catalog = app(JobCategoryCatalog::class)->all();

        $this->assertSame(8, JobCategory::query()->count());
        $this->assertSame(26, JobSubcategory::query()->count());

        $this->assertSame([
            'finance',
            'marketing_sales',
            'content_design_media',
            'administration_services',
            'medical',
            'hospitality_catering_travel',
            'education',
            'transport_delivery',
        ], array_keys($catalog));

        $this->assertSame([
            'المالية',
            'التسويق والمبيعات',
            'المحتوى والتصميم والتصوير',
            'الإدارة والخدمات',
            'المجال الطبي',
            'الضيافة والتموين والسفر',
            'التعليم',
            'النقل والتوصيل',
        ], array_column($catalog, 'label_ar'));

        $this->assertSame(
            ['المحاسبة', 'الخزينة', 'المراجعة والتدقيق', 'التحليل المالي والموازنات'],
            array_column($catalog['finance']['subcategories'], 'label_ar'),
        );
        $this->assertSame(
            ['المبيعات', 'المناديب والترويج', 'التسويق الرقمي'],
            array_column($catalog['marketing_sales']['subcategories'], 'label_ar'),
        );
        $this->assertSame(
            ['صناعة المحتوى والإعلام', 'التصميم الجرافيكي', 'التصوير الفوتوغرافي والفيديو', 'إدارة الصفحات وكتابة المحتوى'],
            array_column($catalog['content_design_media']['subcategories'], 'label_ar'),
        );
        $this->assertSame(
            ['الإدارة والسكرتارية', 'الموارد البشرية', 'خدمة العملاء والاستعلامات'],
            array_column($catalog['administration_services']['subcategories'], 'label_ar'),
        );
        $this->assertSame(
            ['المستشفيات والمرافق الصحية', 'شركات الأدوية', 'التمريض', 'الصيدلة والمختبرات'],
            array_column($catalog['medical']['subcategories'], 'label_ar'),
        );
        $this->assertSame(
            ['المطاعم', 'شركات التموين', 'حجز التذاكر والطيران'],
            array_column($catalog['hospitality_catering_travel']['subcategories'], 'label_ar'),
        );
        $this->assertSame(
            ['التعليم والتدريس', 'رياض الأطفال', 'المدارس', 'الجامعات والكليات'],
            array_column($catalog['education']['subcategories'], 'label_ar'),
        );
        $this->assertSame(
            ['المناديب والتوصيل'],
            array_column($catalog['transport_delivery']['subcategories'], 'label_ar'),
        );
    }

    public function test_the_jobs_index_is_published_only_paginated_and_counts_the_full_matching_dataset(): void
    {
        $company = $this->company();

        foreach (range(1, 13) as $index) {
            $this->job($company, [
                'title' => "محاسب {$index}",
                'category' => 'finance',
                'subcategory' => 'accounting',
            ]);
        }

        $legacy = $this->job($company, [
            'title' => 'وظيفة قديمة غير مصنفة',
            'category' => null,
            'subcategory' => null,
        ]);
        $legacy->forceFill([
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ])->save();
        $this->job($company, [
            'title' => 'وظيفة مخفية',
            'status' => 'draft',
            'category' => 'finance',
            'subcategory' => 'accounting',
        ]);

        $this->get('/jobs')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Jobs/Index')
                ->where('jobs.total', 14)
                ->where('jobs.per_page', 12)
                ->has('jobs.data', 12)
                ->where('counts.total', 14)
                ->where('counts.other', 1)
                ->where('categories', function ($categories): bool {
                    $finance = collect($categories)->firstWhere('key', 'finance');
                    $accounting = collect($finance['subcategories'])->firstWhere('key', 'accounting');

                    return $finance['count'] === 13 && $accounting['count'] === 13;
                })
                ->where('jobs.data', fn ($jobs): bool => ! collect($jobs)->contains('title', 'وظيفة مخفية')));

        $this->get('/jobs?page=2')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('jobs.data', 2)
                ->where('counts.total', 14)
                ->where('counts.other', 1)
                ->where('jobs.data', fn ($jobs): bool => collect($jobs)->pluck('id')->contains($legacy->id)));
    }

    public function test_category_filters_compose_with_existing_filters_before_pagination_and_counts_remain_accurate(): void
    {
        $company = $this->company();
        $target = $this->job($company, [
            'title' => 'أخصائي تسويق رقمي مميز',
            'location' => 'طرابلس',
            'job_type' => 'دوام كامل',
            'experience_level' => 'متوسط',
            'category' => 'marketing_sales',
            'subcategory' => 'digital_marketing',
        ]);
        $this->job($company, [
            'title' => 'أخصائي تسويق رقمي مالي',
            'location' => 'طرابلس',
            'job_type' => 'دوام كامل',
            'experience_level' => 'متوسط',
            'category' => 'finance',
            'subcategory' => 'financial_analysis_budgeting',
        ]);
        $this->job($company, [
            'title' => 'أخصائي تسويق رقمي في بنغازي',
            'location' => 'بنغازي',
            'job_type' => 'دوام كامل',
            'experience_level' => 'متوسط',
            'category' => 'marketing_sales',
            'subcategory' => 'digital_marketing',
        ]);
        $this->job($company, [
            'title' => 'أخصائي تسويق رقمي جزئي',
            'location' => 'طرابلس',
            'job_type' => 'دوام جزئي',
            'experience_level' => 'متوسط',
            'category' => 'marketing_sales',
            'subcategory' => 'digital_marketing',
        ]);
        $this->job($company, [
            'title' => 'أخصائي تسويق رقمي خبير',
            'location' => 'طرابلس',
            'job_type' => 'دوام كامل',
            'experience_level' => 'خبير',
            'category' => 'marketing_sales',
            'subcategory' => 'digital_marketing',
        ]);
        $this->job($company, [
            'title' => 'أخصائي تسويق رقمي مخفي',
            'location' => 'طرابلس',
            'job_type' => 'دوام كامل',
            'experience_level' => 'متوسط',
            'category' => 'marketing_sales',
            'subcategory' => 'digital_marketing',
            'status' => 'closed',
        ]);

        $query = http_build_query([
            'search' => 'أخصائي تسويق رقمي',
            'location' => 'طرابلس',
            'job_type' => 'دوام كامل',
            'experience_level' => 'متوسط',
            'category' => 'marketing_sales',
            'subcategory' => 'digital_marketing',
        ]);

        $this->get("/jobs?{$query}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('jobs.total', 1)
                ->has('jobs.data', 1)
                ->where('jobs.data.0.id', $target->id)
                ->where('counts.total', 2)
                ->where('counts.other', 0)
                ->where('filters.search', 'أخصائي تسويق رقمي')
                ->where('filters.location', 'طرابلس')
                ->where('filters.job_type', 'دوام كامل')
                ->where('filters.experience_level', 'متوسط')
                ->where('filters.category', 'marketing_sales')
                ->where('filters.subcategory', 'digital_marketing')
                ->where('categories', function ($categories): bool {
                    $marketing = collect($categories)->firstWhere('key', 'marketing_sales');
                    $digital = collect($marketing['subcategories'])->firstWhere('key', 'digital_marketing');
                    $finance = collect($categories)->firstWhere('key', 'finance');

                    return $marketing['count'] === 1
                        && $digital['count'] === 1
                        && $finance['count'] === 1;
                }));
    }

    public function test_jobs_keep_verified_company_first_and_newest_ordering(): void
    {
        $verifiedCompany = $this->company();
        [, $unverifiedCompany] = $this->employerAndCompany();
        $unverifiedCompany->update(['verification_status' => 'unverified']);

        $verifiedOlder = $this->job($verifiedCompany, ['title' => 'وظيفة موثقة أقدم']);
        $verifiedOlder->forceFill(['created_at' => now()->subDays(2), 'updated_at' => now()->subDays(2)])->save();
        $verifiedNewer = $this->job($verifiedCompany, ['title' => 'وظيفة موثقة أحدث']);
        $verifiedNewer->forceFill(['created_at' => now()->subDay(), 'updated_at' => now()->subDay()])->save();
        $unverifiedNewest = $this->job($unverifiedCompany, ['title' => 'وظيفة غير موثقة الأحدث']);

        $this->get('/jobs')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('jobs.data', fn ($jobs): bool => collect($jobs)->pluck('id')->all() === [
                    $verifiedNewer->id,
                    $verifiedOlder->id,
                    $unverifiedNewest->id,
                ]));
    }

    public function test_legacy_uncategorized_jobs_remain_available_only_in_the_fallback_collection(): void
    {
        $company = $this->company();
        $categorized = $this->job($company, [
            'title' => 'محاسب مصنف',
            'category' => 'finance',
            'subcategory' => 'accounting',
        ]);
        $legacy = $this->job($company, [
            'title' => 'وظيفة غامضة',
            'category' => null,
            'subcategory' => null,
        ]);

        $this->get('/jobs')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('jobs.total', 2)
                ->where('counts.other', 1)
                ->where('jobs.data', fn ($jobs): bool => collect($jobs)->pluck('id')->sort()->values()->all() === collect([$categorized->id, $legacy->id])->sort()->values()->all()));

        $this->get('/jobs?category=finance')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('jobs.total', 1)
                ->where('jobs.data.0.id', $categorized->id)
                ->where('counts.other', 1));
    }

    public function test_pagination_links_preserve_category_subcategory_and_existing_search_filters(): void
    {
        $company = $this->company();

        foreach (range(1, 13) as $index) {
            $this->job($company, [
                'title' => "مسوق رقمي {$index}",
                'location' => 'طرابلس',
                'job_type' => 'دوام كامل',
                'experience_level' => 'متوسط',
                'category' => 'marketing_sales',
                'subcategory' => 'digital_marketing',
            ]);
        }

        $query = http_build_query([
            'search' => 'مسوق رقمي',
            'location' => 'طرابلس',
            'job_type' => 'دوام كامل',
            'experience_level' => 'متوسط',
            'category' => 'marketing_sales',
            'subcategory' => 'digital_marketing',
        ]);

        $this->get("/jobs?{$query}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('jobs.total', 13)
                ->has('jobs.data', 12)
                ->where('jobs.next_page_url', function (string $url): bool {
                    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

                    return ($query['page'] ?? null) === '2'
                        && ($query['search'] ?? null) === 'مسوق رقمي'
                        && ($query['location'] ?? null) === 'طرابلس'
                        && ($query['job_type'] ?? null) === 'دوام كامل'
                        && ($query['experience_level'] ?? null) === 'متوسط'
                        && ($query['category'] ?? null) === 'marketing_sales'
                        && ($query['subcategory'] ?? null) === 'digital_marketing';
                }));
    }

    public function test_job_details_keep_the_existing_job_and_expose_its_category_labels(): void
    {
        $job = $this->job($this->company(), [
            'title' => 'محاسب تفاصيل',
            'slug' => 'accountant-details',
            'category' => 'finance',
            'subcategory' => 'accounting',
        ]);

        $this->get('/jobs/accountant-details')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Jobs/Show')
                ->where('job.id', $job->id)
                ->where('job.title', 'محاسب تفاصيل')
                ->where('job.category', 'finance')
                ->where('job.subcategory', 'accounting')
                ->where('job.category_label', 'المالية')
                ->where('job.subcategory_label', 'المحاسبة'));
    }

    public function test_all_new_jobs_require_a_valid_category_and_related_subcategory(): void
    {
        [$employer] = $this->employerAndCompany();

        $this->actingAs($employer)
            ->post('/employer/jobs', $this->jobPayload([
                'category' => null,
                'subcategory' => null,
            ]))
            ->assertSessionHasErrors('category');

        $this->assertDatabaseCount('jobs', 0);

        $this->actingAs($employer)
            ->post('/employer/jobs', $this->jobPayload([
                'status' => 'draft',
                'category' => null,
                'subcategory' => null,
            ]))
            ->assertSessionHasErrors('category');

        $this->assertDatabaseCount('jobs', 0);

        $this->actingAs($employer)
            ->post('/employer/jobs', $this->jobPayload([
                'category' => 'unknown_category',
                'subcategory' => null,
            ]))
            ->assertSessionHasErrors('category');

        $this->assertDatabaseCount('jobs', 0);

        $this->actingAs($employer)
            ->post('/employer/jobs', $this->jobPayload([
                'category' => 'finance',
                'subcategory' => 'unknown_subcategory',
            ]))
            ->assertSessionHasErrors('subcategory');

        $this->assertDatabaseCount('jobs', 0);

        $this->actingAs($employer)
            ->post('/employer/jobs', $this->jobPayload([
                'category' => 'finance',
                'subcategory' => 'digital_marketing',
            ]))
            ->assertSessionHasErrors('subcategory');

        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_create_and_edit_forms_receive_the_catalog_and_persisted_assignment(): void
    {
        [$employer, $company] = $this->employerAndCompany();
        $job = $this->job($company, [
            'status' => 'draft',
            'category' => 'finance',
            'subcategory' => 'accounting',
        ]);

        $this->actingAs($employer)
            ->get('/employer/jobs/create')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Employer/JobForm')
                ->where('job', null)
                ->has('categories', 8)
                ->where('categories.0.key', 'finance'));

        $this->get("/employer/jobs/{$job->id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Employer/JobForm')
                ->where('job.id', $job->id)
                ->where('job.category', 'finance')
                ->where('job.subcategory', 'accounting')
                ->has('categories', 8));
    }

    public function test_drafts_require_categories_and_valid_assignments_persist_after_saving(): void
    {
        [$employer] = $this->employerAndCompany();

        $this->actingAs($employer)
            ->post('/employer/jobs', $this->jobPayload([
                'title' => 'مسودة قديمة',
                'status' => 'draft',
                'category' => null,
                'subcategory' => null,
            ]))
            ->assertSessionHasErrors('category');

        $this->assertDatabaseMissing('jobs', [
            'title' => 'مسودة قديمة',
        ]);

        $this->actingAs($employer)
            ->post('/employer/jobs', $this->jobPayload([
                'title' => 'مسودة مصنفة',
                'status' => 'draft',
                'category' => 'finance',
                'subcategory' => null,
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('jobs', [
            'title' => 'مسودة مصنفة',
            'status' => 'draft',
            'category' => 'finance',
            'subcategory' => null,
        ]);

        $this->actingAs($employer)
            ->post('/employer/jobs', $this->jobPayload())
            ->assertRedirect();

        $saved = Job::query()->where('title', 'أخصائي تسويق رقمي')->firstOrFail();

        $this->assertSame('pending_review', $saved->status);
        $this->assertSame('marketing_sales', $saved->category);
        $this->assertSame('digital_marketing', $saved->subcategory);
    }

    public function test_an_employer_can_update_a_job_assignment_but_an_invalid_pair_is_not_saved(): void
    {
        [$employer, $company] = $this->employerAndCompany();
        $job = $this->job($company, [
            'title' => 'محاسب',
            'status' => 'draft',
            'category' => 'finance',
            'subcategory' => 'accounting',
        ]);

        $this->actingAs($employer)
            ->put("/employer/jobs/{$job->id}", $this->jobPayload([
                'title' => 'أخصائي تسويق رقمي',
                'category' => 'marketing_sales',
                'subcategory' => 'digital_marketing',
            ]))
            ->assertRedirect();

        $this->assertSame('marketing_sales', $job->fresh()->category);
        $this->assertSame('digital_marketing', $job->fresh()->subcategory);

        $this->actingAs($employer)
            ->put("/employer/jobs/{$job->id}", $this->jobPayload([
                'category' => 'finance',
                'subcategory' => 'digital_marketing',
            ]))
            ->assertSessionHasErrors('subcategory');

        $this->assertSame('marketing_sales', $job->fresh()->category);
        $this->assertSame('digital_marketing', $job->fresh()->subcategory);
    }

    public function test_database_constraints_reject_a_cross_category_subcategory_pair(): void
    {
        $company = $this->company();
        $caught = false;

        try {
            DB::transaction(fn () => $this->job($company, [
                'title' => 'تصنيف غير متوافق',
                'category' => 'finance',
                'subcategory' => 'digital_marketing',
            ]));
        } catch (QueryException $exception) {
            $this->assertSame(DB::connection()->getDriverName() === 'pgsql' ? '23503' : '23000', $exception->getCode());
            $caught = true;
        }

        $this->assertTrue($caught, 'The database must reject a subcategory assigned to the wrong parent category.');
        $this->assertDatabaseMissing('jobs', ['title' => 'تصنيف غير متوافق']);
    }

    public function test_the_backfill_is_conservative_idempotent_and_preserves_existing_job_data(): void
    {
        $company = $this->company();
        $examples = [
            'digital' => $this->job($company, ['title' => 'أخصائي تسويق رقمي', 'category' => null, 'subcategory' => null]),
            'accountant' => $this->job($company, ['title' => 'محاسب', 'category' => null, 'subcategory' => null]),
            'sales' => $this->job($company, ['title' => 'موظف مبيعات', 'category' => null, 'subcategory' => null]),
            'promotion' => $this->job($company, ['title' => 'مندوب ترويج', 'category' => null, 'subcategory' => null]),
            'external_sales' => $this->job($company, ['title' => 'مندوب بيع خارجي', 'category' => null, 'subcategory' => null]),
            'delivery' => $this->job($company, ['title' => 'مندوب توصيل', 'category' => null, 'subcategory' => null]),
            'chef' => $this->job($company, ['title' => 'شيف وجبات شعبية', 'category' => null, 'subcategory' => null]),
            'graphic' => $this->job($company, ['title' => 'مصمم جرافيك', 'category' => null, 'subcategory' => null]),
            'responsibility' => $this->job($company, [
                'title' => 'فرصة مهنية',
                'responsibilities' => ['إدارة الحملات في التسويق الرقمي'],
                'category' => null,
                'subcategory' => null,
            ]),
            'representative' => $this->job($company, ['title' => 'مندوب', 'category' => null, 'subcategory' => null]),
            'laravel' => $this->job($company, ['title' => 'مطور Laravel و React', 'category' => null, 'subcategory' => null]),
            'analyst' => $this->job($company, ['title' => 'محلل نظم أعمال', 'category' => null, 'subcategory' => null]),
        ];
        $preclassified = $this->job($company, [
            'title' => 'مندوب توصيل مصنف يدويًا',
            'category' => 'finance',
            'subcategory' => 'accounting',
        ]);
        $preserved = $examples['promotion']->only([
            'id',
            'company_profile_id',
            'title',
            'slug',
            'description',
            'responsibilities',
            'required_skills',
            'location',
            'job_type',
            'contract_type',
            'experience_level',
            'salary_min',
            'salary_max',
            'status',
        ]);

        $this->seed(JobCategoryBackfillSeeder::class);

        $this->assertAssignment($examples['digital'], 'marketing_sales', 'digital_marketing');
        $this->assertAssignment($examples['accountant'], 'finance', 'accounting');
        $this->assertAssignment($examples['sales'], 'marketing_sales', 'sales');
        $this->assertAssignment($examples['promotion'], 'marketing_sales', 'promotion_representatives');
        $this->assertAssignment($examples['external_sales'], 'marketing_sales', 'promotion_representatives');
        $this->assertAssignment($examples['delivery'], 'transport_delivery', 'delivery_representatives');
        $this->assertAssignment($examples['chef'], 'hospitality_catering_travel', 'restaurants');
        $this->assertAssignment($examples['graphic'], 'content_design_media', 'graphic_design');
        $this->assertAssignment($examples['responsibility'], 'marketing_sales', 'digital_marketing');
        $this->assertAssignment($examples['representative'], null, null);
        $this->assertAssignment($examples['laravel'], null, null);
        $this->assertAssignment($examples['analyst'], null, null);
        $this->assertAssignment($preclassified, 'finance', 'accounting');
        $this->assertSame($preserved, $examples['promotion']->fresh()->only(array_keys($preserved)));

        $firstPass = Job::query()
            ->orderBy('id')
            ->get(['id', 'category', 'subcategory'])
            ->toArray();

        $this->seed(JobCategoryBackfillSeeder::class);

        $this->assertSame(
            $firstPass,
            Job::query()->orderBy('id')->get(['id', 'category', 'subcategory'])->toArray(),
        );
    }

    private function company(): CompanyProfile
    {
        [, $company] = $this->employerAndCompany();

        return $company;
    }

    /** @return array{0: User, 1: CompanyProfile} */
    private function employerAndCompany(): array
    {
        $employer = User::factory()->create(['role' => 'employer']);
        $company = CompanyProfile::create([
            'user_id' => $employer->id,
            'company_name' => 'شركة اختبار الفئات '.Str::random(6),
            'verification_status' => 'verified',
        ]);

        return [$employer, $company];
    }

    private function job(CompanyProfile $company, array $overrides = []): Job
    {
        return Job::create(array_merge([
            'company_profile_id' => $company->id,
            'title' => 'وظيفة مصنفة',
            'slug' => 'category-job-'.Str::lower(Str::random(12)),
            'description' => 'وصف وظيفي أصلي يجب الحفاظ عليه.',
            'responsibilities' => ['مسؤولية أصلية'],
            'required_skills' => ['مهارة أصلية'],
            'location' => 'طرابلس',
            'job_type' => 'دوام كامل',
            'contract_type' => 'عقد سنوي',
            'experience_level' => 'متوسط',
            'salary_min' => 2500,
            'salary_max' => 3500,
            'status' => 'published',
            'category' => 'finance',
            'subcategory' => 'accounting',
        ], $overrides));
    }

    private function jobPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'أخصائي تسويق رقمي',
            'description' => 'وصف وظيفي واضح وكامل.',
            'responsibilities' => ['إدارة الحملات'],
            'required_skills' => ['التسويق الرقمي'],
            'location' => 'طرابلس',
            'job_type' => 'دوام كامل',
            'contract_type' => 'عقد سنوي',
            'experience_level' => 'متوسط',
            'salary_min' => 2500,
            'salary_max' => 3500,
            'status' => 'published',
            'category' => 'marketing_sales',
            'subcategory' => 'digital_marketing',
        ], $overrides);
    }

    private function assertAssignment(Job $job, ?string $category, ?string $subcategory): void
    {
        $job->refresh();

        $this->assertSame($category, $job->category, "Unexpected category for {$job->title}");
        $this->assertSame($subcategory, $job->subcategory, "Unexpected subcategory for {$job->title}");
    }
}
