<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\AssistantMessage;
use App\Models\CompanyProfile;
use App\Models\CourseEnrollment;
use App\Models\CourseRecommendation;
use App\Models\CourseUserFeedback;
use App\Models\InterviewInvitation;
use App\Models\Job;
use App\Models\TrainingCourse;
use App\Models\TrainingProviderProfile;
use App\Models\User;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class AdminAccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_permanently_delete_an_employer_and_all_owned_data(): void
    {
        Storage::fake('public');

        $admin = $this->user('admin', 'admin-employer-delete@example.com');
        $employer = $this->user('employer', 'deleted-employer@example.com');
        $companyLogo = 'company-logos/deleted-company.png';
        $company = $this->company($employer, 'الشركة المحذوفة', ['logo_path' => $companyLogo]);
        $job = $this->job($company, 'deleted-company-job');

        $unrelatedEmployer = $this->user('employer', 'preserved-employer@example.com');
        $unrelatedLogo = 'company-logos/preserved-company.png';
        $unrelatedCompany = $this->company($unrelatedEmployer, 'الشركة المحفوظة', ['logo_path' => $unrelatedLogo]);
        $unrelatedJob = $this->job($unrelatedCompany, 'preserved-company-job');

        $seeker = $this->user('job_seeker', 'employer-deletion-seeker@example.com');
        $application = $this->application($job, $seeker);
        $invitation = InterviewInvitation::create([
            'application_id' => $application->id,
            'message' => 'دعوة مرتبطة بالوظيفة المحذوفة',
        ]);
        $unrelatedApplication = $this->application($unrelatedJob, $seeker);
        $unrelatedInvitation = InterviewInvitation::create([
            'application_id' => $unrelatedApplication->id,
            'message' => 'دعوة يجب أن تبقى',
        ]);

        $assistantMessage = AssistantMessage::create([
            'user_id' => $employer->id,
            'role' => 'user',
            'message' => 'رسالة خاصة بصاحب العمل المحذوف',
        ]);
        $unrelatedAssistantMessage = AssistantMessage::create([
            'user_id' => $unrelatedEmployer->id,
            'role' => 'user',
            'message' => 'رسالة يجب أن تبقى',
        ]);

        $this->databaseSession('deleted-employer-session', $employer);
        $this->databaseSession('preserved-employer-session', $unrelatedEmployer);
        $this->passwordResetToken($employer, 'deleted-employer-token');
        $this->passwordResetToken($unrelatedEmployer, 'preserved-employer-token');

        $courseOwner = $this->user('training_provider', 'recommendation-course-owner@example.com');
        $courseProvider = $this->provider($courseOwner, 'company', 'مزود دورة التوصية');
        $course = $this->course($courseProvider, 'recommendation-course');
        $recommendation = $this->recommendation(
            $seeker,
            $course,
            [$job->id, $unrelatedJob->id],
        );
        $unrelatedRecommendationCourse = $this->course($courseProvider, 'preserved-recommendation-course');
        $unrelatedRecommendation = $this->recommendation(
            $seeker,
            $unrelatedRecommendationCourse,
            [$unrelatedJob->id],
        );

        Storage::disk('public')->put($companyLogo, 'target company logo');
        Storage::disk('public')->put($unrelatedLogo, 'unrelated company logo');
        Storage::disk('public')->put('company-logos/unreferenced.png', 'unreferenced file');

        $response = $this->actingAs($admin)->delete(
            route('admin.companies.destroy', $company, absolute: false),
        );

        $response
            ->assertRedirect(route('admin.companies.index', absolute: false))
            ->assertSessionHas('success', 'تم حذف حساب صاحب العمل وجميع بياناته المرتبطة بنجاح.');

        $this->assertDatabaseMissing('users', ['id' => $employer->id]);
        $this->assertDatabaseMissing('company_profiles', ['id' => $company->id]);
        $this->assertDatabaseMissing('jobs', ['id' => $job->id]);
        $this->assertDatabaseMissing('applications', ['id' => $application->id]);
        $this->assertDatabaseMissing('interview_invitations', ['id' => $invitation->id]);
        $this->assertDatabaseMissing('assistant_messages', ['id' => $assistantMessage->id]);
        $this->assertDatabaseMissing('sessions', ['id' => 'deleted-employer-session']);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $employer->email]);
        Storage::disk('public')->assertMissing($companyLogo);

        $this->assertDatabaseHas('users', ['id' => $seeker->id]);
        $this->assertDatabaseHas('users', ['id' => $unrelatedEmployer->id]);
        $this->assertDatabaseHas('company_profiles', ['id' => $unrelatedCompany->id]);
        $this->assertDatabaseHas('jobs', ['id' => $unrelatedJob->id]);
        $this->assertDatabaseHas('applications', ['id' => $unrelatedApplication->id]);
        $this->assertDatabaseHas('interview_invitations', ['id' => $unrelatedInvitation->id]);
        $this->assertDatabaseHas('assistant_messages', ['id' => $unrelatedAssistantMessage->id]);
        $this->assertDatabaseHas('sessions', ['id' => 'preserved-employer-session']);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $unrelatedEmployer->email]);
        $this->assertDatabaseMissing('course_recommendations', ['id' => $recommendation->id]);
        $this->assertDatabaseHas('course_recommendations', ['id' => $unrelatedRecommendation->id]);
        $this->assertSame(
            [$unrelatedJob->id],
            CourseRecommendation::findOrFail($unrelatedRecommendation->id)->target_job_ids,
        );
        Storage::disk('public')->assertExists($unrelatedLogo);
        Storage::disk('public')->assertExists('company-logos/unreferenced.png');
    }

    public function test_deleting_an_employer_does_not_delete_a_logo_still_referenced_by_another_company(): void
    {
        Storage::fake('public');

        $admin = $this->user('admin', 'admin-shared-company-file@example.com');
        $sharedLogo = 'company-logos/shared-company.png';
        $employer = $this->user('employer', 'shared-logo-deleted-employer@example.com');
        $company = $this->company($employer, 'شركة ستحذف', ['logo_path' => $sharedLogo]);
        $unrelatedEmployer = $this->user('employer', 'shared-logo-preserved-employer@example.com');
        $unrelatedCompany = $this->company($unrelatedEmployer, 'شركة ستبقى', ['logo_path' => $sharedLogo]);

        Storage::disk('public')->put($sharedLogo, 'shared company logo');

        $this->actingAs($admin)
            ->delete(route('admin.companies.destroy', $company, absolute: false))
            ->assertRedirect(route('admin.companies.index', absolute: false))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('users', ['id' => $employer->id]);
        $this->assertDatabaseHas('company_profiles', ['id' => $unrelatedCompany->id]);
        Storage::disk('public')->assertExists($sharedLogo);
    }

    public function test_admin_can_permanently_delete_an_individual_trainer_and_all_owned_data(): void
    {
        Storage::fake('public');

        $admin = $this->user('admin', 'admin-trainer-delete@example.com');
        $trainer = $this->user('training_provider', 'deleted-trainer@example.com');
        $logo = 'training-providers/deleted-trainer-logo.png';
        $profileImage = 'training-providers/deleted-trainer-profile.png';
        $provider = $this->provider($trainer, 'trainer', 'المدرب المحذوف', [
            'logo_path' => $logo,
            'profile_image_path' => $profileImage,
        ]);
        $courseCover = 'training-courses/deleted-course-cover.png';
        $course = $this->course($provider, 'deleted-trainer-course', ['cover_image_path' => $courseCover]);

        $sharedCover = 'training-courses/shared-cover.png';
        $sharedCourse = $this->course($provider, 'deleted-shared-cover-course', ['cover_image_path' => $sharedCover]);
        $missingCoverCourse = $this->course($provider, 'deleted-missing-cover-course', [
            'cover_image_path' => 'training-courses/already-missing.png',
        ]);

        $unrelatedProviderUser = $this->user('training_provider', 'preserved-provider@example.com');
        $unrelatedLogo = 'training-providers/preserved-provider-logo.png';
        $unrelatedProfileImage = 'training-providers/preserved-provider-profile.png';
        $unrelatedProvider = $this->provider($unrelatedProviderUser, 'company', 'مزود محفوظ', [
            'logo_path' => $unrelatedLogo,
            'profile_image_path' => $unrelatedProfileImage,
        ]);
        $unrelatedCourse = $this->course($unrelatedProvider, 'preserved-provider-course', [
            'cover_image_path' => $sharedCover,
        ]);

        $seeker = $this->user('job_seeker', 'trainer-deletion-seeker@example.com');
        $enrollment = $this->enrollment($seeker, $course);
        $feedback = CourseUserFeedback::create([
            'user_id' => $seeker->id,
            'course_id' => $course->id,
            'saved' => true,
        ]);
        $recommendation = $this->recommendation($seeker, $course);

        $unrelatedEnrollment = $this->enrollment($seeker, $unrelatedCourse);
        $unrelatedFeedback = CourseUserFeedback::create([
            'user_id' => $seeker->id,
            'course_id' => $unrelatedCourse->id,
            'interested' => true,
        ]);
        $unrelatedRecommendation = $this->recommendation($seeker, $unrelatedCourse);

        $assistantMessage = AssistantMessage::create([
            'user_id' => $trainer->id,
            'role' => 'user',
            'message' => 'رسالة خاصة بالمدرب المحذوف',
        ]);
        $this->databaseSession('deleted-trainer-session', $trainer);
        $this->passwordResetToken($trainer, 'deleted-trainer-token');

        foreach ([$logo, $profileImage, $courseCover, $sharedCover, $unrelatedLogo, $unrelatedProfileImage] as $path) {
            Storage::disk('public')->put($path, "file at {$path}");
        }

        $response = $this->actingAs($admin)->delete(
            route('admin.trainers.destroy', $provider, absolute: false),
        );

        $response
            ->assertRedirect(route('admin.trainers.index', absolute: false))
            ->assertSessionHas('success', 'تم حذف حساب مزود التدريب وجميع بياناته المرتبطة بنجاح.');

        $this->assertDatabaseMissing('users', ['id' => $trainer->id]);
        $this->assertDatabaseMissing('training_provider_profiles', ['id' => $provider->id]);
        $this->assertDatabaseMissing('training_courses', ['id' => $course->id]);
        $this->assertDatabaseMissing('training_courses', ['id' => $sharedCourse->id]);
        $this->assertDatabaseMissing('training_courses', ['id' => $missingCoverCourse->id]);
        $this->assertDatabaseMissing('course_enrollments', ['id' => $enrollment->id]);
        $this->assertDatabaseMissing('course_user_feedback', ['id' => $feedback->id]);
        $this->assertDatabaseMissing('course_recommendations', ['id' => $recommendation->id]);
        $this->assertDatabaseMissing('assistant_messages', ['id' => $assistantMessage->id]);
        $this->assertDatabaseMissing('sessions', ['id' => 'deleted-trainer-session']);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $trainer->email]);
        Storage::disk('public')->assertMissing($logo);
        Storage::disk('public')->assertMissing($profileImage);
        Storage::disk('public')->assertMissing($courseCover);

        $this->assertDatabaseHas('users', ['id' => $seeker->id]);
        $this->assertDatabaseHas('users', ['id' => $unrelatedProviderUser->id]);
        $this->assertDatabaseHas('training_provider_profiles', ['id' => $unrelatedProvider->id]);
        $this->assertDatabaseHas('training_courses', ['id' => $unrelatedCourse->id]);
        $this->assertDatabaseHas('course_enrollments', ['id' => $unrelatedEnrollment->id]);
        $this->assertDatabaseHas('course_user_feedback', ['id' => $unrelatedFeedback->id]);
        $this->assertDatabaseHas('course_recommendations', ['id' => $unrelatedRecommendation->id]);
        Storage::disk('public')->assertExists($sharedCover);
        Storage::disk('public')->assertExists($unrelatedLogo);
        Storage::disk('public')->assertExists($unrelatedProfileImage);
    }

    public function test_admin_can_delete_a_training_company_account(): void
    {
        $admin = $this->user('admin', 'admin-training-company-delete@example.com');
        $trainingCompany = $this->user('training_provider', 'deleted-training-company@example.com');
        $provider = $this->provider($trainingCompany, 'company', 'شركة تدريب ستحذف');

        $this->actingAs($admin)
            ->delete(route('admin.trainers.destroy', $provider, absolute: false))
            ->assertRedirect(route('admin.trainers.index', absolute: false))
            ->assertSessionHas('success', 'تم حذف حساب مزود التدريب وجميع بياناته المرتبطة بنجاح.');

        $this->assertDatabaseMissing('users', ['id' => $trainingCompany->id]);
        $this->assertDatabaseMissing('training_provider_profiles', ['id' => $provider->id]);
    }

    public function test_guests_cannot_use_administrator_account_deletion_routes(): void
    {
        $employer = $this->user('employer', 'guest-target-employer@example.com');
        $company = $this->company($employer, 'شركة محمية من الضيف');
        $trainer = $this->user('training_provider', 'guest-target-provider@example.com');
        $provider = $this->provider($trainer, 'trainer', 'مدرب محمي من الضيف');

        $this->delete(route('admin.companies.destroy', $company, absolute: false))
            ->assertRedirect(route('login', absolute: false));
        $this->delete(route('admin.trainers.destroy', $provider, absolute: false))
            ->assertRedirect(route('login', absolute: false));

        $this->assertDatabaseHas('users', ['id' => $employer->id]);
        $this->assertDatabaseHas('company_profiles', ['id' => $company->id]);
        $this->assertDatabaseHas('users', ['id' => $trainer->id]);
        $this->assertDatabaseHas('training_provider_profiles', ['id' => $provider->id]);
    }

    public function test_non_administrators_cannot_use_either_account_deletion_route(): void
    {
        $targetEmployer = $this->user('employer', 'role-target-employer@example.com');
        $targetCompany = $this->company($targetEmployer, 'شركة محمية بالأدوار');
        $targetTrainer = $this->user('training_provider', 'role-target-provider@example.com');
        $targetProvider = $this->provider($targetTrainer, 'trainer', 'مدرب محمي بالأدوار');

        foreach (['employer', 'training_provider', 'job_seeker'] as $role) {
            $actor = $this->user($role, "{$role}-unauthorized-delete@example.com");

            $this->actingAs($actor)
                ->delete(route('admin.companies.destroy', $targetCompany, absolute: false))
                ->assertForbidden();
            $this->actingAs($actor)
                ->delete(route('admin.trainers.destroy', $targetProvider, absolute: false))
                ->assertForbidden();
        }

        $this->assertDatabaseHas('users', ['id' => $targetEmployer->id]);
        $this->assertDatabaseHas('company_profiles', ['id' => $targetCompany->id]);
        $this->assertDatabaseHas('users', ['id' => $targetTrainer->id]);
        $this->assertDatabaseHas('training_provider_profiles', ['id' => $targetProvider->id]);
    }

    public function test_company_deletion_rejects_profiles_attached_to_any_non_employer_role(): void
    {
        $admin = $this->user('admin', 'current-admin-company-safety@example.com');
        $otherAdmin = $this->user('admin', 'other-admin-company-safety@example.com');
        $jobSeeker = $this->user('job_seeker', 'wrong-company-seeker@example.com');
        $trainingProvider = $this->user('training_provider', 'wrong-company-provider@example.com');

        $profiles = [
            $this->company($admin, 'ملف شركة مرتبط بالمدير'),
            $this->company($otherAdmin, 'ملف شركة مرتبط بمدير آخر'),
            $this->company($jobSeeker, 'ملف شركة مرتبط بباحث'),
            $this->company($trainingProvider, 'ملف شركة مرتبط بمزود تدريب'),
        ];

        foreach ($profiles as $profile) {
            $this->actingAs($admin)
                ->delete(route('admin.companies.destroy', $profile, absolute: false))
                ->assertNotFound();

            $this->assertDatabaseHas('users', ['id' => $profile->user_id]);
            $this->assertDatabaseHas('company_profiles', ['id' => $profile->id]);
        }

        $this->assertAuthenticatedAs($admin);
    }

    public function test_training_provider_deletion_rejects_profiles_attached_to_any_other_role(): void
    {
        $admin = $this->user('admin', 'current-admin-provider-safety@example.com');
        $otherAdmin = $this->user('admin', 'other-admin-provider-safety@example.com');
        $jobSeeker = $this->user('job_seeker', 'wrong-provider-seeker@example.com');
        $employer = $this->user('employer', 'wrong-provider-employer@example.com');

        $profiles = [
            $this->provider($admin, 'company', 'ملف تدريب مرتبط بالمدير'),
            $this->provider($otherAdmin, 'company', 'ملف تدريب مرتبط بمدير آخر'),
            $this->provider($jobSeeker, 'trainer', 'ملف تدريب مرتبط بباحث'),
            $this->provider($employer, 'company', 'ملف تدريب مرتبط بصاحب عمل'),
        ];

        foreach ($profiles as $profile) {
            $this->actingAs($admin)
                ->delete(route('admin.trainers.destroy', $profile, absolute: false))
                ->assertNotFound();

            $this->assertDatabaseHas('users', ['id' => $profile->user_id]);
            $this->assertDatabaseHas('training_provider_profiles', ['id' => $profile->id]);
        }

        $this->assertAuthenticatedAs($admin);
    }

    public function test_employers_and_training_providers_cannot_delete_themselves_through_profile(): void
    {
        $employer = $this->user('employer', 'self-delete-employer@example.com');
        $company = $this->company($employer, 'شركة لا تحذف ذاتيا');

        $this->actingAs($employer)
            ->delete(route('profile.destroy', absolute: false), ['password' => 'password'])
            ->assertForbidden();
        $this->assertAuthenticatedAs($employer);
        $this->assertDatabaseHas('users', ['id' => $employer->id]);
        $this->assertDatabaseHas('company_profiles', ['id' => $company->id]);

        $trainingProvider = $this->user('training_provider', 'self-delete-provider@example.com');
        $provider = $this->provider($trainingProvider, 'trainer', 'مدرب لا يحذف ذاته');

        $this->actingAs($trainingProvider)
            ->delete(route('profile.destroy', absolute: false), ['password' => 'password'])
            ->assertForbidden();
        $this->assertAuthenticatedAs($trainingProvider);
        $this->assertDatabaseHas('users', ['id' => $trainingProvider->id]);
        $this->assertDatabaseHas('training_provider_profiles', ['id' => $provider->id]);
    }

    public function test_failed_employer_deletion_rolls_back_all_database_cleanup_and_preserves_files(): void
    {
        Storage::fake('public');

        $admin = $this->user('admin', 'admin-rollback@example.com');
        $employer = $this->user('employer', 'rollback-employer@example.com');
        $logo = 'company-logos/rollback-company.png';
        $company = $this->company($employer, 'شركة اختبار التراجع', ['logo_path' => $logo]);
        $job = $this->job($company, 'rollback-company-job');
        $seeker = $this->user('job_seeker', 'rollback-seeker@example.com');
        $application = $this->application($job, $seeker);
        $invitation = InterviewInvitation::create(['application_id' => $application->id]);
        $message = AssistantMessage::create([
            'user_id' => $employer->id,
            'role' => 'user',
            'message' => 'يجب استعادة هذه الرسالة',
        ]);
        $this->databaseSession('rollback-employer-session', $employer);
        $this->passwordResetToken($employer, 'rollback-employer-token');

        $courseOwner = $this->user('training_provider', 'rollback-course-owner@example.com');
        $courseProvider = $this->provider($courseOwner, 'company', 'مزود دورة اختبار التراجع');
        $course = $this->course($courseProvider, 'rollback-recommendation-course');
        $recommendation = $this->recommendation($seeker, $course, [$job->id]);

        Storage::disk('public')->put($logo, 'rollback logo');

        Event::listen('eloquent.deleted: '.User::class, function (User $deletedUser) use ($employer): void {
            if ($deletedUser->is($employer)) {
                throw new RuntimeException('Forced account deletion failure.');
            }
        });

        $response = $this->actingAs($admin)->delete(
            route('admin.companies.destroy', $company, absolute: false),
        );

        $response
            ->assertRedirect(route('admin.companies.index', absolute: false))
            ->assertSessionHas('error', 'تعذر حذف حساب صاحب العمل. يرجى المحاولة مرة أخرى.')
            ->assertSessionMissing('success');

        $this->assertDatabaseHas('users', ['id' => $employer->id]);
        $this->assertDatabaseHas('company_profiles', ['id' => $company->id]);
        $this->assertDatabaseHas('jobs', ['id' => $job->id]);
        $this->assertDatabaseHas('applications', ['id' => $application->id]);
        $this->assertDatabaseHas('interview_invitations', ['id' => $invitation->id]);
        $this->assertDatabaseHas('assistant_messages', ['id' => $message->id, 'user_id' => $employer->id]);
        $this->assertDatabaseHas('sessions', ['id' => 'rollback-employer-session', 'user_id' => $employer->id]);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $employer->email]);
        $this->assertSame(
            [$job->id],
            CourseRecommendation::findOrFail($recommendation->id)->target_job_ids,
        );
        Storage::disk('public')->assertExists($logo);
    }

    public function test_employer_file_cleanup_failure_is_reported_after_database_deletion_commits(): void
    {
        $admin = $this->user('admin', 'admin-employer-file-failure@example.com');
        $employer = $this->user('employer', 'employer-file-failure@example.com');
        $logo = 'company-logos/file-cleanup-failure.png';
        $company = $this->company($employer, 'شركة فشل حذف ملفها', ['logo_path' => $logo]);
        $disk = Mockery::mock(Filesystem::class);

        $disk->shouldReceive('delete')->once()->with([$logo])->andReturnFalse();
        Storage::shouldReceive('disk')->once()->with('public')->andReturn($disk);

        $response = $this->actingAs($admin)->delete(
            route('admin.companies.destroy', $company, absolute: false),
        );

        $response
            ->assertRedirect(route('admin.companies.index', absolute: false))
            ->assertSessionHas('error', 'تم حذف حساب صاحب العمل، لكن تعذر حذف بعض الملفات المرتبطة به. يرجى مراجعة السجلات.')
            ->assertSessionMissing('success');

        $this->assertDatabaseMissing('users', ['id' => $employer->id]);
        $this->assertDatabaseMissing('company_profiles', ['id' => $company->id]);
    }

    public function test_training_provider_file_cleanup_failure_is_reported_after_database_deletion_commits(): void
    {
        $admin = $this->user('admin', 'admin-provider-file-failure@example.com');
        $trainingProvider = $this->user('training_provider', 'provider-file-failure@example.com');
        $logo = 'training-providers/file-cleanup-failure.png';
        $provider = $this->provider($trainingProvider, 'trainer', 'مزود فشل حذف ملفه', ['logo_path' => $logo]);
        $disk = Mockery::mock(Filesystem::class);

        $disk->shouldReceive('delete')->once()->with([$logo])->andReturnFalse();
        Storage::shouldReceive('disk')->once()->with('public')->andReturn($disk);

        $response = $this->actingAs($admin)->delete(
            route('admin.trainers.destroy', $provider, absolute: false),
        );

        $response
            ->assertRedirect(route('admin.trainers.index', absolute: false))
            ->assertSessionHas('error', 'تم حذف حساب مزود التدريب، لكن تعذر حذف بعض الملفات المرتبطة به. يرجى مراجعة السجلات.')
            ->assertSessionMissing('success');

        $this->assertDatabaseMissing('users', ['id' => $trainingProvider->id]);
        $this->assertDatabaseMissing('training_provider_profiles', ['id' => $provider->id]);
    }

    private function user(string $role, string $email): User
    {
        return User::factory()->create([
            'role' => $role,
            'email' => $email,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function company(User $user, string $name, array $attributes = []): CompanyProfile
    {
        return CompanyProfile::create([
            'user_id' => $user->id,
            'company_name' => $name,
            ...$attributes,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function job(CompanyProfile $company, string $slug, array $attributes = []): Job
    {
        return Job::create([
            'company_profile_id' => $company->id,
            'title' => "وظيفة {$slug}",
            'slug' => $slug,
            'description' => 'وصف وظيفي لاختبار حذف الحساب.',
            ...$attributes,
        ]);
    }

    private function application(Job $job, User $user): Application
    {
        return Application::create([
            'job_id' => $job->id,
            'user_id' => $user->id,
            'cover_letter' => 'رسالة تقديم للاختبار.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function provider(User $user, string $type, string $name, array $attributes = []): TrainingProviderProfile
    {
        return TrainingProviderProfile::create([
            'user_id' => $user->id,
            'provider_type' => $type,
            'display_name' => $name,
            ...$attributes,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function course(TrainingProviderProfile $provider, string $slug, array $attributes = []): TrainingCourse
    {
        return TrainingCourse::create([
            'training_provider_id' => $provider->id,
            'title' => "دورة {$slug}",
            'slug' => $slug,
            'description' => 'وصف دورة لاختبار حذف الحساب.',
            'difficulty_level' => 'beginner',
            'delivery_method' => 'online',
            ...$attributes,
        ]);
    }

    private function enrollment(User $user, TrainingCourse $course): CourseEnrollment
    {
        return CourseEnrollment::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'status' => 'registered',
            'registered_at' => now(),
        ]);
    }

    /**
     * @param  array<int, int>  $targetJobIds
     */
    private function recommendation(
        User $user,
        TrainingCourse $course,
        array $targetJobIds = [],
    ): CourseRecommendation {
        return CourseRecommendation::create([
            'job_seeker_id' => $user->id,
            'course_id' => $course->id,
            'score' => 80,
            'missing_skills_covered' => ['Laravel'],
            'target_job_ids' => $targetJobIds,
            'evidence' => [],
            'reason' => 'توصية أنشئت لاختبار حذف الحساب.',
            'confidence' => 0.8,
            'content_signature' => hash('sha256', "{$user->id}:{$course->id}"),
            'recommended_at' => now(),
        ]);
    }

    private function databaseSession(string $id, User $user): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => 'test-session-payload',
            'last_activity' => now()->timestamp,
        ]);
    }

    private function passwordResetToken(User $user, string $token): void
    {
        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => $token,
            'created_at' => now(),
        ]);
    }
}
