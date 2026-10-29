<?php

namespace Tests\StoragePostgres;

use App\Http\Controllers\AdminJobCategoryController;
use App\Jobs\AnalyzeCv;
use App\Models\CompanyProfile;
use App\Models\Job;
use App\Models\JobCategory;
use App\Models\JobSeekerProfile;
use App\Models\User;
use App\Services\CvAnalysisService;
use App\Storage\StoredFileRepository;
use App\Storage\StoredFileRescue;
use App\Storage\StoredFileSchema;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\Flysystem\UnableToWriteFile;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

require_once __DIR__.'/../../tools/uploads/snapshot.php';
require_once __DIR__.'/../../tools/uploads/compare.php';

class DatabaseStorageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('testing', app()->environment());
        $this->assertSame('pgsql', config('database.default'));
        foreach (['host' => '127.0.0.1', 'port' => '55432', 'database' => 'madarat_upload_verify'] as $key => $expected) {
            $this->assertSame($expected, (string) config('database.connections.pgsql.'.$key), 'Never reset any other database.');
        }
        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
        $this->withoutVite();
        Storage::forgetDisk(['public', 'documents']);
    }

    public function test_bytea_round_trips_all_bytes_and_downloads_pdf_doc_docx_and_images(): void
    {
        $this->assertSame('bytea', DB::selectOne("SELECT udt_name FROM information_schema.columns WHERE table_schema='public' AND table_name='stored_files' AND column_name='contents'")->udt_name);
        StoredFileSchema::verifyWithLedger(DB::connection()->getPdo());
        $this->artisan('uploads:check')->assertExitCode(0);
        Queue::fake();
        $owner = User::factory()->create(['role' => 'job_seeker']);
        $allBytes = implode('', array_map('chr', range(0, 255)));
        foreach (['pdf' => 'application/pdf', 'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'] as $extension => $mime) {
            $file = UploadedFile::fake()->create('synthetic.'.$extension, 1, $mime);
            file_put_contents($file->getPathname(), '%Synthetic '.$extension."\n".$allBytes);
            $sourceHash = hash_file('sha256', $file->getPathname());
            $sourceBytes = file_get_contents($file->getPathname());
            $this->actingAs($owner)->post('/seeker/cv-analysis', ['cv' => $file])->assertRedirect()->assertSessionHasNoErrors();
            $profile = $owner->fresh()->jobSeekerProfile;
            $row = DB::table('stored_files')->select(explode(',', StoredFileRepository::METADATA))->where('disk', 'documents')->where('path', $profile->cv_path)->first();
            $this->assertSame($sourceHash, $row->sha256);
            $this->assertSame('synthetic.'.$extension, $row->original_name);
            $this->assertSame($owner->id, $row->owner_user_id);
            $this->get('/files/cv/'.$profile->id)->assertOk()->assertDownload('synthetic.'.$extension)->assertStreamedContent($sourceBytes);
            $profile->update(['cv_status' => 'failed']);
        }
        $image = UploadedFile::fake()->image('synthetic.png');
        $contents = file_get_contents($image->getPathname());
        $employer = User::factory()->create(['role' => 'employer']);
        $this->actingAs($employer)->post('/employer/company', ['company_name' => 'Synthetic', 'logo' => $image])->assertRedirect()->assertSessionHasNoErrors();
        $path = $employer->fresh()->companyProfile->logo_path;
        $this->get('/media/'.$path)->assertOk()->assertStreamedContent($contents);
        $this->get('/storage/'.$path)->assertOk()->assertStreamedContent($contents);
        $metadata = (new StoredFileRepository(DB::connection()->getPdo()))->metadata('public', $path);
        $this->assertArrayNotHasKey('contents', $metadata);
        $this->getJson('/jobs')->assertOk()->assertDontSee('original_name')->assertDontSee('contents');
    }

    public function test_private_ownership_cannot_be_bypassed_by_pointing_another_profile_at_a_known_path(): void
    {
        $owner = User::factory()->create(['role' => 'job_seeker']);
        $stranger = User::factory()->create(['role' => 'job_seeker']);
        $profile = JobSeekerProfile::create(['user_id' => $owner->id, 'cv_path' => 'cvs/private.pdf']);
        $poisoned = JobSeekerProfile::create(['user_id' => $stranger->id, 'cv_path' => $profile->cv_path]);
        Storage::disk('documents')->put($profile->cv_path, "%PDF private\0\xff", ['visibility' => 'private', 'owner_user_id' => $owner->id, 'mime_type' => 'application/pdf']);
        $this->get('/files/cv/'.$profile->id)->assertRedirect('/login');
        $this->actingAs($stranger)->get('/files/cv/'.$profile->id)->assertForbidden();
        $this->get('/files/cv/'.$poisoned->id)->assertNotFound();
        $this->get('/storage/cvs/private.pdf')->assertNotFound();
        $this->get('/media/cvs/private.pdf')->assertNotFound();
        $this->actingAs($owner)->get('/files/cv/'.$profile->id)->assertOk()->assertStreamedContent("%PDF private\0\xff");
    }

    public function test_cv_analysis_reads_database_bytes_and_removes_its_temporary_file(): void
    {
        $owner = User::factory()->create(['role' => 'job_seeker']);
        $attempt = (string) Str::uuid();
        $profile = JobSeekerProfile::create(['user_id' => $owner->id, 'cv_path' => 'cvs/analysis.pdf', 'cv_status' => 'processing', 'cv_analysis_attempt_id' => $attempt]);
        $bytes = "%PDF database analysis\0\xff";
        Storage::disk('documents')->put($profile->cv_path, $bytes, ['visibility' => 'private', 'owner_user_id' => $owner->id, 'mime_type' => 'application/pdf']);
        $temporaryPath = null;
        $service = \Mockery::mock(CvAnalysisService::class);
        $service->shouldReceive('analyze')->once()->withArgs(function ($file, $selected) use (&$temporaryPath, $bytes, $profile) {
            $temporaryPath = $file->getRealPath();
            $this->assertSame($bytes, file_get_contents($temporaryPath));
            $this->assertSame('synthetic.pdf', $file->getClientOriginalName());

            return $selected->id === $profile->id;
        })->andReturn(['score' => 88, 'extracted_skills' => ['SQL'], 'missing_skills' => [], 'education_summary' => 'Synthetic', 'experience_summary' => 'Synthetic', 'strengths' => [], 'recommendations' => []]);
        (new AnalyzeCv($profile->id, $profile->cv_path, 'synthetic.pdf', $attempt))->handle($service);
        $this->assertSame('analyzed', $profile->fresh()->cv_status);
        $this->assertSame(88, $profile->fresh()->profile_score);
        $this->assertFileDoesNotExist($temporaryPath);
    }

    public function test_configurable_size_and_type_limits_reject_without_creating_rows(): void
    {
        Queue::fake();
        config(['uploads.limits_kb.cv' => 1, 'uploads.limits_kb.image' => 1]);
        $owner = User::factory()->create(['role' => 'job_seeker']);
        $this->actingAs($owner)->post('/seeker/cv-analysis', ['cv' => UploadedFile::fake()->create('large.pdf', 2, 'application/pdf')])->assertSessionHasErrors('cv');
        $this->post('/seeker/cv-analysis', ['cv' => UploadedFile::fake()->create('bad.txt', 1, 'text/plain')])->assertSessionHasErrors('cv');
        $this->assertSame(0, DB::table('stored_files')->count());
        try {
            Storage::disk('documents')->put('cvs/too-large.pdf', str_repeat('x', 1025), ['visibility' => 'private']);
            $this->fail('Backend must also enforce the limit.');
        } catch (UnableToWriteFile) {
            $this->assertSame(0, DB::table('stored_files')->count());
        }
        Queue::assertNothingPushed();
    }

    public function test_independent_application_processes_read_after_restart_with_empty_local_storage(): void
    {
        $root = sys_get_temp_dir().'/madarat-upload-processes-'.bin2hex(random_bytes(10));
        mkdir($root, 0700);
        $bytes = "%PDF-1.7\n".random_bytes(1024 * 1024);
        file_put_contents($root.'/synthetic.pdf', $bytes);
        $path = 'cvs/processes.pdf';
        $results = [];
        try {
            foreach (['put', 'get', 'get'] as $index => $mode) {
                $storage = $root.'/empty-'.$index;
                mkdir($storage, 0700);
                $process = new Process([PHP_BINARY, '-d', 'extension=pdo_pgsql', base_path('tests/Support/database_upload_probe.php'), $mode, $path, $storage, $root.'/synthetic.pdf'], base_path(), $this->probeEnvironment());
                $process->setTimeout(60)->mustRun();
                $result = json_decode(trim($process->getOutput()), true, flags: JSON_THROW_ON_ERROR);
                $this->assertSame(hash('sha256', $bytes), $result['sha256']);
                $this->assertSame(strlen($bytes), $result['bytes']);
                $this->assertSame('database', $result['driver']);
                $this->assertSame(0, $result['local_files']);
                $results[] = $result;
            }
            $this->assertCount(3, array_unique(array_column($results, 'pid')));
        } finally {
            (new Filesystem)->deleteDirectory($root);
        }
    }

    public function test_standalone_rescue_is_idempotent_and_conflicts_keep_both_source_copies(): void
    {
        $pdo = DB::connection()->getPdo();
        $this->assertFalse(StoredFileSchema::prepareWithLedger($pdo));
        $seeker = User::factory()->create(['role' => 'job_seeker']);
        $profile = JobSeekerProfile::create(['user_id' => $seeker->id, 'cv_path' => 'cvs/old.pdf']);
        $references = [['table' => 'job_seeker_profiles', 'id' => $profile->id, 'column' => 'cv_path', 'disk' => 'public', 'path' => 'cvs/old.pdf']];
        $root = sys_get_temp_dir().'/madarat-rescue-synthetic-'.bin2hex(random_bytes(12));
        mkdir($root, 0700);
        $rescue = new StoredFileRescue($pdo);
        try {
            $runs = [];
            foreach (['instance-a', 'instance-b'] as $instance) {
                $source = $root.'/'.$instance;
                mkdir($source.'/storage/app/public/cvs', 0700, true);
                mkdir($source.'/storage/app/private', 0700, true);
                file_put_contents($source.'/storage/app/public/cvs/old.pdf', "%PDF shared\0\xff");
                file_put_contents($source.'/storage/app/private/.gitignore', 'placeholder');
                file_put_contents($source.'/storage/app/private/empty', '');
                $snapshot = \MadaratUploadSnapshot::create($source, $root, $references);
                $manifest = json_decode(file_get_contents($snapshot['directory'].'/manifest.json'), true);
                $manifest['instance_tag'] = $instance;
                file_put_contents($snapshot['directory'].'/manifest.json', json_encode($manifest));
                $run = $rescue->preserve($snapshot['directory']);
                $again = $rescue->preserve($snapshot['directory']);
                $this->assertSame($run['run'], $again['run']);
                $this->assertSame(0, $again['new_copies']);
                $this->assertSame(3, $again['verified_existing_copies']);
                $runs[] = $run['run'];
            }
            $this->assertSame(6, DB::table('stored_files')->where('disk', 'preserved')->count());
            $dryRun = $rescue->reconcile($runs, ['instance-a', 'instance-b'], $references);
            $this->assertTrue($dryRun['ready']);
            $this->assertFalse($dryRun['applied']);
            $this->assertSame(0, DB::table('stored_files')->where('disk', 'documents')->count());
            $applied = $rescue->reconcile($runs, ['instance-a', 'instance-b'], $references, true);
            $this->assertTrue($applied['applied']);
            $this->actingAs($seeker)->get('/files/cv/'.$profile->id)->assertOk()->assertStreamedContent("%PDF shared\0\xff");
            $this->assertSame('cvs/old.pdf', $profile->fresh()->cv_path);
            file_put_contents($root.'/instance-b/storage/app/public/cvs/old.pdf', '%PDF different');
            $new = \MadaratUploadSnapshot::create($root.'/instance-b', $root, $references);
            $manifest = json_decode(file_get_contents($new['directory'].'/manifest.json'), true);
            $manifest['instance_tag'] = 'instance-b';
            file_put_contents($new['directory'].'/manifest.json', json_encode($manifest));
            $changed = $rescue->preserve($new['directory']);
            $conflict = $rescue->reconcile([$runs[0], $changed['run']], ['instance-a', 'instance-b'], $references, true);
            $this->assertFalse($conflict['applied']);
            $this->assertCount(1, $conflict['report']['conflicts']);
            $this->assertSame(7, DB::table('stored_files')->where('disk', 'preserved')->count());
            $this->assertSame('%PDF different', file_get_contents($root.'/instance-b/storage/app/public/cvs/old.pdf'));
            $this->get('/files/cv/'.$profile->id)->assertOk()->assertStreamedContent("%PDF shared\0\xff");
            $missing = $rescue->reconcile($runs, ['instance-a', 'instance-b'], [...$references, ['table' => 'job_seeker_profiles', 'id' => 999, 'column' => 'cv_path', 'disk' => 'public', 'path' => 'cvs/missing.pdf']]);
            $this->assertFalse($missing['ready']);
            $this->assertCount(1, $missing['report']['missing_references']);
        } finally {
            (new Filesystem)->deleteDirectory($root);
        }
    }

    public function test_schema_drift_is_not_hidden_by_the_ledger_or_rescue_tool(): void
    {
        DB::statement('ALTER TABLE stored_files ADD COLUMN unexpected text');
        $before = DB::table('migrations')->get()->toJson();
        try {
            StoredFileSchema::prepareWithLedger(DB::connection()->getPdo());
            $this->fail('Schema drift must stop preparation.');
        } catch (RuntimeException) {
            $this->assertSame($before, DB::table('migrations')->get()->toJson());
            $this->assertSame(1, DB::table('migrations')->where('migration', StoredFileSchema::MIGRATION)->count());
        }
    }

    public function test_standalone_tool_runs_against_an_old_runtime_before_deployment_and_dry_run_writes_nothing(): void
    {
        $root = sys_get_temp_dir().'/madarat-old-runtime-synthetic-'.bin2hex(random_bytes(12));
        mkdir($root.'/vendor', 0700, true);
        mkdir($root.'/bootstrap', 0700);
        mkdir($root.'/config', 0700);
        mkdir($root.'/storage/app/public/cvs', 0700, true);
        mkdir($root.'/storage/app/private', 0700, true);
        file_put_contents($root.'/vendor/autoload.php', '<?php return require '.var_export(base_path('vendor/autoload.php'), true).';');
        file_put_contents($root.'/bootstrap/app.php', '<?php return new Illuminate\\Foundation\\Application(dirname(__DIR__));');
        copy(config_path('database.php'), $root.'/config/database.php');
        file_put_contents($root.'/config/app.php', '<?php return ["env"=>env("APP_ENV","testing")];');
        file_put_contents($root.'/storage/app/public/cvs/old.pdf', "%PDF standalone bytes\0\xff");
        file_put_contents($root.'/storage/app/private/.gitignore', 'placeholder');
        $pdo = DB::connection()->getPdo();
        $pdo->exec('DROP TABLE stored_file_import_runs');
        $pdo->exec('DROP TABLE stored_files');
        DB::table('migrations')->where('migration', StoredFileSchema::MIGRATION)->delete();
        $execute = function (array $arguments) use ($root): array {
            $process = new Process([PHP_BINARY, '-d', 'extension=pdo_pgsql', base_path('tools/uploads/database-rescue.php'), '--source='.$root, ...$arguments], $root, $this->probeEnvironment());
            $process->setTimeout(60)->mustRun();

            return json_decode(trim($process->getOutput()), true, flags: JSON_THROW_ON_ERROR);
        };
        try {
            $dry = $execute(['--action=prepare']);
            $this->assertTrue($dry['dry_run']);
            $this->assertTrue($dry['would_create']);
            $this->assertNull(DB::selectOne("SELECT to_regclass('public.stored_files') AS name")->name);
            $prepared = $execute(['--action=prepare', '--apply', '--approved-production-write']);
            $this->assertTrue($prepared['created']);
            $this->assertFalse($execute(['--action=prepare', '--apply', '--approved-production-write'])['created']);
            $scan = $execute([]);
            $this->assertTrue($scan['dry_run']);
            $this->assertSame(2, $scan['files']);
            $execute(['--action=import']);
            $this->assertSame(0, DB::table('stored_files')->count());
            $copied = $execute(['--action=import', '--apply', '--approved-production-write']);
            $this->assertSame(2, $copied['new_copies']);
            $repeat = $execute(['--action=import', '--apply', '--approved-production-write']);
            $this->assertSame($copied['run'], $repeat['run']);
            $this->assertSame(0, $repeat['new_copies']);
            $this->assertSame(2, DB::table('stored_files')->where('disk', 'preserved')->count());
            $this->artisan('migrate', ['--path' => 'database/migrations/'.StoredFileSchema::MIGRATION.'.php', '--force' => true])->assertExitCode(0);
            $this->assertSame(1, DB::table('migrations')->where('migration', StoredFileSchema::MIGRATION)->count());
            $this->assertSame(2, DB::table('stored_files')->count());
        } finally {
            (new Filesystem)->deleteDirectory($root);
        }
    }

    public function test_category_management_job_creation_and_publication_still_work_with_database_uploads(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $employer = User::factory()->create(['role' => 'employer']);
        CompanyProfile::create(['user_id' => $employer->id, 'company_name' => 'Synthetic', 'verification_status' => 'verified']);
        $this->actingAs($admin)->post('/admin/job-categories', ['name' => 'فئة اختبار تخزين'])->assertRedirect()->assertSessionHasNoErrors();
        $category = JobCategory::where('name_ar', 'فئة اختبار تخزين')->firstOrFail();
        $this->actingAs($employer)->post('/employer/jobs', ['title' => 'Synthetic job', 'description' => 'Preserved content', 'category' => $category->key, 'status' => 'published'])->assertRedirect()->assertSessionHasNoErrors();
        $job = Job::where('title', 'Synthetic job')->firstOrFail();
        $this->assertSame('pending_review', $job->status);
        $this->assertSame($category->key, $job->category);
        $this->actingAs($admin)->get('/admin/jobs/'.$job->id.'/review')->assertOk();
        $this->post('/admin/jobs/'.$job->id.'/approve')->assertRedirect();
        $this->assertSame('published', $job->fresh()->status);
        $this->get('/jobs/'.$job->slug)->assertOk()->assertSee('Synthetic job');
        $this->delete('/admin/job-categories/'.$category->id)->assertRedirect()->assertSessionHas('error', fn ($message) => str_contains($message, AdminJobCategoryController::LINKED_JOBS_MESSAGE));
        $this->assertTrue($category->fresh()->exists);
    }

    private function probeEnvironment(): array
    {
        return ['APP_ENV' => 'testing', 'APP_KEY' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
            'DB_CONNECTION' => 'pgsql', 'DB_HOST' => '127.0.0.1', 'DB_PORT' => '55432', 'DB_DATABASE' => 'madarat_upload_verify',
            'DB_USERNAME' => 'postgres', 'DB_PASSWORD' => '', 'DB_URL' => '', 'DB_SSLMODE' => 'disable',
            'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array', 'LOG_CHANNEL' => 'null'];
    }
}
