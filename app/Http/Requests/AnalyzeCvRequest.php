<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AnalyzeCvRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->role === 'job_seeker';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'cv' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:15360'],
        ];
    }

    public function messages(): array
    {
        return [
            'cv.required' => 'يرجى اختيار ملف السيرة الذاتية.',
            'cv.mimes' => 'يجب أن تكون السيرة بصيغة PDF أو DOC أو DOCX.',
            'cv.max' => 'يجب ألا يتجاوز حجم السيرة الذاتية 15 ميجابايت.',
        ];
    }
}
