<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $definitions = $this->definitions();
        $categoryKeys = array_keys($definitions);
        $subcategoryParents = [];

        foreach ($definitions as $categoryKey => $definition) {
            foreach (array_keys($definition['subcategories']) as $subcategoryKey) {
                $subcategoryParents[$subcategoryKey] = $categoryKey;
            }
        }

        $unknownCategories = DB::table('jobs')
            ->whereNotNull('category')
            ->distinct()
            ->pluck('category')
            ->reject(fn (string $key): bool => in_array($key, $categoryKeys, true));
        $invalidPairs = DB::table('jobs')
            ->whereNotNull('subcategory')
            ->get(['id', 'category', 'subcategory'])
            ->reject(fn (object $job): bool => $job->category !== null
                && ($subcategoryParents[$job->subcategory] ?? null) === $job->category);

        if ($unknownCategories->isNotEmpty() || $invalidPairs->isNotEmpty()) {
            throw new RuntimeException(
                'Cannot create job category relationships until existing job category keys are mapped explicitly.',
            );
        }

        Schema::create('job_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('name_ar')->unique();
            $table->string('name_en')->nullable();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->timestamps();
        });

        Schema::create('job_subcategories', function (Blueprint $table): void {
            $table->id();
            $table->string('category_key');
            $table->string('key')->unique();
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['category_key', 'key']);
            $table->unique(['category_key', 'name_ar']);
            $table->foreign('category_key')
                ->references('key')
                ->on('job_categories')
                ->cascadeOnDelete();
        });

        $now = now();
        $categoryRows = [];
        $subcategoryRows = [];

        foreach ($definitions as $categoryOrder => $definition) {
            $categoryRows[] = [
                'key' => $categoryOrder,
                'name_ar' => $definition['name_ar'],
                'name_en' => $definition['name_en'],
                'sort_order' => count($categoryRows),
                'created_at' => $now,
                'updated_at' => $now,
            ];

            foreach ($definition['subcategories'] as $subcategoryKey => $subcategory) {
                $subcategoryRows[] = [
                    'category_key' => $categoryOrder,
                    'key' => $subcategoryKey,
                    'name_ar' => $subcategory['name_ar'],
                    'name_en' => $subcategory['name_en'],
                    'sort_order' => count(array_filter(
                        $subcategoryRows,
                        fn (array $row): bool => $row['category_key'] === $categoryOrder,
                    )),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        DB::table('job_categories')->insert($categoryRows);
        DB::table('job_subcategories')->insert($subcategoryRows);

        Schema::table('jobs', function (Blueprint $table): void {
            $table->foreign('category', 'jobs_job_category_key_foreign')
                ->references('key')
                ->on('job_categories')
                ->restrictOnDelete();
            $table->foreign('subcategory', 'jobs_job_subcategory_key_foreign')
                ->references('key')
                ->on('job_subcategories')
                ->restrictOnDelete();
            $table->foreign(
                ['category', 'subcategory'],
                'jobs_job_category_subcategory_pair_foreign',
            )->references(['category_key', 'key'])
                ->on('job_subcategories')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('jobs', function (Blueprint $table): void {
            $table->dropForeign('jobs_job_category_subcategory_pair_foreign');
            $table->dropForeign('jobs_job_subcategory_key_foreign');
            $table->dropForeign('jobs_job_category_key_foreign');
        });

        Schema::dropIfExists('job_subcategories');
        Schema::dropIfExists('job_categories');
    }

    /**
     * @return array<string, array{name_ar: string, name_en: string, subcategories: array<string, array{name_ar: string, name_en: string}>}>
     */
    private function definitions(): array
    {
        return [
            'finance' => [
                'name_ar' => 'المالية',
                'name_en' => 'Finance',
                'subcategories' => [
                    'accounting' => ['name_ar' => 'المحاسبة', 'name_en' => 'Accounting'],
                    'treasury' => ['name_ar' => 'الخزينة', 'name_en' => 'Treasury'],
                    'audit' => ['name_ar' => 'المراجعة والتدقيق', 'name_en' => 'Review and Auditing'],
                    'financial_analysis_budgeting' => ['name_ar' => 'التحليل المالي والموازنات', 'name_en' => 'Financial Analysis and Budgeting'],
                ],
            ],
            'marketing_sales' => [
                'name_ar' => 'التسويق والمبيعات',
                'name_en' => 'Marketing and Sales',
                'subcategories' => [
                    'sales' => ['name_ar' => 'المبيعات', 'name_en' => 'Sales'],
                    'promotion_representatives' => ['name_ar' => 'المناديب والترويج', 'name_en' => 'Representatives and Promotion'],
                    'digital_marketing' => ['name_ar' => 'التسويق الرقمي', 'name_en' => 'Digital Marketing'],
                ],
            ],
            'content_design_media' => [
                'name_ar' => 'المحتوى والتصميم والتصوير',
                'name_en' => 'Content, Design and Photography',
                'subcategories' => [
                    'content_media' => ['name_ar' => 'صناعة المحتوى والإعلام', 'name_en' => 'Content Creation and Media'],
                    'graphic_design' => ['name_ar' => 'التصميم الجرافيكي', 'name_en' => 'Graphic Design'],
                    'photo_video' => ['name_ar' => 'التصوير الفوتوغرافي والفيديو', 'name_en' => 'Photography and Video'],
                    'social_pages_copywriting' => ['name_ar' => 'إدارة الصفحات وكتابة المحتوى', 'name_en' => 'Page Management and Content Writing'],
                ],
            ],
            'administration_services' => [
                'name_ar' => 'الإدارة والخدمات',
                'name_en' => 'Administration and Services',
                'subcategories' => [
                    'administration_secretarial' => ['name_ar' => 'الإدارة والسكرتارية', 'name_en' => 'Administration and Secretarial'],
                    'human_resources' => ['name_ar' => 'الموارد البشرية', 'name_en' => 'Human Resources'],
                    'customer_service_inquiries' => ['name_ar' => 'خدمة العملاء والاستعلامات', 'name_en' => 'Customer Service and Inquiries'],
                ],
            ],
            'medical' => [
                'name_ar' => 'المجال الطبي',
                'name_en' => 'Medical',
                'subcategories' => [
                    'hospitals_healthcare' => ['name_ar' => 'المستشفيات والمرافق الصحية', 'name_en' => 'Hospitals and Healthcare Facilities'],
                    'pharmaceutical_companies' => ['name_ar' => 'شركات الأدوية', 'name_en' => 'Pharmaceutical Companies'],
                    'nursing' => ['name_ar' => 'التمريض', 'name_en' => 'Nursing'],
                    'pharmacy_laboratories' => ['name_ar' => 'الصيدلة والمختبرات', 'name_en' => 'Pharmacy and Laboratories'],
                ],
            ],
            'hospitality_catering_travel' => [
                'name_ar' => 'الضيافة والتموين والسفر',
                'name_en' => 'Hospitality, Catering and Travel',
                'subcategories' => [
                    'restaurants' => ['name_ar' => 'المطاعم', 'name_en' => 'Restaurants'],
                    'catering_companies' => ['name_ar' => 'شركات التموين', 'name_en' => 'Catering Companies'],
                    'ticketing_aviation' => ['name_ar' => 'حجز التذاكر والطيران', 'name_en' => 'Ticketing and Aviation'],
                ],
            ],
            'education' => [
                'name_ar' => 'التعليم',
                'name_en' => 'Education',
                'subcategories' => [
                    'teaching' => ['name_ar' => 'التعليم والتدريس', 'name_en' => 'Education and Teaching'],
                    'kindergarten' => ['name_ar' => 'رياض الأطفال', 'name_en' => 'Kindergarten'],
                    'schools' => ['name_ar' => 'المدارس', 'name_en' => 'Schools'],
                    'universities_colleges' => ['name_ar' => 'الجامعات والكليات', 'name_en' => 'Universities and Colleges'],
                ],
            ],
            'transport_delivery' => [
                'name_ar' => 'النقل والتوصيل',
                'name_en' => 'Transport and Delivery',
                'subcategories' => [
                    'delivery_representatives' => ['name_ar' => 'المناديب والتوصيل', 'name_en' => 'Representatives and Delivery'],
                ],
            ],
        ];
    }
};
