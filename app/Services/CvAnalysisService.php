<?php

namespace App\Services;

use App\Exceptions\OpenAiException;
use App\Models\JobSeekerProfile;
use Illuminate\Http\UploadedFile;
use JsonException;

class CvAnalysisService
{
    public const MAX_FILE_BYTES = 15 * 1024 * 1024;

    /** @var array<string, string> */
    private const MIME_TYPES = [
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    /** @var list<string> */
    private const RESULT_KEYS = [
        'score',
        'extracted_skills',
        'missing_skills',
        'education_summary',
        'experience_summary',
        'strengths',
        'recommendations',
    ];

    public function __construct(private OpenAiClient $openAi) {}

    /**
     * @return array{
     *     score: int,
     *     extracted_skills: list<string>,
     *     missing_skills: list<string>,
     *     education_summary: string,
     *     experience_summary: string,
     *     strengths: list<string>,
     *     recommendations: list<string>
     * }
     */
    public function analyze(UploadedFile $file, JobSeekerProfile $profile): array
    {
        if (! $this->openAi->isConfigured()) {
            throw OpenAiException::missingConfiguration();
        }

        $extension = strtolower($file->getClientOriginalExtension());
        $path = $file->getRealPath();
        $size = is_string($path) && is_file($path) ? filesize($path) : false;

        if (! isset(self::MIME_TYPES[$extension])) {
            throw OpenAiException::invalidRequest();
        }

        if (! is_string($path) || ! is_file($path) || ! is_readable($path)) {
            throw OpenAiException::missingFile();
        }

        if ($size === false || $size < 1 || $size > self::MAX_FILE_BYTES) {
            throw OpenAiException::invalidRequest();
        }

        $instructions = 'أنت محلل سير ذاتية لمنصة مدارات. حلّل الملف المرفق بموضوعية، واكتب النتائج بالعربية المهنية المختصرة. التزم حصراً ببنية النتيجة المطلوبة، ولا تخترع معلومات غير موجودة في السيرة.';
        $context = [
            'target_field' => $profile->field,
            'headline' => $profile->headline,
            'city' => $profile->city,
            'bio' => $profile->bio,
        ];
        $prompt = "حلّل السيرة الذاتية المرفقة مع مراعاة سياق الباحث التالي:\n".
            (json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '{}');

        $text = $this->openAi->textWithFile($instructions, $file, $prompt, [
            'mime_type' => self::MIME_TYPES[$extension],
            'max_file_bytes' => self::MAX_FILE_BYTES,
            'max_output_tokens' => 2000,
            'text_format' => $this->structuredOutputFormat(),
        ]);

        try {
            $decoded = json_decode($text, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw OpenAiException::invalidStructuredOutput($exception);
        }

        if (! is_array($decoded)) {
            throw OpenAiException::invalidStructuredOutput();
        }

        return $this->normalize($decoded);
    }

    /**
     * @return array<string, mixed>
     */
    private function structuredOutputFormat(): array
    {
        return [
            'type' => 'json_schema',
            'name' => 'cv_analysis',
            'strict' => true,
            'schema' => [
                'type' => 'object',
                'additionalProperties' => false,
                'properties' => [
                    'score' => [
                        'type' => 'integer',
                        'minimum' => 0,
                        'maximum' => 100,
                    ],
                    'extracted_skills' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                    ],
                    'missing_skills' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                    ],
                    'education_summary' => ['type' => 'string'],
                    'experience_summary' => ['type' => 'string'],
                    'strengths' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                    ],
                    'recommendations' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                    ],
                ],
                'required' => self::RESULT_KEYS,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array{
     *     score: int,
     *     extracted_skills: list<string>,
     *     missing_skills: list<string>,
     *     education_summary: string,
     *     experience_summary: string,
     *     strengths: list<string>,
     *     recommendations: list<string>
     * }
     */
    private function normalize(array $result): array
    {
        $keys = array_keys($result);
        sort($keys);
        $expectedKeys = self::RESULT_KEYS;
        sort($expectedKeys);

        if ($keys !== $expectedKeys || ! is_int($result['score'])) {
            throw OpenAiException::invalidStructuredOutput();
        }

        $education = $this->requiredString($result['education_summary']);
        $experience = $this->requiredString($result['experience_summary']);

        return [
            'score' => max(0, min(100, $result['score'])),
            'extracted_skills' => $this->requiredStringList($result['extracted_skills']),
            'missing_skills' => $this->requiredStringList($result['missing_skills']),
            'education_summary' => $education,
            'experience_summary' => $experience,
            'strengths' => $this->requiredStringList($result['strengths']),
            'recommendations' => $this->requiredStringList($result['recommendations']),
        ];
    }

    private function requiredString(mixed $value): string
    {
        if (! is_string($value) || trim($value) === '') {
            throw OpenAiException::invalidStructuredOutput();
        }

        return trim($value);
    }

    /**
     * @return list<string>
     */
    private function requiredStringList(mixed $value): array
    {
        if (! is_array($value) || ! array_is_list($value) || $value === []) {
            throw OpenAiException::invalidStructuredOutput();
        }

        $normalized = [];

        foreach ($value as $item) {
            if (! is_string($item) || trim($item) === '') {
                throw OpenAiException::invalidStructuredOutput();
            }

            $normalized[] = trim($item);
        }

        return array_values(array_unique($normalized));
    }
}
