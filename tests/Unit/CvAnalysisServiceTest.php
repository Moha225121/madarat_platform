<?php

namespace Tests\Unit;

use App\Exceptions\OpenAiException;
use App\Models\JobSeekerProfile;
use App\Services\CvAnalysisService;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Throwable;

class CvAnalysisServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.openai.key' => 'test-key-never-sent-to-a-real-provider',
            'services.openai.model' => 'cv-test-model',
            'services.openai.base_url' => 'https://openai.test/v1',
            'services.openai.connect_timeout' => 2,
            'services.openai.timeout' => 3,
            'services.openai.file_timeout' => 4,
        ]);

        Http::preventStrayRequests();
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function cvFileCases(): array
    {
        return [
            'PDF' => ['pdf', 'application/pdf'],
            'legacy DOC' => ['doc', 'application/msword'],
            'DOCX' => ['docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        ];
    }

    #[DataProvider('cvFileCases')]
    public function test_every_supported_cv_type_uses_file_input_and_the_strict_schema(string $extension, string $mimeType): void
    {
        $contents = "non-empty {$extension} test bytes";
        $file = UploadedFile::fake()
            ->createWithContent("candidate-resume.{$extension}", $contents)
            ->mimeType('application/octet-stream');
        $profile = new JobSeekerProfile([
            'field' => 'هندسة البرمجيات',
            'headline' => 'مطور',
            'city' => 'طرابلس',
            'bio' => 'نبذة اختبارية',
        ]);
        $providerResult = $this->validProviderResult([
            'score' => 130,
            'extracted_skills' => [' Laravel ', 'Laravel', 'PHP'],
        ]);

        Http::fake(['*' => Http::response(['output_text' => json_encode($providerResult, JSON_UNESCAPED_UNICODE)])]);

        $result = app(CvAnalysisService::class)->analyze($file, $profile);

        $this->assertSame(100, $result['score']);
        $this->assertSame(['Laravel', 'PHP'], $result['extracted_skills']);
        $this->assertSame($providerResult['missing_skills'], $result['missing_skills']);
        $this->assertSame($providerResult['strengths'], $result['strengths']);
        $this->assertSame($providerResult['recommendations'], $result['recommendations']);

        Http::assertSent(function (Request $request) use ($extension, $mimeType, $contents): bool {
            $payload = $request->data();
            $filePart = $payload['input'][0]['content'][0] ?? [];
            $textPart = $payload['input'][0]['content'][1] ?? [];
            $format = $payload['text']['format'] ?? [];
            $schema = $format['schema'] ?? [];
            $properties = array_keys($schema['properties'] ?? []);
            $required = $schema['required'] ?? [];
            sort($properties);
            sort($required);
            $expected = [
                'education_summary',
                'experience_summary',
                'extracted_skills',
                'missing_skills',
                'recommendations',
                'score',
                'strengths',
            ];

            return $request->url() === 'https://openai.test/v1/responses'
                && $payload['model'] === 'cv-test-model'
                && $payload['max_output_tokens'] === 2000
                && $filePart['type'] === 'input_file'
                && $filePart['filename'] === "candidate-resume.{$extension}"
                && $filePart['file_data'] === "data:{$mimeType};base64,".base64_encode($contents)
                && $textPart['type'] === 'input_text'
                && str_contains($textPart['text'], 'هندسة البرمجيات')
                && $format['type'] === 'json_schema'
                && $format['name'] === 'cv_analysis'
                && $format['strict'] === true
                && $schema['type'] === 'object'
                && $schema['additionalProperties'] === false
                && $schema['properties']['score'] === [
                    'type' => 'integer',
                    'minimum' => 0,
                    'maximum' => 100,
                ]
                && $schema['properties']['extracted_skills']['items'] === ['type' => 'string']
                && $schema['properties']['missing_skills']['items'] === ['type' => 'string']
                && $schema['properties']['strengths']['items'] === ['type' => 'string']
                && $schema['properties']['recommendations']['items'] === ['type' => 'string']
                && $properties === $expected
                && $required === $expected;
        });
        Http::assertSentCount(1);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function malformedStructuredOutputCases(): array
    {
        $valid = [
            'score' => 75,
            'extracted_skills' => ['PHP'],
            'missing_skills' => ['TypeScript'],
            'education_summary' => 'ملخص التعليم',
            'experience_summary' => 'ملخص الخبرة',
            'strengths' => ['حل المشكلات'],
            'recommendations' => ['تطوير المشاريع'],
        ];

        $missing = $valid;
        unset($missing['strengths']);
        $extra = [...$valid, 'unexpected' => 'not allowed'];
        $stringScore = [...$valid, 'score' => '75'];
        $emptyArray = [...$valid, 'recommendations' => []];
        $malformedArray = [...$valid, 'strengths' => ['valid', 12]];
        $blankSummary = [...$valid, 'education_summary' => '   '];

        return [
            'not JSON' => ['not-json'],
            'non-object JSON' => [json_encode('text')],
            'missing required field' => [json_encode($missing)],
            'extra field' => [json_encode($extra)],
            'non-integer score' => [json_encode($stringScore)],
            'empty required array' => [json_encode($emptyArray)],
            'malformed array item' => [json_encode($malformedArray)],
            'blank required summary' => [json_encode($blankSummary)],
        ];
    }

    #[DataProvider('malformedStructuredOutputCases')]
    public function test_malformed_or_incomplete_structured_output_is_never_accepted(string $output): void
    {
        Http::fake(['*' => Http::response(['output_text' => $output])]);
        $file = UploadedFile::fake()->createWithContent('resume.pdf', '%PDF test');

        $exception = $this->capture(fn () => app(CvAnalysisService::class)->analyze(
            $file,
            new JobSeekerProfile,
        ));

        $this->assertSame(OpenAiException::INVALID_STRUCTURED_OUTPUT, $exception->category);
        $this->assertFalse($exception->retryable);
    }

    public function test_unsupported_and_empty_files_are_rejected_without_an_openai_request(): void
    {
        Http::fake();

        $unsupported = $this->capture(fn () => app(CvAnalysisService::class)->analyze(
            UploadedFile::fake()->createWithContent('resume.txt', 'plain text'),
            new JobSeekerProfile,
        ));
        $this->assertSame(OpenAiException::INVALID_REQUEST, $unsupported->category);

        $empty = $this->capture(fn () => app(CvAnalysisService::class)->analyze(
            UploadedFile::fake()->createWithContent('resume.doc', ''),
            new JobSeekerProfile,
        ));
        $this->assertSame(OpenAiException::INVALID_REQUEST, $empty->category);

        Http::assertNothingSent();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validProviderResult(array $overrides = []): array
    {
        return array_replace([
            'score' => 87,
            'extracted_skills' => ['Laravel', 'PHP'],
            'missing_skills' => ['TypeScript'],
            'education_summary' => 'بكالوريوس في علوم الحاسوب',
            'experience_summary' => 'خبرة في تطوير تطبيقات الويب',
            'strengths' => ['حل المشكلات', 'التعاون'],
            'recommendations' => ['تعلم TypeScript', 'إضافة نتائج قابلة للقياس'],
        ], $overrides);
    }

    private function capture(callable $callback): OpenAiException
    {
        try {
            $callback();
        } catch (OpenAiException $exception) {
            return $exception;
        } catch (Throwable $exception) {
            $this->fail('Expected OpenAiException, received '.get_class($exception).': '.$exception->getMessage());
        }

        $this->fail('Expected an OpenAiException to be thrown.');
    }
}
