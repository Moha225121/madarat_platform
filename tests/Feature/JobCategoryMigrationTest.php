<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\CompanyProfile;
use App\Models\Job;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

class JobCategoryMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->assertTrue(app()->environment('testing'));
        config()->set('database.connections.category_upgrade', [
            ...config('database.connections.sqlite'),
            'url' => null,
            'database' => ':memory:',
            'foreign_key_constraints' => true,
        ]);
        DB::setDefaultConnection('category_upgrade');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());

        $paths = collect(glob(database_path('migrations/*.php')))
            ->reject(fn (string $path): bool => str_starts_with(basename($path), '2026_10_22_'))
            ->map(fn (string $path): string => 'database/migrations/'.basename($path))
            ->values()
            ->all();

        $this->assertSame(0, Artisan::call('migrate', [
            '--database' => 'category_upgrade',
            '--path' => $paths,
            '--force' => true,
        ]));
    }

    protected function tearDown(): void
    {
        DB::purge('category_upgrade');

        parent::tearDown();
    }

    public function test_incremental_migrations_preserve_populated_jobs_users_applications_and_assignments(): void
    {
        [$classified, $legacy] = $this->existingRecords();
        $beforeColumns = $this->snapshot();

        $this->columnsMigration()->up();

        $afterColumns = $this->snapshot();
        foreach ($afterColumns['jobs'] as &$row) {
            $this->assertNull($row['category']);
            $this->assertNull($row['subcategory']);
            unset($row['category'], $row['subcategory']);
        }
        unset($row);
        $this->assertSame($beforeColumns, $afterColumns);

        DB::table('jobs')->where('id', $classified->id)->update([
            'category' => 'finance',
            'subcategory' => 'accounting',
        ]);
        $beforeRelationships = $this->snapshot();

        $this->relationshipsMigration()->up();

        $this->assertSame($beforeRelationships, $this->snapshot());
        $this->assertSame(8, DB::table('job_categories')->count());
        $this->assertSame(26, DB::table('job_subcategories')->count());
        $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
        $this->assertSame('finance', $classified->fresh()->category);
        $this->assertSame('accounting', $classified->fresh()->subcategory);
        $this->assertNull($legacy->fresh()->category);

        $this->get('/jobs')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('jobs.total', 2)
            ->where('counts.other', 1)
            ->where('jobs.data', fn ($jobs): bool => collect($jobs)->contains(
                fn ($job): bool => $job['id'] === $legacy->id && $job['is_other'] === true,
            )));
    }

    public function test_unmapped_existing_keys_stop_the_migration_without_losing_or_reassigning_records(): void
    {
        [$job] = $this->existingRecords();
        $this->columnsMigration()->up();
        DB::table('jobs')->where('id', $job->id)->update([
            'category' => 'legacy-unmapped-key',
            'subcategory' => null,
        ]);
        $before = $this->snapshot();
        $caught = null;

        try {
            $this->relationshipsMigration()->up();
        } catch (RuntimeException $exception) {
            $caught = $exception;
        }

        $this->assertInstanceOf(RuntimeException::class, $caught);
        $this->assertStringContainsString('mapped explicitly', $caught->getMessage());
        $this->assertSame($before, $this->snapshot());
        $this->assertFalse(Schema::hasTable('job_categories'));
        $this->assertFalse(Schema::hasTable('job_subcategories'));
    }

    public function test_legacy_unmapped_and_null_categories_are_not_excluded_from_public_browsing(): void
    {
        [$unmapped, $uncategorized] = $this->existingRecords();
        $this->columnsMigration()->up();
        $this->relationshipsMigration()->up();

        // Simulate an older import with a missing catalog entry, only in this
        // dedicated in-memory test connection. Normal writes remain FK-protected.
        Schema::disableForeignKeyConstraints();
        try {
            DB::table('jobs')->where('id', $unmapped->id)->update([
                'category' => 'legacy-missing-definition',
            ]);
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        $this->get('/jobs')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('jobs.total', 2)
            ->where('counts.other', 2)
            ->where('jobs.data', fn ($jobs): bool => collect($jobs)->pluck('id')->sort()->values()->all()
                === collect([$unmapped->id, $uncategorized->id])->sort()->values()->all()
                && collect($jobs)->every(fn ($job): bool => $job['is_other'] === true)));

        $this->get('/jobs/'.$unmapped->slug)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('job.id', $unmapped->id)
            ->where('job.is_other', true)
            ->where('job.category_label', null));
    }

    /** @return array{Job, Job} */
    private function existingRecords(): array
    {
        $employer = User::factory()->create(['role' => 'employer']);
        $seeker = User::factory()->create(['role' => 'job_seeker']);
        $company = CompanyProfile::create([
            'user_id' => $employer->id,
            'company_name' => 'شركة اختبار ترقية الفئات',
            'verification_status' => 'verified',
        ]);
        $jobs = [];

        foreach (['classified', 'legacy'] as $suffix) {
            $jobs[] = Job::create([
                'company_profile_id' => $company->id,
                'title' => 'وظيفة موجودة '.$suffix,
                'slug' => 'upgrade-'.$suffix,
                'description' => 'وصف موجود يجب الحفاظ عليه حرفيًا.',
                'responsibilities' => ['مسؤولية موجودة'],
                'required_skills' => ['مهارة موجودة'],
                'location' => 'طرابلس',
                'salary_min' => 1000,
                'salary_max' => 2000,
                'status' => 'published',
            ]);
        }

        Application::create([
            'job_id' => $jobs[0]->id,
            'user_id' => $seeker->id,
            'cover_letter' => 'طلب موجود يجب ألا يتغير.',
            'match_score' => 82,
            'status' => 'shortlisted',
        ]);

        return $jobs;
    }

    private function snapshot(): array
    {
        return collect(['users', 'company_profiles', 'jobs', 'applications'])
            ->mapWithKeys(fn (string $table): array => [
                $table => DB::table($table)->orderBy('id')->get()->map(fn ($row): array => (array) $row)->all(),
            ])
            ->all();
    }

    private function columnsMigration(): object
    {
        return require database_path('migrations/2026_10_22_000001_add_category_fields_to_jobs_table.php');
    }

    private function relationshipsMigration(): object
    {
        return require database_path('migrations/2026_10_22_000002_create_job_category_tables.php');
    }
}
