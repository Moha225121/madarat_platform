<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\CompanyProfile;
use App\Models\Job;
use App\Models\JobSeekerProfile;
use App\Models\TrainingCourse;
use App\Models\TrainingProviderProfile;
use App\Models\User;
use App\Services\AssistantService;
use App\Services\MockAssistantService;
use App\Services\OpenAiClient;
use App\Services\UploadStorage;
use App\Storage\StoredFileRepository;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class UploadStorageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('public');
        Storage::fake('documents');
        config(['uploads.legacy_cv_reads' => false]);
    }

    public function test_cv_upload_is_private_and_never_written_to_media(): void
    {
        Queue::fake();
        $user = User::factory()->create(['role' => 'job_seeker']);
        $this->actingAs($user)->post('/seeker/cv-analysis', [
            'cv' => UploadedFile::fake()->create('candidate.pdf', 10, 'application/pdf'),
        ])->assertSessionHasNoErrors()->assertRedirect();
        $profile = $user->fresh()->jobSeekerProfile;
        Storage::disk('documents')->assertExists($profile->cv_path);
        $this->assertSame('private', config('filesystems.disks.documents.visibility'));
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertSame('processing', $profile->cv_status);
    }

    public function test_cv_access_checks_owner_exact_admin_and_related_employer(): void
    {
        $owner = User::factory()->create(['role' => 'job_seeker']);
        $profile = JobSeekerProfile::create(['user_id' => $owner->id, 'cv_path' => 'cvs/existing.pdf']);
        Storage::disk('documents')->put($profile->cv_path, '%PDF private example');
        $url = '/files/cv/'.$profile->id;
        $this->get($url)->assertRedirect('/login');
        foreach (['job_seeker', 'employer', 'training_provider', 'administrator'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get($url)->assertForbidden();
        }
        $this->actingAs($owner)->get($url)->assertOk()->assertDownload('cv.pdf')
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertStreamedContent('%PDF private example');
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get($url)->assertOk()->assertStreamedContent('%PDF private example');
        $employer = User::factory()->create(['role' => 'employer']);
        $company = CompanyProfile::create(['user_id' => $employer->id, 'company_name' => 'Synthetic company']);
        $job = Job::create(['company_profile_id' => $company->id, 'title' => 'Job', 'slug' => 'upload-access', 'description' => 'Description']);
        Application::create(['job_id' => $job->id, 'user_id' => $owner->id]);
        $this->actingAs($employer)->get($url)->assertOk()->assertStreamedContent('%PDF private example');
        $profile->update(['cv_path' => 'cvs/../../outside.pdf']);
        $this->get($url)->assertNotFound();
    }

    public function test_old_cv_paths_are_preserved_after_private_copy_and_missing_files_return_404(): void
    {
        $owner = User::factory()->create(['role' => 'job_seeker']);
        $profile = JobSeekerProfile::create(['user_id' => $owner->id, 'cv_path' => 'cvs/old.docx']);
        Storage::disk('public')->put($profile->cv_path, 'public legacy');
        $this->actingAs($owner)->get('/files/cv/'.$profile->id)->assertNotFound();
        Storage::disk('documents')->put($profile->cv_path, 'verified private copy');
        $this->get('/files/cv/'.$profile->id)->assertOk()->assertStreamedContent('verified private copy');
        $this->assertSame('cvs/old.docx', $profile->fresh()->cv_path);
        $this->get('/storage/cvs/old.docx')->assertNotFound();
        $this->get('/media/cvs/old.docx')->assertNotFound();
        Storage::disk('public')->assertExists('cvs/old.docx');
    }

    public function test_media_and_legacy_urls_stream_only_allowed_images(): void
    {
        Storage::disk('public')->put('company-logos/old.png', 'image bytes');
        foreach (['/media/', '/storage/'] as $prefix) {
            $this->get($prefix.'company-logos/old.png')->assertOk()
                ->assertHeader('X-Content-Type-Options', 'nosniff')->assertStreamedContent('image bytes');
            foreach (['cvs/secret.pdf', 'company-logos/evil.svg', 'company-logos/nested/file.png', 'company-logos/%252e%252e.png', 'company-logos/missing.png'] as $path) {
                $this->get($prefix.$path)->assertNotFound();
            }
        }
    }

    public function test_every_image_upload_path_uses_private_media_storage(): void
    {
        $employer = User::factory()->create(['role' => 'employer']);
        $this->actingAs($employer)->post('/employer/company', [
            'company_name' => 'Test', 'logo' => UploadedFile::fake()->image('logo.png'),
        ])->assertSessionHasNoErrors();
        $companyPath = $employer->fresh()->companyProfile->logo_path;
        $provider = TrainingProviderProfile::factory()->create();
        $this->actingAs($provider->user)->post('/training/profile', [
            'provider_type' => 'company', 'display_name' => 'Test', 'description' => 'Description',
            'email' => 'test@example.test', 'phone' => '123', 'city' => 'Tripoli', 'specializations' => ['PHP'],
            'logo' => UploadedFile::fake()->image('logo.png'), 'profile_image' => UploadedFile::fake()->image('photo.png'),
        ])->assertSessionHasNoErrors();
        $this->post('/training/courses', [
            'title' => 'Course', 'description' => 'Description', 'skills_taught' => 'PHP',
            'difficulty_level' => 'beginner', 'delivery_method' => 'online', 'is_remote' => true,
            'cover_image' => UploadedFile::fake()->image('course.png'),
        ])->assertSessionHasNoErrors();
        $paths = [$companyPath, $provider->fresh()->logo_path, $provider->fresh()->profile_image_path,
            TrainingCourse::where('training_provider_id', $provider->id)->firstOrFail()->cover_image_path];
        foreach ($paths as $path) {
            $this->assertNotEmpty($path);
            Storage::disk('public')->assertExists($path);
            $this->assertSame('private', config('filesystems.disks.public.visibility'));
            $this->get('/media/'.$path)->assertOk();
        }
        $this->assertSame([], Storage::disk('documents')->allFiles());
    }

    public function test_remote_cv_reader_uses_a_stream_without_requesting_a_local_disk_path_and_cleans_up_on_exception(): void
    {
        $disk = Mockery::mock(Filesystem::class);
        $source = fopen('php://temp', 'w+b');
        fwrite($source, '%PDF remote bytes');
        rewind($source);
        $disk->shouldReceive('exists')->once()->with('cvs/remote.pdf')->andReturnTrue();
        $disk->shouldReceive('readStream')->once()->with('cvs/remote.pdf')->andReturn($source);
        Storage::shouldReceive('disk')->once()->with('documents')->andReturn($disk);
        $temporaryPath = null;
        try {
            app(UploadStorage::class)->withCvFile('cvs/remote.pdf', function ($path) use (&$temporaryPath): void {
                $temporaryPath = $path;
                $this->assertSame('%PDF remote bytes', file_get_contents($path));
                throw new RuntimeException('Synthetic callback failure');
            });
            $this->fail('Expected the callback to fail.');
        } catch (RuntimeException $error) {
            $this->assertSame('Synthetic callback failure', $error->getMessage());
        }
        $this->assertNotNull($temporaryPath);
        $this->assertFileDoesNotExist($temporaryPath);
        $this->assertFalse(is_resource($source));
    }

    public function test_release_configuration_check_rejects_container_storage_and_legacy_cv_reads(): void
    {
        config(['filesystems.disks.public.driver' => 'local']);
        $this->artisan('uploads:check')->assertExitCode(1);
        config(['filesystems.disks.public.driver' => 'database', 'uploads.legacy_cv_reads' => true]);
        $this->artisan('uploads:check')->assertExitCode(1);
    }

    public function test_assistant_reads_the_private_cv_and_removes_its_temporary_file(): void
    {
        $owner = User::factory()->create(['role' => 'job_seeker']);
        $profile = JobSeekerProfile::create(['user_id' => $owner->id, 'cv_path' => 'cvs/assistant.pdf']);
        Storage::disk('documents')->put($profile->cv_path, '%PDF private assistant test');
        $client = Mockery::mock(OpenAiClient::class);
        $client->shouldReceive('isConfigured')->once()->andReturnTrue();
        $temporaryPath = null;
        $client->shouldReceive('textWithStoredFile')->once()->withArgs(function ($instructions, $path, $filename, $mime, $input) use (&$temporaryPath) {
            $temporaryPath = $path;
            $this->assertSame('%PDF private assistant test', file_get_contents($path));
            $this->assertSame('assistant.pdf', $filename);
            $this->assertSame('application/pdf', $mime);

            return true;
        })->andReturn('Private CV read successfully');
        $assistant = new AssistantService($client, app(MockAssistantService::class));
        $context = $assistant->contextFor($owner);
        $this->assertTrue($context['job_seeker_profile']['cv']['available']);
        $this->assertSame('documents', $context['job_seeker_profile']['cv']['disk']);
        $this->assertSame('Private CV read successfully', $assistant->respond('Synthetic question', $context));
        $this->assertNotNull($temporaryPath);
        $this->assertFileDoesNotExist($temporaryPath);
        Storage::disk('public')->assertMissing('cvs/assistant.pdf');
    }

    public function test_database_disks_store_all_six_upload_paths_with_ownership_and_verified_bytes(): void
    {
        Storage::forgetDisk(['public', 'documents']);
        Queue::fake();
        $owner = User::factory()->create(['role' => 'job_seeker']);
        $this->actingAs($owner)->post('/seeker/cv-analysis', [
            'cv' => UploadedFile::fake()->createWithContent('candidate.pdf', "%PDF-1.7\nSynthetic PDF bytes\0\xff"),
        ])->assertRedirect()->assertSessionHasNoErrors();
        $employer = User::factory()->create(['role' => 'employer']);
        $this->actingAs($employer)->post('/employer/company', [
            'company_name' => 'Test', 'logo' => UploadedFile::fake()->image('company.png'),
        ])->assertSessionHasNoErrors();
        $provider = TrainingProviderProfile::factory()->create();
        $this->actingAs($provider->user)->post('/training/profile', [
            'provider_type' => 'company', 'display_name' => 'Test', 'description' => 'Description',
            'email' => 'test@example.test', 'phone' => '123', 'city' => 'Tripoli', 'specializations' => ['PHP'],
            'logo' => UploadedFile::fake()->image('logo.png'), 'profile_image' => UploadedFile::fake()->image('photo.png'),
        ])->assertSessionHasNoErrors();
        $this->post('/training/courses', ['title' => 'Course', 'description' => 'Description', 'skills_taught' => 'PHP',
            'difficulty_level' => 'beginner', 'delivery_method' => 'online', 'is_remote' => true,
            'cover_image' => UploadedFile::fake()->image('cover.png'),
        ])->assertSessionHasNoErrors();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->post('/admin/advertisements', [
            'image' => UploadedFile::fake()->image('advertisement.png'),
        ])->assertSessionHas('success');
        $rows = DB::table('stored_files')->select(explode(',', StoredFileRepository::METADATA))->orderBy('id')->get();
        $this->assertCount(6, $rows);
        $this->assertSame('documents', $rows[0]->disk);
        foreach ($rows as $row) {
            $this->assertNotNull($row->owner_user_id);
            $stream = Storage::disk($row->disk)->readStream($row->path);
            $contents = stream_get_contents($stream);
            fclose($stream);
            $this->assertSame((int) $row->size, strlen($contents));
            $this->assertSame($row->sha256, hash('sha256', $contents));
            $this->assertArrayNotHasKey('contents', (array) $row);
        }
    }
}
