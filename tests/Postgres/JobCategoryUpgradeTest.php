<?php

namespace Tests\Postgres;

use App\Console\Commands\MigrateJobCategories;
use App\Models\Application;
use App\Models\CompanyProfile;
use App\Models\Job;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class JobCategoryUpgradeTest extends TestCase
{
    private const COLUMNS_MIGRATION = '2026_10_22_000001_add_category_fields_to_jobs_table.php';

    private const RELATIONSHIPS_MIGRATION = '2026_10_22_000002_create_job_category_tables.php';

    protected function setUp(): void
    {
        parent::setUp();

        // Every destructive setup operation is restricted to this disposable
        // loopback database, never the preview or an external PostgreSQL host.
        $this->assertTrue(app()->environment('testing'));
        $this->assertSame('pgsql', config('database.default'));
        $this->assertSame('127.0.0.1', config('database.connections.pgsql.host'));
        $this->assertSame('55432', (string) config('database.connections.pgsql.port'));
        $this->assertSame('madarat_category_verify', DB::connection()->getDatabaseName());
        $identity = DB::selectOne('SELECT current_database() AS database, host(inet_server_addr()) AS host');
        $this->assertSame('madarat_category_verify', $identity->database);
        $this->assertSame('127.0.0.1', $identity->host);

        $paths = collect(glob(database_path('migrations/*.php')))
            ->reject(fn (string $path): bool => in_array(basename($path), [
                self::COLUMNS_MIGRATION,
                self::RELATIONSHIPS_MIGRATION,
            ], true))
            ->map(fn (string $path): string => 'database/migrations/'.basename($path))
            ->values()
            ->all();

        $this->assertSame(0, Artisan::call('migrate:fresh', [
            '--database' => 'pgsql',
            '--path' => $paths,
            '--force' => true,
        ]));
    }

    public function test_upgrade_from_the_reported_production_jobs_schema_preserves_data_and_existing_constraints(): void
    {
        $legacyIds = $this->syntheticRecords();
        $before = $this->snapshot();
        $columns = collect(DB::select("SELECT column_name, data_type, is_nullable, column_default FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'jobs' ORDER BY ordinal_position"))
            ->keyBy('column_name');
        $this->assertSame([
            'id', 'company_profile_id', 'title', 'slug', 'description',
            'responsibilities', 'required_skills', 'location', 'job_type',
            'contract_type', 'experience_level', 'salary_min', 'salary_max',
            'status', 'generated_description', 'created_at', 'updated_at',
        ], $columns->keys()->all());
        foreach (['id', 'company_profile_id'] as $name) {
            $this->assertSame('bigint', $columns[$name]->data_type);
        }
        foreach (['responsibilities', 'required_skills'] as $name) {
            $this->assertSame('json', $columns[$name]->data_type);
            $this->assertSame('YES', $columns[$name]->is_nullable);
        }
        $this->assertStringContainsString('draft', $columns['status']->column_default);
        $existingConstraints = $this->jobConstraints();
        $existingIndexes = $this->jobIndexes();

        $this->migrate([self::COLUMNS_MIGRATION]);
        $afterColumns = $this->snapshot();
        foreach ($afterColumns['jobs'] as &$row) {
            $this->assertNull($row['category']);
            $this->assertNull($row['subcategory']);
            unset($row['category'], $row['subcategory']);
        }
        unset($row);
        $this->assertSame($before, $afterColumns);
        $beforeRelationships = $this->snapshot();

        $this->migrate([self::RELATIONSHIPS_MIGRATION]);

        $this->assertSame($beforeRelationships, $this->snapshot());
        $this->assertSame(4, DB::table('jobs')->count());
        $this->assertSame(5, DB::table('users')->count());
        $this->assertSame(2, DB::table('company_profiles')->count());
        $this->assertSame(2, DB::table('applications')->count());
        $this->assertSame(8, DB::table('job_categories')->count());
        $this->assertSame(26, DB::table('job_subcategories')->count());
        $this->assertSame(0, DB::table('jobs')->whereNotNull('category')->count());
        foreach ($existingConstraints as $name => $definition) {
            $this->assertSame($definition, $this->jobConstraints()[$name]);
        }
        foreach ($existingIndexes as $name => $definition) {
            $this->assertSame($definition, $this->jobIndexes()[$name]);
        }
        $this->assertSame(['draft' => 1, 'pending_review' => 1, 'published' => 2],
            DB::table('jobs')->selectRaw('status, COUNT(*) AS total')->groupBy('status')->orderBy('status')->pluck('total', 'status')->map(fn ($value): int => (int) $value)->all());

        $this->get('/jobs')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('jobs.total', 2)
            ->where('counts.other', 2)
            ->where('jobs.data', fn ($jobs): bool => collect($jobs)->pluck('id')->sort()->values()->all() === $legacyIds
                && collect($jobs)->every(fn ($job): bool => $job['is_other'] === true)));
        $this->assertDatabaseHas('migrations', ['migration' => pathinfo(self::COLUMNS_MIGRATION, PATHINFO_FILENAME)]);
        $this->assertDatabaseHas('migrations', ['migration' => pathinfo(self::RELATIONSHIPS_MIGRATION, PATHINFO_FILENAME)]);
    }

    public function test_existing_assignments_are_preserved_and_unknown_keys_stop_before_schema_changes(): void
    {
        $this->syntheticRecords();
        $this->migrate([self::COLUMNS_MIGRATION]);
        DB::table('jobs')->where('slug', 'pg-upgrade-0')->update([
            'category' => 'finance', 'subcategory' => 'accounting',
        ]);
        DB::table('jobs')->where('slug', 'pg-upgrade-1')->update([
            'category' => 'unknown-existing-key',
        ]);
        $before = $this->snapshot();
        $caught = null;
        try {
            $this->migrate([self::RELATIONSHIPS_MIGRATION]);
        } catch (\RuntimeException $exception) {
            $caught = $exception;
        }
        $this->assertInstanceOf(\RuntimeException::class, $caught);
        $this->assertStringContainsString('mapped explicitly', $caught->getMessage());
        $this->assertSame($before, $this->snapshot());
        $this->assertFalse(Schema::hasTable('job_categories'));
        $this->assertFalse(Schema::hasTable('job_subcategories'));
        $this->assertDatabaseMissing('migrations', ['migration' => pathinfo(self::RELATIONSHIPS_MIGRATION, PATHINFO_FILENAME)]);

        // This explicit mapping is synthetic; the migration itself never guesses.
        DB::table('jobs')->where('slug', 'pg-upgrade-1')->update(['category' => 'education']);
        $mapped = $this->snapshot();
        $this->migrate([self::RELATIONSHIPS_MIGRATION]);
        $this->assertSame($mapped, $this->snapshot());
        $this->assertDatabaseHas('jobs', ['slug' => 'pg-upgrade-0', 'category' => 'finance', 'subcategory' => 'accounting']);
        $this->assertDatabaseHas('jobs', ['slug' => 'pg-upgrade-1', 'category' => 'education', 'subcategory' => null]);
    }

    public function test_deployment_command_refuses_a_concurrent_runner_and_is_repeatable_without_data_changes(): void
    {
        $this->syntheticRecords();
        $before = $this->snapshot();
        $holder = new \PDO(
            'pgsql:host=127.0.0.1;port=55432;dbname=madarat_category_verify;sslmode=disable',
            'postgres',
            '',
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION],
        );
        $bindings = [MigrateJobCategories::LOCK_NAMESPACE, MigrateJobCategories::LOCK_KEY];
        $hold = $holder->prepare('SELECT pg_advisory_lock(?, ?)');
        $hold->execute($bindings);

        try {
            $this->assertSame(1, Artisan::call('madarat:migrate-job-categories', ['--force' => true]));
            $this->assertStringContainsString('Another category migration process', Artisan::output());
            $this->assertFalse(Schema::hasColumn('jobs', 'category'));
            $this->assertFalse(Schema::hasTable('job_categories'));
            $this->assertSame($before, $this->snapshot());
        } finally {
            $release = $holder->prepare('SELECT pg_advisory_unlock(?, ?)');
            $release->execute($bindings);
        }

        $this->assertSame(0, Artisan::call('madarat:migrate-job-categories', ['--force' => true]));
        $this->assertTrue(Schema::hasColumn('jobs', 'category'));
        $this->assertTrue(Schema::hasTable('job_categories'));
        $after = $this->snapshot();
        foreach ($after['jobs'] as &$row) {
            $this->assertNull($row['category']);
            $this->assertNull($row['subcategory']);
            unset($row['category'], $row['subcategory']);
        }
        unset($row);
        $this->assertSame($before, $after);
        $withCategories = $this->snapshot();
        $this->assertSame(0, Artisan::call('madarat:migrate-job-categories', ['--force' => true]));
        $this->assertSame($withCategories, $this->snapshot());
        $this->assertSame(8, DB::table('job_categories')->count());
        $this->assertSame(26, DB::table('job_subcategories')->count());
    }

    private function migrate(array $files): void
    {
        $this->assertSame(0, Artisan::call('migrate', [
            '--database' => 'pgsql',
            '--path' => array_map(fn (string $file): string => 'database/migrations/'.$file, $files),
            '--force' => true,
        ]));
    }

    private function syntheticRecords(): array
    {
        User::factory()->create(['role' => 'admin']);
        User::factory()->create(['role' => 'training_provider']);
        $seeker = User::factory()->create(['role' => 'job_seeker']);
        $companies = [];
        foreach ([0, 1] as $index) {
            $employer = User::factory()->create(['role' => 'employer']);
            $companies[] = CompanyProfile::create([
                'user_id' => $employer->id,
                'company_name' => 'شركة اصطناعية '.$index,
                'verification_status' => 'verified',
            ]);
        }
        $publishedIds = [];
        foreach (['published', 'published', 'pending_review', 'draft'] as $index => $status) {
            $job = Job::create([
                'company_profile_id' => $companies[$index % 2]->id,
                'title' => 'محاسب اصطناعي '.$index,
                'slug' => 'pg-upgrade-'.$index,
                'description' => 'محتوى اصطناعي يجب حفظه حرفيًا أثناء الترقية.',
                'responsibilities' => $index === 1 ? null : ['مهمة أولى', 'مهمة ثانية'],
                'required_skills' => $index === 1 ? null : ['Excel', 'تحليل'],
                'location' => 'طرابلس',
                'salary_min' => 1500,
                'salary_max' => 2500,
                'status' => $status,
            ]);
            if ($status === 'published') {
                $publishedIds[] = $job->id;
                Application::create([
                    'job_id' => $job->id,
                    'user_id' => $seeker->id,
                    'cover_letter' => 'طلب اصطناعي محفوظ.',
                    'match_score' => 75,
                ]);
            }
        }

        return $publishedIds;
    }

    private function snapshot(): array
    {
        return collect(['users', 'company_profiles', 'jobs', 'applications'])
            ->mapWithKeys(fn (string $table): array => [
                $table => DB::table($table)->orderBy('id')->get()->map(fn ($row): array => (array) $row)->all(),
            ])->all();
    }

    private function jobConstraints(): array
    {
        return collect(DB::select("SELECT conname, pg_get_constraintdef(oid) AS definition FROM pg_constraint WHERE conrelid = 'public.jobs'::regclass ORDER BY conname"))
            ->pluck('definition', 'conname')->all();
    }

    private function jobIndexes(): array
    {
        return collect(DB::select("SELECT indexname, indexdef FROM pg_indexes WHERE schemaname = 'public' AND tablename = 'jobs' ORDER BY indexname"))
            ->pluck('indexdef', 'indexname')->all();
    }
}
