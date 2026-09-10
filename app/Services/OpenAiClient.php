<?php

namespace App\Services;

use App\Exceptions\OpenAiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Throwable;

class OpenAiClient
{
    public function isConfigured(): bool
    {
        return filled(config('services.openai.key'));
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function text(string $instructions, string $input, array $options = []): string
    {
        return $this->request([
            'instructions' => $instructions,
            'input' => $input,
        ], $options, false);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function textWithFile(string $instructions, UploadedFile $file, string $input, array $options = []): string
    {
        $path = $file->getRealPath();

        if (! is_string($path) || $path === '') {
            throw OpenAiException::missingFile();
        }

        return $this->textWithStoredFile(
            $instructions,
            $path,
            $file->getClientOriginalName(),
            (string) ($options['mime_type'] ?? $file->getMimeType() ?: 'application/octet-stream'),
            $input,
            $options,
        );
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function textWithStoredFile(
        string $instructions,
        string $path,
        string $filename,
        string $mimeType,
        string $input,
        array $options = [],
    ): string {
        if (! is_file($path)) {
            throw OpenAiException::missingFile();
        }

        if (! is_readable($path)) {
            throw OpenAiException::invalidRequest();
        }

        clearstatcache(true, $path);
        $size = filesize($path);
        $maxBytes = isset($options['max_file_bytes']) ? max(1, (int) $options['max_file_bytes']) : null;

        if ($size === false || $size < 1 || ($maxBytes !== null && $size > $maxBytes)) {
            throw OpenAiException::invalidRequest();
        }

        $contents = $maxBytes === null
            ? file_get_contents($path)
            : file_get_contents($path, false, null, 0, $maxBytes + 1);

        if ($contents === false || $contents === '' || ($maxBytes !== null && strlen($contents) > $maxBytes)) {
            throw OpenAiException::invalidRequest();
        }

        $safeFilename = basename(str_replace('\\', '/', $filename));
        $safeMimeType = preg_match('/^[a-z0-9.+-]+\/[a-z0-9.+-]+$/i', $mimeType)
            ? $mimeType
            : 'application/octet-stream';

        if ($safeFilename === '') {
            throw OpenAiException::invalidRequest();
        }

        $fileData = 'data:'.$safeMimeType.';base64,'.base64_encode($contents);

        return $this->request([
            'instructions' => $instructions,
            'input' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'input_file',
                            'filename' => $safeFilename,
                            'file_data' => $fileData,
                        ],
                        [
                            'type' => 'input_text',
                            'text' => $input,
                        ],
                    ],
                ],
            ],
        ], $options, true);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $options
     */
    private function request(array $payload, array $options, bool $usesFileTimeout): string
    {
        if (! $this->isConfigured()) {
            throw OpenAiException::missingConfiguration();
        }

        $payload['model'] = $options['model'] ?? config('services.openai.model');

        if (isset($options['text_format']) && is_array($options['text_format'])) {
            $payload['text'] = ['format' => $options['text_format']];
        }

        if (isset($options['max_output_tokens'])) {
            $payload['max_output_tokens'] = max(1, (int) $options['max_output_tokens']);
        }

        try {
            $response = Http::withToken(config('services.openai.key'))
                ->acceptJson()
                ->connectTimeout((int) config('services.openai.connect_timeout'))
                ->timeout((int) config($usesFileTimeout ? 'services.openai.file_timeout' : 'services.openai.timeout'))
                ->post(rtrim((string) config('services.openai.base_url'), '/').'/responses', $payload);
        } catch (ConnectionException $exception) {
            throw $this->connectionException($exception);
        }

        if ($response->failed()) {
            throw $this->responseException($response);
        }

        $responsePayload = $response->json();

        if (! is_array($responsePayload)) {
            throw new OpenAiException(
                OpenAiException::EMPTY_RESPONSE,
                requestId: $this->requestId($response),
            );
        }

        return $this->extractText($responsePayload, $this->requestId($response));
    }

    private function connectionException(ConnectionException $exception): OpenAiException
    {
        $category = $this->isTimeout($exception)
            ? OpenAiException::TIMEOUT
            : OpenAiException::CONNECTION_FAILED;

        return new OpenAiException($category, true, previous: $exception);
    }

    private function responseException(Response $response): OpenAiException
    {
        $error = $response->json('error');
        $error = is_array($error) ? $error : [];
        $providerType = $this->nullableString($error['type'] ?? null);
        $providerCode = $this->nullableString($error['code'] ?? null);
        $requestId = $this->requestId($response);
        $status = $response->status();
        $modelCodes = ['model_not_found', 'invalid_model', 'unsupported_model', 'model_not_available'];

        if (in_array($providerCode, $modelCodes, true)) {
            return new OpenAiException(
                OpenAiException::INVALID_MODEL,
                httpStatus: $status,
                providerType: $providerType,
                providerCode: $providerCode,
                requestId: $requestId,
            );
        }

        $category = match (true) {
            $status === 401 => OpenAiException::AUTHENTICATION_FAILED,
            $status === 403 => OpenAiException::PERMISSION_DENIED,
            $status === 408 => OpenAiException::TIMEOUT,
            $status === 429 => OpenAiException::RATE_LIMITED,
            $status >= 500 => OpenAiException::PROVIDER_UNAVAILABLE,
            in_array($status, [400, 404, 413, 415, 422], true) => OpenAiException::INVALID_REQUEST,
            default => OpenAiException::UNKNOWN_PROVIDER_FAILURE,
        };

        $retryable = in_array($category, [
            OpenAiException::TIMEOUT,
            OpenAiException::RATE_LIMITED,
            OpenAiException::PROVIDER_UNAVAILABLE,
        ], true);

        return new OpenAiException(
            $category,
            $retryable,
            $status,
            $providerType,
            $providerCode,
            $requestId,
            $retryable ? $this->retryAfterSeconds($response) : null,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractText(array $payload, ?string $requestId): string
    {
        if (($payload['status'] ?? null) === 'incomplete') {
            throw new OpenAiException(OpenAiException::INCOMPLETE_RESPONSE, requestId: $requestId);
        }

        if (isset($payload['output_text']) && is_string($payload['output_text']) && trim($payload['output_text']) !== '') {
            return trim($payload['output_text']);
        }

        $parts = [];

        foreach ($payload['output'] ?? [] as $item) {
            if (! is_array($item)) {
                continue;
            }

            foreach ($item['content'] ?? [] as $content) {
                if (! is_array($content)) {
                    continue;
                }

                if (($content['type'] ?? null) === 'refusal' || filled($content['refusal'] ?? null)) {
                    throw new OpenAiException(OpenAiException::REFUSAL, requestId: $requestId);
                }

                if (($content['type'] ?? null) === 'output_text' && isset($content['text']) && is_string($content['text'])) {
                    $parts[] = $content['text'];
                }
            }
        }

        $text = trim(implode("\n", $parts));

        if ($text === '') {
            throw new OpenAiException(OpenAiException::EMPTY_RESPONSE, requestId: $requestId);
        }

        return $text;
    }

    private function requestId(Response $response): ?string
    {
        foreach (['x-request-id', 'openai-request-id', 'request-id'] as $header) {
            $value = $response->header($header);

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function retryAfterSeconds(Response $response): ?int
    {
        $value = $response->header('Retry-After');

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        if (ctype_digit(trim($value))) {
            return max(1, (int) trim($value));
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? null : max(1, $timestamp - time());
    }

    private function isTimeout(Throwable $exception): bool
    {
        for ($current = $exception; $current; $current = $current->getPrevious()) {
            if ((int) $current->getCode() === 28
                || preg_match('/(?:timed?\s*out|timeout|cURL error 28)/i', $current->getMessage()) === 1) {
                return true;
            }
        }

        return false;
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
