<?php

namespace Tests\Feature\Auth;

use App\Models\TrainingProviderProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'role' => 'job_seeker',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_each_account_type_has_a_preselected_registration_form(): void
    {
        foreach (['job-seeker', 'employer', 'trainer', 'training-company'] as $accountType) {
            $this->get("/register/{$accountType}")
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('Auth/Register')
                    ->where('accountType', $accountType));
        }
    }

    public function test_trainer_and_training_company_accounts_store_the_correct_provider_type(): void
    {
        foreach (['trainer' => 'trainer', 'training-company' => 'company'] as $accountType => $providerType) {
            $this->post('/register', [
                'name' => $accountType,
                'email' => "{$accountType}@example.com",
                'account_type' => $accountType,
                'password' => 'password',
                'password_confirmation' => 'password',
            ])->assertRedirect('/dashboard');

            $this->assertSame(
                $providerType,
                TrainingProviderProfile::where('email', "{$accountType}@example.com")->value('provider_type'),
            );

            auth()->logout();
        }
    }
}
