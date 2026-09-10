<?php

namespace Tests\Unit;

use App\Exceptions\OpenAiException;
use App\Services\OpenAiClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Throwable;

class OpenAiClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.openai.key' => 'test-key-never-sent-to-a-real-provider',
            'services.openai.model' => 'test-default-model',
            'services.openai.base_url' => 'https://openai.test/v1',
            'services.openai.connect_timeout' => 2,
            'services.openai.timeout' => 3,
            'services.openai.file_timeout' => 4,
        ]);

        Http::preventStrayRequests();
    }

    public function test_missing_configuration_is_typed_and_sends_no_request(): void
    {
        config(['services.openai.key' => null]);
        Http::fake();

        $exception = $this->capture(fn () => $this->client()->text('instructions', 'input'));

        $this->assertSame(OpenAiException::MISSING_CONFIGURATION, $exception->category);
        $this->assertFalse($exception->retryable);
        Http::assertNothingSent();
    }

    /**
     * @return array<string, array{int, string, string, bool}>
     */
    public static function providerFailureCases(): array
    {
        return [
            'authentication' => [401, 'invalid_api_key', OpenAiException::AUTHENTICATION_FAILED, false],
            'permission' => [403, 'permission_denied', OpenAiException::PERMISSION_DENIED, false],
            'invalid request' => [400, 'invalid_file', OpenAiException::INVALID_REQUEST, false],
            'rate limit' => [429, 'rate_limit_exceeded', OpenAiException::RATE_LIMITED, true],
            'server error' => [500, 'server_error', OpenAiException::PROVIDER_UNAVAILABLE, true],
            'bad gateway' => [502, 'server_error', OpenAiException::PROVIDER_UNAVAILABLE, true],
            'unavailable' => [503, 'server_error', OpenAiException::PROVIDER_UNAVAILABLE, true],
        ];
    }

    #[DataProvider('providerFailureCases')]
    public function test_http_failures_are_typed_and_only_transient_failures_are_retryable(
        int $status,
        string $providerCode,
        string $category,
        bool $retryable,
    ): void {
        Http::fake([
            '*' => Http::response([
                'error' => [
                    'type' => 'provider_error_type',
                    'code' => $providerCode,
                    'message' => 'raw-provider-marker-that-must-not-be-exposed',
                ],
            ], $status, [
                'x-request-id' => 'request-test-123',
                'Retry-After' => '17',
            ]),
        ]);

        $exception = $this->capture(fn () => $this->client()->text('instructions', 'input'));

        $this->assertSame($category, $exception->category);
        $this->assertSame($retryable, $exception->retryable);
        $this->assertSame($status, $exception->httpStatus);
        $this->assertSame('provider_error_type', $exception->providerType);
        $this->assertSame($providerCode, $exception->providerCode);
        $this->assertSame('request-test-123', $exception->requestId);
        $this->assertSame($retryable ? 17 : null, $exception->retryAfterSeconds);
        $this->assertStringNotContainsString('raw-provider-marker', $exception->getMessage());
        Http::assertSentCount(1);
    }

    public function test_rejected_model_is_a_permanent_typed_failure(): void
    {
        Http::fake([
            '*' => Http::response([
                'error' => [
                    'type' => 'invalid_request_error',
                    'code' => 'model_not_found',
                ],
            ], 400),
        ]);

        $exception = $this->capture(fn () => $this->client()->text('instructions', 'input'));

        $this->assertSame(OpenAiException::INVALID_MODEL, $exception->category);
        $this->assertFalse($exception->retryable);
        $this->assertSame(400, $exception->httpStatus);
        $this->assertSame('model_not_found', $exception->providerCode);
        Http::assertSentCount(1);
    }

    public function test_connection_failure_is_typed_and_retryable(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 7: Could not connect'));

        $connection = $this->capture(fn () => $this->client()->text('instructions', 'input'));

        $this->assertSame(OpenAiException::CONNECTION_FAILED, $connection->category);
        $this->assertTrue($connection->retryable);
    }

    public function test_timeout_is_typed_and_retryable(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

        $timeout = $this->capture(fn () => $this->client()->text('instructions', 'input'));

        $this->assertSame(OpenAiException::TIMEOUT, $timeout->category);
        $this->assertTrue($timeout->retryable);
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function unsuccessfulResponseCases(): array
    {
        return [
            'empty response' => [[], OpenAiException::EMPTY_RESPONSE],
            'empty output' => [['status' => 'completed', 'output' => []], OpenAiException::EMPTY_RESPONSE],
            'incomplete response' => [['status' => 'incomplete', 'output' => []], OpenAiException::INCOMPLETE_RESPONSE],
            'refusal' => [[
                'status' => 'completed',
                'output' => [[
                    'content' => [[
                        'type' => 'refusal',
                        'refusal' => 'provider refusal text',
                    ]],
                ]],
            ], OpenAiException::REFUSAL],
        ];
    }

    #[DataProvider('unsuccessfulResponseCases')]
    public function test_unsuccessful_response_shapes_have_specific_safe_categories(array $payload, string $category): void
    {
        Http::fake(['*' => Http::response($payload, 200, ['x-request-id' => 'response-id'])]);

        $exception = $this->capture(fn () => $this->client()->text('instructions', 'input'));

        $this->assertSame($category, $exception->category);
        $this->assertSame('response-id', $exception->requestId);
        $this->assertStringNotContainsString('provider refusal text', $exception->getMessage());
    }

    public function test_plain_text_call_remains_backward_compatible_and_does_not_add_cv_options(): void
    {
        Http::fake(['*' => Http::response(['output_text' => '  إجابة مهنية  '])]);

        $result = $this->client()->text('تعليمات', 'سؤال');

        $this->assertSame('إجابة مهنية', $result);
        Http::assertSent(function ($request): bool {
            $payload = $request->data();

            return $request->url() === 'https://openai.test/v1/responses'
                && $payload['model'] === 'test-default-model'
                && $payload['instructions'] === 'تعليمات'
                && $payload['input'] === 'سؤال'
                && ! array_key_exists('text', $payload)
                && ! array_key_exists('max_output_tokens', $payload);
        });
    }

    public function test_supported_request_options_can_override_model_format_and_output_limit(): void
    {
        Http::fake(['*' => Http::response(['output_text' => '{"answer":"ok"}'])]);
        $format = [
            'type' => 'json_schema',
            'name' => 'caller_schema',
            'strict' => true,
            'schema' => [
                'type' => 'object',
                'additionalProperties' => false,
                'properties' => ['answer' => ['type' => 'string']],
                'required' => ['answer'],
            ],
        ];

        $result = $this->client()->text('instructions', 'input', [
            'model' => 'explicit-model-override',
            'text_format' => $format,
            'max_output_tokens' => 321,
        ]);

        $this->assertSame('{"answer":"ok"}', $result);
        Http::assertSent(fn ($request): bool => $request['model'] === 'explicit-model-override'
            && $request['text']['format'] === $format
            && $request['max_output_tokens'] === 321);
        Http::assertSentCount(1);
    }

    public function test_stored_file_call_rejects_a_missing_or_empty_file_before_http(): void
    {
        Http::fake();

        $missing = $this->capture(fn () => $this->client()->textWithStoredFile(
            'instructions',
            storage_path('app/definitely-missing-cv.pdf'),
            'cv.pdf',
            'application/pdf',
            'input',
        ));

        $this->assertSame(OpenAiException::MISSING_FILE, $missing->category);

        $emptyPath = tempnam(sys_get_temp_dir(), 'empty-cv-');
        $this->assertIsString($emptyPath);

        try {
            $empty = $this->capture(fn () => $this->client()->textWithStoredFile(
                'instructions',
                $emptyPath,
                'cv.pdf',
                'application/pdf',
                'input',
            ));

            $this->assertSame(OpenAiException::INVALID_REQUEST, $empty->category);
        } finally {
            @unlink($emptyPath);
        }

        Http::assertNothingSent();
    }

    public function test_file_read_respects_the_explicit_byte_limit_before_sending(): void
    {
        Http::fake();
        $path = tempnam(sys_get_temp_dir(), 'bounded-cv-');
        $this->assertIsString($path);
        file_put_contents($path, '123456');

        try {
            $exception = $this->capture(fn () => $this->client()->textWithStoredFile(
                'instructions',
                $path,
                'cv.pdf',
                'application/pdf',
                'input',
                ['max_file_bytes' => 5],
            ));

            $this->assertSame(OpenAiException::INVALID_REQUEST, $exception->category);
        } finally {
            @unlink($path);
        }

        Http::assertNothingSent();
    }

    private function client(): OpenAiClient
    {
        return app(OpenAiClient::class);
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
