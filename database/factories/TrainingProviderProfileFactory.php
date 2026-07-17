<?php

namespace Database\Factories;

use App\Models\TrainingProviderProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TrainingProviderProfileFactory extends Factory
{
    protected $model = TrainingProviderProfile::class;

    public function definition(): array
    {
        return ['user_id' => User::factory()->state(['role' => 'training_provider']), 'provider_type' => 'company', 'display_name' => fake()->company(), 'description' => fake()->paragraph(), 'email' => fake()->unique()->companyEmail(), 'phone' => fake()->phoneNumber(), 'city' => fake()->city(), 'specializations' => ['Laravel'], 'commercial_registration_number' => fake()->uuid(), 'verification_status' => 'incomplete'];
    }
}
