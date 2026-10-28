<?php

namespace App\Http\Requests;

use App\Models\JobSubcategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreJobRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->role === 'employer';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'required_skills' => ['nullable'],
            'responsibilities' => ['nullable'],
            'category' => [
                'required',
                'string',
                Rule::exists('job_categories', 'key'),
            ],
            'subcategory' => [
                'nullable',
                'string',
                Rule::exists('job_subcategories', 'key'),
            ],
            'location' => ['nullable', 'string', 'max:255'],
            'job_type' => ['nullable', 'string', 'max:255'],
            'contract_type' => ['nullable', 'string', 'max:255'],
            'experience_level' => ['nullable', 'string', 'max:255'],
            'salary_min' => ['nullable', 'integer', 'min:0'],
            'salary_max' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', 'in:draft,published,closed,pending_review'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $category = $this->input('category');
                $subcategory = $this->input('subcategory');

                if ($subcategory === null || $subcategory === '') {
                    return;
                }

                $storedSubcategory = JobSubcategory::query()
                    ->where('key', $subcategory)
                    ->first(['category_key']);

                if ($storedSubcategory !== null && $storedSubcategory->category_key !== $category) {
                    $validator->errors()->add(
                        'subcategory',
                        'الفئة الفرعية المحددة لا تنتمي إلى الفئة الرئيسية.',
                    );
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'category.required' => 'الفئة الوظيفية مطلوبة.',
            'category.exists' => 'الفئة الوظيفية المحددة لم تعد متاحة. حدّث الصفحة واختر فئة أخرى.',
            'subcategory.exists' => 'الفئة الفرعية المحددة لم تعد متاحة. حدّث الصفحة واختر فئة أخرى.',
        ];
    }
}
