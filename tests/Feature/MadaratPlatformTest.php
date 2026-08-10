<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\CompanyProfile;
use App\Models\Job;
use App\Models\JobSeekerProfile;
use App\Models\User;
use App\Services\MatchingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MadaratPlatformTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_as_job_seeker(): void
    {
        $this->post('/register', [
            'name' => 'باحث جديد',
            'email' => 'new-seeker@madarat.test',
            'role' => 'job_seeker',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect('/dashboard');

        $this->assertDatabaseHas('users', ['email' => 'new-seeker@madarat.test', 'role' => 'job_seeker']);
        $this->assertDatabaseCount('job_seeker_profiles', 1);
    }

    public function test_user_can_register_as_employer(): void
    {
        $this->post('/register', [
            'name' => 'شركة جديدة',
            'email' => 'new-company@madarat.test',
            'role' => 'employer',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect('/dashboard');

        $this->assertDatabaseHas('users', ['email' => 'new-company@madarat.test', 'role' => 'employer']);
        $this->assertDatabaseHas('company_profiles', ['company_name' => 'شركة جديدة']);
    }

    public function test_role_redirect_works(): void
    {
        $user = User::factory()->create(['role' => 'employer']);

        $this->actingAs($user)->get('/dashboard')->assertRedirect('/employer/dashboard');
    }

    public function test_employer_can_create_job(): void
    {
        $user = User::factory()->create(['role' => 'employer']);
        CompanyProfile::create(['user_id' => $user->id, 'company_name' => 'شركة الاختبار']);

        $this->actingAs($user)->post('/employer/jobs', [
            'title' => 'مطور نظم',
            'description' => 'وصف وظيفي واضح ومناسب.',
            'required_skills' => 'Laravel, SQL',
            'status' => 'published',
        ])->assertRedirect();

        $this->assertDatabaseHas('jobs', ['title' => 'مطور نظم', 'status' => 'pending_review']);
    }

    public function test_verified_employer_jobs_are_published_immediately(): void
    {
        $user = User::factory()->create(['role' => 'employer']);
        CompanyProfile::create([
            'user_id' => $user->id,
            'company_name' => 'شركة موثقة',
            'verification_status' => 'verified',
        ]);

        $this->actingAs($user)->post('/employer/jobs', [
            'title' => 'مطور أول',
            'description' => 'وصف وظيفي واضح ومناسب.',
            'required_skills' => 'Laravel, SQL',
            'status' => 'published',
        ])->assertRedirect();

        $this->assertDatabaseHas('jobs', ['title' => 'مطور أول', 'status' => 'published']);
    }

    public function test_employer_can_save_job_as_draft_regardless_of_verification(): void
    {
        $user = User::factory()->create(['role' => 'employer']);
        CompanyProfile::create([
            'user_id' => $user->id,
            'company_name' => 'شركة موثقة',
            'verification_status' => 'verified',
        ]);

        $this->actingAs($user)->post('/employer/jobs', [
            'title' => 'وظيفة مسودة',
            'description' => 'وصف وظيفي واضح ومناسب.',
            'required_skills' => 'Laravel, SQL',
            'status' => 'draft',
        ])->assertRedirect();

        $this->assertDatabaseHas('jobs', ['title' => 'وظيفة مسودة', 'status' => 'draft']);
    }

    public function test_job_seeker_can_apply_to_published_job_and_duplicate_is_blocked(): void
    {
        $employer = User::factory()->create(['role' => 'employer']);
        $company = CompanyProfile::create(['user_id' => $employer->id, 'company_name' => 'شركة']);
        $job = Job::create([
            'company_profile_id' => $company->id,
            'title' => 'مطور Laravel',
            'slug' => 'laravel-dev',
            'description' => 'وصف',
            'required_skills' => ['Laravel'],
            'status' => 'published',
        ]);
        $seeker = User::factory()->create(['role' => 'job_seeker']);
        JobSeekerProfile::create(['user_id' => $seeker->id, 'extracted_skills' => ['Laravel']]);

        $this->actingAs($seeker)->post("/jobs/{$job->id}/apply")->assertRedirect();
        $this->actingAs($seeker)->post("/jobs/{$job->id}/apply")->assertStatus(422);
    }

    public function test_matching_service_returns_expected_score(): void
    {
        $company = CompanyProfile::create(['user_id' => User::factory()->create(['role' => 'employer'])->id, 'company_name' => 'شركة']);
        $job = Job::create(['company_profile_id' => $company->id, 'title' => 'مطور', 'slug' => 'dev', 'description' => 'وصف', 'required_skills' => ['Laravel', 'React'], 'status' => 'published']);
        $profile = JobSeekerProfile::create(['user_id' => User::factory()->create(['role' => 'job_seeker'])->id, 'extracted_skills' => ['Laravel']]);

        $this->assertSame(50, app(MatchingService::class)->match($job, $profile)['score']);
    }

    public function test_matching_normalizes_skills_location_and_arabic_field(): void
    {
        $company = CompanyProfile::create(['user_id' => User::factory()->create(['role' => 'employer'])->id, 'company_name' => 'Company']);
        $job = Job::create([
            'company_profile_id' => $company->id,
            'title' => 'مُطوّر برمجيات',
            'slug' => 'normalized-match',
            'description' => 'Description',
            'required_skills' => ['React.js', 'Laravel'],
            'location' => 'Tripoli - Libya',
            'status' => 'published',
        ]);
        $profile = JobSeekerProfile::create([
            'user_id' => User::factory()->create(['role' => 'job_seeker'])->id,
            'extracted_skills' => ['ReactJS development', 'laravel framework'],
            'city' => ' tripoli, libya ',
            'field' => 'مطور',
        ]);

        $match = app(MatchingService::class)->match($job, $profile);

        $this->assertSame(100, $match['score']);
        $this->assertSame(['React.js', 'Laravel'], $match['matched_skills']);
        $this->assertSame([], $match['missing_skills']);
    }

    public function test_matching_does_not_count_duplicate_required_skills_twice(): void
    {
        $company = CompanyProfile::create(['user_id' => User::factory()->create(['role' => 'employer'])->id, 'company_name' => 'Company']);
        $job = Job::create(['company_profile_id' => $company->id, 'title' => 'Developer', 'slug' => 'duplicate-skills', 'description' => 'Description', 'required_skills' => ['Laravel', ' laravel ', 'React'], 'status' => 'published']);
        $profile = JobSeekerProfile::create(['user_id' => User::factory()->create(['role' => 'job_seeker'])->id, 'extracted_skills' => ['Laravel']]);

        $this->assertSame(50, app(MatchingService::class)->match($job, $profile)['score']);
    }

    public function test_admin_access_is_protected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $seeker = User::factory()->create(['role' => 'job_seeker']);

        $this->actingAs($admin)->get('/admin/dashboard')->assertOk();
        $this->actingAs($seeker)->get('/admin/dashboard')->assertForbidden();
    }

    public function test_admin_can_delete_job_seeker_account(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $seeker = User::factory()->create(['role' => 'job_seeker']);
        JobSeekerProfile::create(['user_id' => $seeker->id]);

        $this->actingAs($admin)
            ->delete("/admin/job-seekers/{$seeker->id}")
            ->assertRedirect('/admin/job-seekers');

        $this->assertDatabaseMissing('users', ['id' => $seeker->id]);
        $this->assertDatabaseMissing('job_seeker_profiles', ['user_id' => $seeker->id]);
    }

    public function test_admin_cannot_delete_non_job_seeker_from_job_seeker_directory(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $employer = User::factory()->create(['role' => 'employer']);

        $this->actingAs($admin)
            ->delete("/admin/job-seekers/{$employer->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('users', ['id' => $employer->id]);
    }

    public function test_admin_can_view_job_applications_and_details(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $employer = User::factory()->create(['role' => 'employer']);
        $company = CompanyProfile::create(['user_id' => $employer->id, 'company_name' => 'شركة الطلبات']);
        $job = Job::create([
            'company_profile_id' => $company->id,
            'title' => 'محلل نظم',
            'slug' => 'systems-analyst',
            'description' => 'وصف الوظيفة',
            'status' => 'published',
        ]);
        $seeker = User::factory()->create(['role' => 'job_seeker', 'name' => 'باحث الطلبات']);
        JobSeekerProfile::create(['user_id' => $seeker->id, 'field' => 'تقنية المعلومات']);
        $application = Application::create([
            'job_id' => $job->id,
            'user_id' => $seeker->id,
            'cover_letter' => 'رسالة تقديم',
            'match_score' => 75,
            'match_summary' => 'مطابقة جيدة',
        ]);

        $this->actingAs($admin)
            ->get('/admin/applications')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Applications')
                ->where('applications.data.0.id', $application->id));

        $this->get("/admin/applications/{$application->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/ApplicationDetails')
                ->where('application.id', $application->id)
                ->where('application.user.name', 'باحث الطلبات'));
    }

    public function test_admin_must_review_job_details_before_approving_or_rejecting(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $employer = User::factory()->create(['role' => 'employer']);
        $company = CompanyProfile::create([
            'user_id' => $employer->id,
            'company_name' => 'شركة الاختبار',
        ]);
        $job = Job::create([
            'company_profile_id' => $company->id,
            'title' => 'وظيفة للمراجعة',
            'slug' => 'job-for-review',
            'description' => 'وصف كامل للوظيفة المطلوب مراجعته.',
            'status' => 'pending_review',
        ]);

        $this->actingAs($admin)
            ->post("/admin/jobs/{$job->id}/approve")
            ->assertForbidden();
        $this->assertSame('pending_review', $job->fresh()->status);

        $this->get("/admin/jobs/{$job->id}/review")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/JobReview')
                ->where('job.id', $job->id));

        $this->post("/admin/jobs/{$job->id}/approve")
            ->assertRedirect('/admin/jobs/pending');
        $this->assertSame('published', $job->fresh()->status);
    }
}
