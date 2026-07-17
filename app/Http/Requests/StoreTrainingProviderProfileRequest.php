<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTrainingProviderProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'training_provider';
    }

    public function rules(): array
    {
        return [
            'provider_type' => ['required', 'in:company,trainer'], 'display_name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'], 'description' => ['required', 'string', 'max:5000'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'], 'profile_image' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'email' => ['required', 'email', 'max:255'], 'phone' => ['required', 'string', 'max:30'], 'website' => ['nullable', 'url', 'max:255'],
            'city' => ['required', 'string', 'max:255'], 'address' => ['nullable', 'string', 'max:500'], 'specializations' => ['required'],
            'years_of_experience' => ['nullable', 'integer', 'min:0', 'max:100'], 'commercial_registration_number' => ['nullable', 'string', 'max:255'], 'certifications' => ['nullable'],
        ];
    }
}
