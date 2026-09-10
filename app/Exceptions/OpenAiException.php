<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class OpenAiException extends RuntimeException
{
    public const MISSING_CONFIGURATION = 'missing_configuration';

    public const AUTHENTICATION_FAILED = 'authentication_failed';

    public const PERMISSION_DENIED = 'permission_denied';

    public const INVALID_MODEL = 'invalid_model';

    public const INVALID_REQUEST = 'invalid_request';

    public const RATE_LIMITED = 'rate_limited';

    public const CONNECTION_FAILED = 'connection_failed';

    public const TIMEOUT = 'timeout';

    public const PROVIDER_UNAVAILABLE = 'provider_unavailable';

    public const INCOMPLETE_RESPONSE = 'incomplete_response';

    public const REFUSAL = 'refusal';

    public const EMPTY_RESPONSE = 'empty_response';

    public const INVALID_STRUCTURED_OUTPUT = 'invalid_structured_output';

    public const MISSING_FILE = 'missing_file';

    public const UNKNOWN_PROVIDER_FAILURE = 'unknown_provider_failure';

    public const QUEUE_UNAVAILABLE = 'queue_unavailable';

    public function __construct(
        public readonly string $category,
        public readonly bool $retryable = false,
        public readonly ?int $httpStatus = null,
        public readonly ?string $providerType = null,
        public readonly ?string $providerCode = null,
        public readonly ?string $requestId = null,
        public readonly ?int $retryAfterSeconds = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct("OpenAI request failed ({$category}).", 0, $previous);
    }

    public static function missingConfiguration(): self
    {
        return new self(self::MISSING_CONFIGURATION);
    }

    public static function missingFile(): self
    {
        return new self(self::MISSING_FILE);
    }

    public static function invalidRequest(?Throwable $previous = null): self
    {
        return new self(self::INVALID_REQUEST, previous: $previous);
    }

    public static function invalidStructuredOutput(?Throwable $previous = null): self
    {
        return new self(self::INVALID_STRUCTURED_OUTPUT, previous: $previous);
    }

    public static function queueUnavailable(Throwable $previous): self
    {
        return new self(self::QUEUE_UNAVAILABLE, previous: $previous);
    }

    public static function unknown(Throwable $previous): self
    {
        return new self(self::UNKNOWN_PROVIDER_FAILURE, previous: $previous);
    }

    public function userMessage(): string
    {
        return match ($this->category) {
            self::INVALID_REQUEST,
            self::MISSING_FILE => 'تعذر قراءة ملف السيرة الذاتية. تأكد من أن الملف غير تالف وبصيغة PDF أو DOC أو DOCX ثم حاول مرة أخرى.',
            self::CONNECTION_FAILED,
            self::TIMEOUT,
            self::RATE_LIMITED,
            self::PROVIDER_UNAVAILABLE,
            self::INCOMPLETE_RESPONSE,
            self::REFUSAL,
            self::EMPTY_RESPONSE,
            self::INVALID_STRUCTURED_OUTPUT => 'لم يكتمل التحليل بسبب مشكلة مؤقتة في خدمة الذكاء الاصطناعي. يرجى إعادة المحاولة.',
            default => 'خدمة تحليل السيرة الذاتية غير متاحة حاليًا. يرجى المحاولة لاحقًا أو التواصل مع إدارة المنصة.',
        };
    }
}
