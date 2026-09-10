<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
            'cv' => ['required', 'file', 'mimes:pdf,doc,docx', 'extensions:pdf,doc,docx', 'max:15360'],
        ];
    }

    /** @return array<callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $file = $this->file('cv');

                if (! $file || $validator->errors()->has('cv')) {
                    return;
                }

                $declaredExtension = strtolower($file->getClientOriginalExtension());
                $detectedExtension = strtolower((string) $file->guessExtension());

                if ($declaredExtension !== $detectedExtension) {
                    $validator->errors()->add('cv', 'يجب أن يتطابق امتداد ملف السيرة الذاتية مع نوعه الفعلي.');
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'cv.required' => 'يرجى اختيار ملف السيرة الذاتية.',
            'cv.mimes' => 'يجب أن تكون السيرة بصيغة PDF أو DOC أو DOCX.',
            'cv.extensions' => 'يجب أن يكون امتداد السيرة PDF أو DOC أو DOCX.',
            'cv.max' => 'يجب ألا يتجاوز حجم السيرة الذاتية 15 ميجابايت.',
        ];
    }
}
