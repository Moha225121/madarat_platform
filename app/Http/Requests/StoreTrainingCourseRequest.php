<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTrainingCourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'training_provider';
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'], 'short_description' => ['nullable', 'string', 'max:500'], 'description' => ['required', 'string', 'max:10000'],
            'learning_outcomes' => ['nullable'], 'skills_taught' => ['required'], 'target_audience' => ['nullable', 'string', 'max:3000'], 'prerequisites' => ['nullable'],
            'difficulty_level' => ['required', 'in:beginner,intermediate,advanced,all_levels'], 'delivery_method' => ['required', 'in:in_person,online,hybrid'],
            'city' => ['nullable', 'string', 'max:255'], 'location' => ['nullable', 'string', 'max:500'], 'is_remote' => ['boolean'],
            'duration_value' => ['nullable', 'integer', 'min:1'], 'duration_unit' => ['nullable', 'in:hours,days,weeks,months'],
            'start_date' => ['nullable', 'date'], 'end_date' => ['nullable', 'date', 'after_or_equal:start_date'], 'registration_deadline' => ['nullable', 'date', 'before_or_equal:end_date'],
            'price' => ['nullable', 'numeric', 'min:0'], 'currency' => ['nullable', 'string', 'size:3'], 'capacity' => ['nullable', 'integer', 'min:1'],
            'contact_email' => ['nullable', 'email'], 'contact_phone' => ['nullable', 'string', 'max:30'], 'registration_url' => ['nullable', 'url', 'max:2048'],
            'cover_image' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:4096'], 'certificate_available' => ['boolean'],
        ];
    }
}
