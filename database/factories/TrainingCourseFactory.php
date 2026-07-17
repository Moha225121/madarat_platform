<?php

namespace Database\Factories;

use App\Models\TrainingCourse;
use App\Models\TrainingProviderProfile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TrainingCourseFactory extends Factory
{
    protected $model = TrainingCourse::class;

    public function definition(): array
    {
        $title = fake()->unique()->sentence(3);

        return ['training_provider_id' => TrainingProviderProfile::factory(), 'title' => $title, 'slug' => Str::slug($title).'-'.Str::random(5), 'description' => fake()->paragraphs(2, true), 'skills_taught' => ['Laravel'], 'prerequisites' => [], 'difficulty_level' => 'beginner', 'delivery_method' => 'online', 'is_remote' => true, 'price' => 100, 'currency' => 'LYD', 'status' => 'draft'];
    }
}
