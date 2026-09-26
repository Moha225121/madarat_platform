<?php

namespace Tests\Feature;

use App\Exceptions\OpenAiException;
use App\Jobs\AnalyzeCv;
use App\Models\CompanyProfile;
use App\Models\Job;
use App\Models\JobSeekerProfile;
use App\Models\User;
use App\Services\CvAnalysisService;
use App\Services\MatchingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CvAnalysisTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Http::preventStrayRequests();
        config([
            'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
            'services.openai.key' => 'test-key-never-sent-to-a-real-provider',
            'services.openai.model' => 'cv-feature-test-model',
            'services.openai.base_url' => 'https://openai.test/v1',
            'services.openai.connect_timeout' => 2,
            'services.openai.timeout' => 3,
            'services.openai.file_timeout' => 4,
        ]);
    }

    public function test_job_seeker_upload_stores_file_sets_processing_and_dispatches_exactly_one_job(): void
    {
        Queue::fake();
        [$seeker, $profile] = $this->createSeeker([
            'cv_status' => 'failed',
            'cv_analysis_error_code' => OpenAiException::TIMEOUT,
            'cv_analysis_error_message' => 'رسالة فشل آمنة سابقة',
            'cv_analysis_completed_at' => now()->subMinute(),
        ]);

        $response = $this->actingAs($seeker)->post('/seeker/cv-analysis', [
            'cv' => UploadedFile::fake()->create('candidate.pdf', 100, 'application/pdf'),
        ]);

        $response->assertRedirect()->assertSessionHas('success', 'تم رفع السيرة الذاتية وبدأ تحليلها.');
        $profile->refresh();

        $this->assertSame('processing', $profile->cv_status);
        $this->assertNotNull($profile->cv_analysis_attempt_id);
        $this->assertTrue(Str::isUuid($profile->cv_analysis_attempt_id));
        $this->assertNull($profile->cv_analysis_error_code);
        $this->assertNull($profile->cv_analysis_error_message);
        $this->assertNotNull($profile->cv_analysis_started_at);
        $this->assertNull($profile->cv_analysis_completed_at);
        Storage::disk('public')->assertExists($profile->cv_path);

        Queue::assertPushed(AnalyzeCv::class, function (AnalyzeCv $job) use ($profile): bool {
            $this->assertInstanceOf(ShouldQueue::class, $job);
            $this->assertSame($profile->id, $job->profileId);
            $this->assertSame($profile->cv_path, $job->path);
            $this->assertSame('candidate.pdf', $job->originalName);
            $this->assertSame($profile->cv_analysis_attempt_id, $job->attemptId);

            return true;
        });
        Queue::assertPushedTimes(AnalyzeCv::class, 1);
    }

    public function test_guest_cannot_upload_a_cv(): void
    {
        Queue::fake();

        $this->post('/seeker/cv-analysis', [
            'cv' => UploadedFile::fake()->create('candidate.pdf', 10, 'application/pdf'),
        ])->assertRedirect('/login');

        Queue::assertNothingPushed();
        $this->assertSame([], Storage::disk('public')->allFiles('cvs'));
    }

    /** @return array<string, array{string}> */
    public static function unauthorizedRoleCases(): array
    {
        return [
            'administrator' => ['admin'],
            'employer' => ['employer'],
            'training provider' => ['training_provider'],
        ];
    }

    #[DataProvider('unauthorizedRoleCases')]
    public function test_non_job_seeker_roles_cannot_upload_a_cv(string $role): void
    {
        Queue::fake();
        $user = User::factory()->create(['role' => $role]);

        $this->actingAs($user)->post('/seeker/cv-analysis', [
            'cv' => UploadedFile::fake()->create('candidate.pdf', 10, 'application/pdf'),
        ])->assertForbidden();

        Queue::assertNothingPushed();
        $this->assertSame([], Storage::disk('public')->allFiles('cvs'));
    }

    public function test_invalid_mismatched_and_oversized_cv_files_are_rejected_without_storage_or_dispatch(): void
    {
        Queue::fake();
        [$seeker] = $this->createSeeker();

        $this->actingAs($seeker)->post('/seeker/cv-analysis', [
            'cv' => UploadedFile::fake()->create('candidate.txt', 10, 'text/plain'),
        ])->assertSessionHasErrors('cv');

        $this->actingAs($seeker)->post('/seeker/cv-analysis', [
            'cv' => UploadedFile::fake()->create('candidate.pdf', 15361, 'application/pdf'),
        ])->assertSessionHasErrors('cv');

        $this->actingAs($seeker)->post('/seeker/cv-analysis', [
            'cv' => UploadedFile::fake()->create('renamed.pdf', 10, 'application/msword'),
        ])->assertSessionHasErrors([
            'cv' => 'يجب أن يتطابق امتداد ملف السيرة الذاتية مع نوعه الفعلي.',
        ]);

        Queue::assertNothingPushed();
        $this->assertSame([], Storage::disk('public')->allFiles('cvs'));
    }

    public function test_duplicate_upload_while_processing_is_rejected_and_the_new_orphan_is_removed(): void
    {
        Queue::fake();
        [$seeker, $profile] = $this->createSeeker();

        $this->actingAs($seeker)->post('/seeker/cv-analysis', [
            'cv' => UploadedFile::fake()->create('first.pdf', 10, 'application/pdf'),
        ])->assertRedirect();
        $profile->refresh();
        $firstPath = $profile->cv_path;
        $firstAttempt = $profile->cv_analysis_attempt_id;

        $this->actingAs($seeker)->post('/seeker/cv-analysis', [
            'cv' => UploadedFile::fake()->create('duplicate.docx', 10, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        ])->assertSessionHasErrors([
            'cv' => 'يوجد تحليل قيد التنفيذ حاليًا. انتظر اكتماله قبل رفع سيرة أخرى.',
        ]);

        $profile->refresh();
        $this->assertSame($firstPath, $profile->cv_path);
        $this->assertSame($firstAttempt, $profile->cv_analysis_attempt_id);
        $this->assertSame('processing', $profile->cv_status);
        $this->assertSame([$firstPath], Storage::disk('public')->allFiles('cvs'));
        Queue::assertPushedTimes(AnalyzeCv::class, 1);
    }

    public function test_a_finally_failed_attempt_can_be_retried(): void
    {
        Queue::fake();
        [$seeker, $profile] = $this->createSeeker([
            'cv_path' => 'cvs/failed.pdf',
            'cv_status' => 'failed',
            'cv_analysis_attempt_id' => (string) Str::uuid(),
            'cv_analysis_error_code' => OpenAiException::PROVIDER_UNAVAILABLE,
            'cv_analysis_error_message' => 'رسالة فشل آمنة',
            'cv_analysis_completed_at' => now(),
        ]);
        $oldAttempt = $profile->cv_analysis_attempt_id;

        $this->actingAs($seeker)->post('/seeker/cv-analysis', [
            'cv' => UploadedFile::fake()->create('retry.doc', 10, 'application/msword'),
        ])->assertRedirect();

        $profile->refresh();
        $this->assertSame('processing', $profile->cv_status);
        $this->assertNotSame($oldAttempt, $profile->cv_analysis_attempt_id);
        $this->assertNull($profile->cv_analysis_error_code);
        $this->assertNull($profile->cv_analysis_error_message);
        Queue::assertPushedTimes(AnalyzeCv::class, 1);
    }

    /** @return array<string, array{string}> */
    public static function successfulFileCases(): array
    {
        return [
            'PDF' => ['pdf'],
            'DOC' => ['doc'],
            'DOCX' => ['docx'],
        ];
    }

    #[DataProvider('successfulFileCases')]
    public function test_successful_job_persists_all_real_fields_for_each_supported_file_type(string $extension): void
    {
        [$profile, $job] = $this->currentJob($extension);
        $providerResult = $this->validProviderResult();
        Http::fake(['*' => Http::response(['output_text' => json_encode($providerResult, JSON_UNESCAPED_UNICODE)])]);
        $job->withFakeQueueInteractions();

        $job->handle(app(CvAnalysisService::class));

        $job->assertNotFailed()->assertNotReleased();
        $profile->refresh();
        $this->assertSame('analyzed', $profile->cv_status);
        $this->assertSame(87, $profile->profile_score);
        $this->assertSame(['Laravel', 'PHP'], $profile->extracted_skills);
        $this->assertSame(['TypeScript'], $profile->missing_skills);
        $this->assertSame('بكالوريوس في علوم الحاسوب', $profile->education_summary);
        $this->assertSame('خبرة في تطوير تطبيقات الويب', $profile->experience_summary);
        $this->assertSame([
            'strengths' => ['حل المشكلات', 'التعاون'],
            'recommendations' => ['تعلم TypeScript', 'إضافة نتائج قابلة للقياس'],
        ], $profile->ai_recommendations);
        $this->assertNull($profile->cv_analysis_error_code);
        $this->assertNull($profile->cv_analysis_error_message);
        $this->assertNotNull($profile->cv_analysis_completed_at);
        Storage::disk('public')->assertExists($profile->cv_path);
        Http::assertSentCount(1);
    }

    public function test_missing_configuration_becomes_a_safe_final_failure_without_erasing_the_cv(): void
    {
        config(['services.openai.key' => null]);
        Http::fake();
        [$profile, $job] = $this->currentJob('pdf');
        $job->withFakeQueueInteractions();

        $job->handle(app(CvAnalysisService::class));

        $job->assertFailedWith(OpenAiException::class)->assertNotReleased();
        $profile->refresh();
        $this->assertSame('failed', $profile->cv_status);
        $this->assertSame(OpenAiException::MISSING_CONFIGURATION, $profile->cv_analysis_error_code);
        $this->assertSame(
            'خدمة تحليل السيرة الذاتية غير متاحة حاليًا. يرجى المحاولة لاحقًا أو التواصل مع إدارة المنصة.',
            $profile->cv_analysis_error_message,
        );
        Storage::disk('public')->assertExists($profile->cv_path);
        Http::assertNothingSent();
    }

    public function test_permanent_provider_failure_is_not_released_and_raw_provider_text_is_not_persisted(): void
    {
        [$profile, $job] = $this->currentJob('pdf');
        Http::fake(['*' => Http::response([
            'error' => [
                'type' => 'invalid_request_error',
                'code' => 'invalid_api_key',
                'message' => 'raw-secret-provider-marker',
            ],
        ], 401)]);
        $job->withFakeQueueInteractions();

        $job->handle(app(CvAnalysisService::class));

        $job->assertFailedWith(OpenAiException::class)->assertNotReleased();
        $profile->refresh();
        $this->assertSame('failed', $profile->cv_status);
        $this->assertSame(OpenAiException::AUTHENTICATION_FAILED, $profile->cv_analysis_error_code);
        $this->assertStringNotContainsString('raw-secret-provider-marker', $profile->cv_analysis_error_message);
        Http::assertSentCount(1);
    }

    public function test_transient_failure_is_released_using_retry_after_without_marking_profile_failed(): void
    {
        [$profile, $job] = $this->currentJob('pdf');
        Http::fake(['*' => Http::response([
            'error' => ['type' => 'rate_limit_error', 'code' => 'rate_limit_exceeded'],
        ], 429, ['Retry-After' => '23'])]);
        $job->withFakeQueueInteractions();

        $job->handle(app(CvAnalysisService::class));

        $job->assertReleased(23)->assertNotFailed();
        $profile->refresh();
        $this->assertSame('processing', $profile->cv_status);
        $this->assertNull($profile->cv_analysis_error_code);
        $this->assertNull($profile->cv_analysis_completed_at);
        Http::assertSentCount(1);
    }

    public function test_transient_failure_on_last_attempt_becomes_a_safe_final_failure(): void
    {
        [$profile, $job] = $this->currentJob('docx');
        Http::fake(['*' => Http::response([
            'error' => ['type' => 'server_error', 'code' => 'service_unavailable'],
        ], 503)]);
        $job->withFakeQueueInteractions();
        $job->job->attempts = 3;

        $job->handle(app(CvAnalysisService::class));

        $job->assertFailedWith(OpenAiException::class)->assertNotReleased();
        $profile->refresh();
        $this->assertSame('failed', $profile->cv_status);
        $this->assertSame(OpenAiException::PROVIDER_UNAVAILABLE, $profile->cv_analysis_error_code);
        $this->assertNotNull($profile->cv_analysis_completed_at);
    }

    public function test_missing_stored_file_is_a_safe_final_failure_and_keeps_the_recorded_path(): void
    {
        [$profile, $job] = $this->currentJob('pdf', storeFile: false);
        Http::fake();
        $job->withFakeQueueInteractions();

        $job->handle(app(CvAnalysisService::class));

        $job->assertFailedWith(OpenAiException::class)->assertNotReleased();
        $profile->refresh();
        $this->assertSame('failed', $profile->cv_status);
        $this->assertSame(OpenAiException::MISSING_FILE, $profile->cv_analysis_error_code);
        $this->assertSame($job->path, $profile->cv_path);
        Http::assertNothingSent();
    }

    public function test_stale_success_job_cannot_call_provider_or_overwrite_a_new_attempt(): void
    {
        [$profile] = $this->createSeekerProfile([
            'cv_path' => 'cvs/new.pdf',
            'cv_status' => 'processing',
            'cv_analysis_attempt_id' => (string) Str::uuid(),
            'profile_score' => 41,
            'extracted_skills' => ['Existing skill'],
        ]);
        Storage::disk('public')->put('cvs/new.pdf', '%PDF new');
        Storage::disk('public')->put('cvs/old.pdf', '%PDF old');
        $newAttempt = $profile->cv_analysis_attempt_id;
        $job = new AnalyzeCv($profile->id, 'cvs/old.pdf', 'old.pdf', (string) Str::uuid());
        Http::fake(['*' => Http::response(['output_text' => json_encode($this->validProviderResult())])]);
        $job->withFakeQueueInteractions();

        $job->handle(app(CvAnalysisService::class));

        $profile->refresh();
        $this->assertSame('processing', $profile->cv_status);
        $this->assertSame('cvs/new.pdf', $profile->cv_path);
        $this->assertSame($newAttempt, $profile->cv_analysis_attempt_id);
        $this->assertSame(41, $profile->profile_score);
        $this->assertSame(['Existing skill'], $profile->extracted_skills);
        Http::assertNothingSent();
        $job->assertNotFailed()->assertNotReleased();
    }

    public function test_stale_failed_job_cannot_mark_a_new_attempt_failed(): void
    {
        [$profile] = $this->createSeekerProfile([
            'cv_path' => 'cvs/new.doc',
            'cv_status' => 'processing',
            'cv_analysis_attempt_id' => (string) Str::uuid(),
        ]);
        $newAttempt = $profile->cv_analysis_attempt_id;
        $job = new AnalyzeCv($profile->id, 'cvs/old.doc', 'old.doc', (string) Str::uuid());

        $job->failed(new OpenAiException(OpenAiException::TIMEOUT, true));

        $profile->refresh();
        $this->assertSame('processing', $profile->cv_status);
        $this->assertSame('cvs/new.doc', $profile->cv_path);
        $this->assertSame($newAttempt, $profile->cv_analysis_attempt_id);
        $this->assertNull($profile->cv_analysis_error_code);
        $this->assertNull($profile->cv_analysis_completed_at);
    }

    public function test_duplicate_job_cannot_replace_an_already_saved_result(): void
    {
        [$profile, $firstJob] = $this->currentJob('pdf');
        $result = $this->validProviderResult();
        Http::fake(['*' => Http::response(['output_text' => json_encode($result, JSON_UNESCAPED_UNICODE)])]);
        $firstJob->withFakeQueueInteractions();
        $firstJob->handle(app(CvAnalysisService::class));
        $profile->refresh();
        $completedAt = $profile->cv_analysis_completed_at;

        $duplicate = new AnalyzeCv(
            $profile->id,
            $profile->cv_path,
            'candidate.pdf',
            $profile->cv_analysis_attempt_id,
        );
        $duplicate->withFakeQueueInteractions();
        $duplicate->handle(app(CvAnalysisService::class));

        $profile->refresh();
        $this->assertSame('analyzed', $profile->cv_status);
        $this->assertSame($result['score'], $profile->profile_score);
        $this->assertTrue($completedAt->equalTo($profile->cv_analysis_completed_at));
        Http::assertSentCount(1);
        $duplicate->assertNotFailed()->assertNotReleased();
    }

    public function test_processing_a_new_attempt_preserves_latest_successful_skills_for_matching(): void
    {
        Queue::fake();
        [$seeker, $profile] = $this->createSeeker([
            'cv_status' => 'analyzed',
            'profile_score' => 80,
            'extracted_skills' => ['Laravel'],
        ]);
        $company = CompanyProfile::create([
            'user_id' => User::factory()->create(['role' => 'employer'])->id,
            'company_name' => 'شركة الاختبار',
        ]);
        $job = Job::create([
            'company_profile_id' => $company->id,
            'title' => 'مطور',
            'slug' => 'cv-analysis-matching-regression',
            'description' => 'وصف',
            'required_skills' => ['Laravel'],
            'status' => 'published',
        ]);

        $this->actingAs($seeker)->post('/seeker/cv-analysis', [
            'cv' => UploadedFile::fake()->create('new.pdf', 10, 'application/pdf'),
        ])->assertRedirect();

        $profile->refresh();
        $this->assertSame('processing', $profile->cv_status);
        $this->assertSame(['Laravel'], $profile->extracted_skills);
        $this->assertSame(100, app(MatchingService::class)->match($job, $profile)['score']);
    }

    public function test_existing_successful_analysis_fields_are_returned_as_real_page_props(): void
    {
        [$seeker, $profile] = $this->createSeeker([
            'cv_status' => 'analyzed',
            'profile_score' => 87,
            'extracted_skills' => ['Laravel'],
            'missing_skills' => ['TypeScript'],
            'education_summary' => 'ملخص التعليم',
            'experience_summary' => 'ملخص الخبرة',
            'ai_recommendations' => [
                'strengths' => ['حل المشكلات'],
                'recommendations' => ['تعلم TypeScript'],
            ],
        ]);

        $this->actingAs($seeker)->get('/seeker/cv-analysis')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Seeker/CvAnalysis')
                ->where('profile.id', $profile->id)
                ->where('profile.cv_status', 'analyzed')
                ->where('profile.profile_score', 87)
                ->where('profile.extracted_skills.0', 'Laravel')
                ->where('profile.missing_skills.0', 'TypeScript')
                ->where('profile.ai_recommendations.strengths.0', 'حل المشكلات')
                ->where('profile.ai_recommendations.recommendations.0', 'تعلم TypeScript'));
    }

    public function test_job_seeker_dashboard_still_receives_the_latest_successful_analysis(): void
    {
        [$seeker, $profile] = $this->createSeeker([
            'cv_status' => 'analyzed',
            'profile_score' => 91,
            'extracted_skills' => ['Laravel', 'React'],
            'missing_skills' => ['TypeScript'],
        ]);

        $this->actingAs($seeker)->get('/seeker/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Seeker/Dashboard')
                ->where('profile.id', $profile->id)
                ->where('profile.cv_status', 'analyzed')
                ->where('profile.profile_score', 91)
                ->where('profile.extracted_skills', ['Laravel', 'React'])
                ->where('profile.missing_skills', ['TypeScript']));
    }

    public function test_queue_uses_dedicated_tables_and_retry_after_exceeds_job_timeout(): void
    {
        $job = new AnalyzeCv(1, 'cvs/test.pdf', 'test.pdf', (string) Str::uuid());

        $this->assertSame('queue_jobs', config('queue.connections.database.table'));
        $this->assertGreaterThan($job->timeout, config('queue.connections.database.retry_after'));
        $this->assertTrue(config('queue.connections.database.after_commit'));
        $this->assertSame(3, $job->tries);
        $this->assertSame([10, 30, 60], $job->backoff());
        $this->assertTrue($job->failOnTimeout);
        $this->assertTrue(Schema::hasTable('queue_jobs'));
        $this->assertTrue(Schema::hasTable('failed_jobs'));
        $this->assertTrue(Schema::hasTable('job_batches'));
        $this->assertContains('payload', Schema::getColumnListing('queue_jobs'));
        $this->assertNotContains('payload', Schema::getColumnListing('jobs'));
    }

    public function test_database_queue_stores_the_cv_job_without_touching_recruitment_jobs(): void
    {
        config(['queue.connections.database.after_commit' => false]);
        $domainJobCount = Job::count();
        $job = new AnalyzeCv(123, 'cvs/queued.pdf', 'queued.pdf', (string) Str::uuid());

        Queue::connection('database')->push($job);

        $this->assertDatabaseCount('queue_jobs', 1);
        $this->assertSame($domainJobCount, Job::count());
        $payload = json_decode((string) DB::table('queue_jobs')->value('payload'), true);
        $this->assertIsArray($payload);
        $this->assertSame(AnalyzeCv::class, $payload['data']['commandName']);
        $this->assertSame(0, DB::table('failed_jobs')->count());
    }

    /** @return array{User, JobSeekerProfile} */
    private function createSeeker(array $profileAttributes = []): array
    {
        $seeker = User::factory()->create(['role' => 'job_seeker']);
        $profile = JobSeekerProfile::create([
            'user_id' => $seeker->id,
            ...$profileAttributes,
        ]);

        return [$seeker, $profile];
    }

    /** @return array{JobSeekerProfile, AnalyzeCv} */
    private function currentJob(string $extension, bool $storeFile = true): array
    {
        $attemptId = (string) Str::uuid();
        $path = "cvs/{$attemptId}.{$extension}";
        [, $profile] = $this->createSeeker([
            'cv_path' => $path,
            'cv_status' => 'processing',
            'cv_analysis_attempt_id' => $attemptId,
            'cv_analysis_started_at' => now(),
        ]);

        if ($storeFile) {
            Storage::disk('public')->put($path, "non-empty {$extension} test bytes");
        }

        return [$profile, new AnalyzeCv($profile->id, $path, "candidate.{$extension}", $attemptId)];
    }

    /** @return array{JobSeekerProfile, User} */
    private function createSeekerProfile(array $profileAttributes): array
    {
        [$seeker, $profile] = $this->createSeeker($profileAttributes);

        return [$profile, $seeker];
    }

    /** @return array<string, mixed> */
    private function validProviderResult(array $overrides = []): array
    {
        return array_replace([
            'score' => 87,
            'extracted_skills' => ['Laravel', 'PHP'],
            'missing_skills' => ['TypeScript'],
            'education_summary' => 'بكالوريوس في علوم الحاسوب',
            'experience_summary' => 'خبرة في تطوير تطبيقات الويب',
            'strengths' => ['حل المشكلات', 'التعاون'],
            'recommendations' => ['تعلم TypeScript', 'إضافة نتائج قابلة للقياس'],
        ], $overrides);
    }
}
