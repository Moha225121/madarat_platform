<?php

namespace Tests\Feature;

use App\Models\CourseRecommendation;
use App\Models\CourseUserFeedback;
use App\Models\JobSeekerProfile;
use App\Models\TrainingCourse;
use App\Models\TrainingProviderProfile;
use App\Models\User;
use App\Services\CourseRecommendationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrainingPlatformTest extends TestCase
{
    use RefreshDatabase;

    public function test_training_provider_can_register_and_is_redirected(): void
    {
        $this->post('/register', ['name' => 'Provider', 'email' => 'provider@test.test', 'role' => 'training_provider', 'password' => 'password', 'password_confirmation' => 'password'])->assertRedirect('/dashboard');
        $user = User::whereEmail('provider@test.test')->firstOrFail();
        $this->assertSame('training_provider', $user->role);
        $this->assertNotNull($user->trainingProviderProfile);
        $this->actingAs($user)->get('/dashboard')->assertRedirect('/training/dashboard');
    }

    public function test_role_boundaries_are_enforced(): void
    {
        $provider = $this->provider();
        $employer = User::factory()->create(['role' => 'employer']);
        $seeker = User::factory()->create(['role' => 'job_seeker']);
        $this->actingAs($provider)->get('/employer/dashboard')->assertForbidden();
        $this->actingAs($provider)->get('/admin/training')->assertForbidden();
        $this->actingAs($employer)->get('/training/dashboard')->assertForbidden();
        $this->actingAs($seeker)->get('/training/courses')->assertForbidden();
    }

    public function test_profile_verification_requires_complete_fields_and_admin_can_moderate(): void
    {
        $provider = $this->provider();
        $profile = $provider->trainingProviderProfile;
        $this->actingAs($provider)->post('/training/profile/request-verification')->assertRedirect();
        $this->assertSame('incomplete', $profile->fresh()->verification_status);
        $profile->update(['legal_name' => 'Legal', 'description' => 'Description', 'phone' => '123', 'city' => 'Tripoli', 'specializations' => ['PHP'], 'commercial_registration_number' => 'CR1']);
        $this->actingAs($provider)->post('/training/profile/request-verification');
        $this->assertSame('pending', $profile->fresh()->verification_status);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post("/admin/training/providers/{$profile->id}/verify")->assertRedirect();
        $this->assertSame('verified', $profile->fresh()->verification_status);
    }

    public function test_provider_can_create_and_submit_own_course_but_not_edit_another(): void
    {
        $provider = $this->provider();
        $other = $this->provider();
        $response = $this->actingAs($provider)->post('/training/courses', $this->courseData());
        $response->assertRedirect();
        $course = TrainingCourse::where('training_provider_id', $provider->trainingProviderProfile->id)->firstOrFail();
        $this->assertSame('draft', $course->status);
        $this->actingAs($other)->put("/training/courses/{$course->id}", $this->courseData(['title' => 'Hijack']))->assertForbidden();
        $this->actingAs($provider)->post("/training/courses/{$course->id}/submit");
        $this->assertSame('pending_review', $course->fresh()->status);
        $this->actingAs($provider)->put("/training/courses/{$course->id}", $this->courseData())->assertForbidden();
    }

    public function test_admin_course_rejection_requires_reason_and_public_catalogue_only_shows_published(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $provider = $this->provider();
        $pending = TrainingCourse::factory()->for($provider->trainingProviderProfile, 'provider')->create(['status' => 'pending_review']);
        $draft = TrainingCourse::factory()->for($provider->trainingProviderProfile, 'provider')->create(['status' => 'draft']);
        $this->actingAs($admin)->post("/admin/training/courses/{$pending->id}/reject", [])->assertSessionHasErrors('reason');
        $this->actingAs($admin)->post("/admin/training/courses/{$pending->id}/reject", ['reason' => 'Needs outcomes']);
        $this->assertSame('rejected', $pending->fresh()->status);
        $published = TrainingCourse::factory()->for($provider->trainingProviderProfile, 'provider')->create(['status' => 'published', 'title' => 'Visible Course']);
        $this->get('/courses')->assertOk()->assertSee('Visible Course')->assertDontSee($draft->title);
    }

    public function test_recommendations_cover_real_gaps_and_exclude_known_completed_and_expired_courses(): void
    {
        $seeker = User::factory()->create(['role' => 'job_seeker']);
        JobSeekerProfile::create(['user_id' => $seeker->id, 'extracted_skills' => ['PHP'], 'missing_skills' => ['React']]);
        $provider = $this->provider();
        $relevant = TrainingCourse::factory()->for($provider->trainingProviderProfile, 'provider')->create(['status' => 'published', 'skills_taught' => ['React']]);
        TrainingCourse::factory()->for($provider->trainingProviderProfile, 'provider')->create(['status' => 'published', 'skills_taught' => ['PHP']]);
        TrainingCourse::factory()->for($provider->trainingProviderProfile, 'provider')->create(['status' => 'closed', 'skills_taught' => ['React']]);
        $recommendations = app(CourseRecommendationService::class)->generate($seeker);
        $this->assertCount(1, $recommendations);
        $this->assertSame($relevant->id, $recommendations->first()->course_id);
        $this->assertContains('react', $recommendations->first()->missing_skills_covered);
        $this->assertNotEmpty($recommendations->first()->evidence);
        CourseUserFeedback::create(['user_id' => $seeker->id, 'course_id' => $relevant->id, 'completed' => true]);
        $this->assertCount(0, app(CourseRecommendationService::class)->generate($seeker->fresh()));
    }

    public function test_job_seeker_cannot_see_another_users_private_recommendations(): void
    {
        $one = User::factory()->create(['role' => 'job_seeker']);
        $two = User::factory()->create(['role' => 'job_seeker']);
        JobSeekerProfile::create(['user_id' => $one->id, 'missing_skills' => ['React']]);
        JobSeekerProfile::create(['user_id' => $two->id]);
        $course = TrainingCourse::factory()->create(['status' => 'published', 'skills_taught' => ['React']]);
        CourseRecommendation::create(['job_seeker_id' => $one->id, 'course_id' => $course->id, 'score' => 80, 'missing_skills_covered' => ['react'], 'evidence' => [], 'reason' => 'private reason marker', 'confidence' => .8, 'content_signature' => str_repeat('a', 64), 'recommended_at' => now()]);
        $this->actingAs($two)->get('/seeker/courses/recommended')->assertOk()->assertDontSee('private reason marker');
    }

    private function provider(): User
    {
        $user = User::factory()->create(['role' => 'training_provider']);
        TrainingProviderProfile::factory()->create(['user_id' => $user->id]);

        return $user;
    }

    private function courseData(array $overrides = []): array
    {
        return array_merge(['title' => 'React Course', 'description' => 'Detailed course', 'skills_taught' => 'React, TypeScript', 'difficulty_level' => 'beginner', 'delivery_method' => 'online', 'is_remote' => true, 'certificate_available' => true], $overrides);
    }
}
