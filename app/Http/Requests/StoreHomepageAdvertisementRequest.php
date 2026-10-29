<?php

namespace App\Http\Requests;

use App\Services\UploadStorage;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreHomepageAdvertisementRequest extends FormRequest
{
    public const INVALID_IMAGE_MESSAGE = 'تعذر رفع الصورة. تأكد من اختيار صورة صحيحة بصيغة JPG أو PNG أو WebP وبحجم لا يتجاوز 5 ميجابايت.';

    private static function invalidImageMessage(): string
    {
        return str_replace('5 ميجابايت', (UploadStorage::maxKilobytes('advertisement') / 1024).' ميجابايت', self::INVALID_IMAGE_MESSAGE);
    }

    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'image' => [
                'bail',
                'required',
                'file',
                'image',
                'mimetypes:image/jpeg,image/png,image/webp',
                'mimes:jpg,jpeg,png,webp',
                'extensions:jpg,jpeg,png,webp',
                'max:'.UploadStorage::maxKilobytes('advertisement'),
            ],
            'alt_text' => ['nullable', 'string', 'max:160'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'image.required' => self::invalidImageMessage(),
            'image.file' => self::invalidImageMessage(),
            'image.image' => self::invalidImageMessage(),
            'image.mimetypes' => self::invalidImageMessage(),
            'image.mimes' => self::invalidImageMessage(),
            'image.extensions' => self::invalidImageMessage(),
            'image.max' => self::invalidImageMessage(),
            'alt_text.string' => 'يجب أن يكون الوصف البديل للصورة نصًا.',
            'alt_text.max' => 'يجب ألا يتجاوز الوصف البديل للصورة 160 حرفًا.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $altText = $this->input('alt_text');

        if (is_string($altText)) {
            $altText = trim($altText);
            $this->merge(['alt_text' => $altText === '' ? null : $altText]);
        }
    }

    protected function failedValidation(Validator $validator): void
    {
        $altTextMessage = $validator->errors()->first('alt_text');
        $message = $validator->errors()->has('image') || $altTextMessage === ''
            ? self::invalidImageMessage()
            : $altTextMessage;

        $this->session()->flash('error', $message);

        parent::failedValidation($validator);
    }
}
