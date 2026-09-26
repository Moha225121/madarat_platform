<?php

namespace App\Jobs;

use App\Exceptions\OpenAiException;
use App\Models\JobSeekerProfile;
use App\Services\CvAnalysisService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\UploadedFile;
use Illuminate\Queue\TimeoutExceededException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class AnalyzeCv implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public bool $failOnTimeout = true;

    public function __construct(
        public int $profileId,
        public string $path,
        public string $originalName,
        public string $attemptId,
    ) {}

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(CvAnalysisService $service): void
    {
        $profile = JobSeekerProfile::find($this->profileId);

        if (! $profile) {
            Log::warning('CV analysis skipped because its profile no longer exists.', [
                'profile_id' => $this->profileId,
                'attempt' => $this->attempts(),
            ]);

            return;
        }

        if (! $this->isCurrentAttempt($profile)) {
            Log::info('Stale or duplicate CV analysis job skipped.', [
                'profile_id' => $this->profileId,
                'attempt' => $this->attempts(),
            ]);

            return;
        }

        $absolutePath = Storage::disk('public')->path($this->path);
        $extension = strtolower(pathinfo($this->originalName, PATHINFO_EXTENSION));
        $mimeType = match ($extension) {
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            default => 'application/octet-stream',
        };

        if (! is_file($absolutePath)) {
            $this->handleFailure(OpenAiException::missingFile());

            return;
        }

        $file = new UploadedFile($absolutePath, $this->safeOriginalName(), $mimeType, null, true);

        try {
            $result = $service->analyze($file, $profile);
        } catch (OpenAiException $exception) {
            $this->handleFailure($exception);

            return;
        } catch (Throwable $exception) {
            $this->handleFailure(OpenAiException::unknown($exception));

            return;
        }

        $this->storeSuccess($result);
    }

    public function failed(Throwable $exception): void
    {
        $failure = match (true) {
            $exception instanceof OpenAiException => $exception,
            $exception instanceof TimeoutExceededException => new OpenAiException(
                OpenAiException::TIMEOUT,
                true,
                previous: $exception,
            ),
            default => OpenAiException::unknown($exception),
        };

        $this->logFailure($failure, 'CV analysis job failed permanently.');
        $this->storeFailure($failure);
    }

    private function handleFailure(OpenAiException $exception): void
    {
        if ($exception->retryable && $this->attempts() < $this->tries) {
            $this->logFailure($exception, 'CV analysis attempt will be retried.');
            $this->release($this->retryDelay($exception));

            return;
        }

        $this->logFailure($exception, 'CV analysis failed.');
        $this->storeFailure($exception);
        $this->fail($exception);
    }

    /**
     * @param  array{
     *     score: int,
     *     extracted_skills: list<string>,
     *     missing_skills: list<string>,
     *     education_summary: string,
     *     experience_summary: string,
     *     strengths: list<string>,
     *     recommendations: list<string>
     * }  $result
     */
    private function storeSuccess(array $result): void
    {
        DB::transaction(function () use ($result): void {
            $profile = JobSeekerProfile::query()->lockForUpdate()->find($this->profileId);

            if (! $profile || ! $this->isCurrentAttempt($profile)) {
                return;
            }

            $profile->update([
                'cv_status' => 'analyzed',
                'profile_score' => $result['score'],
                'extracted_skills' => $result['extracted_skills'],
                'missing_skills' => $result['missing_skills'],
                'education_summary' => $result['education_summary'],
                'experience_summary' => $result['experience_summary'],
                'ai_recommendations' => [
                    'strengths' => $result['strengths'],
                    'recommendations' => $result['recommendations'],
                ],
                'cv_analysis_error_code' => null,
                'cv_analysis_error_message' => null,
                'cv_analysis_completed_at' => now(),
            ]);
        });
    }

    private function storeFailure(OpenAiException $exception): void
    {
        DB::transaction(function () use ($exception): void {
            $profile = JobSeekerProfile::query()->lockForUpdate()->find($this->profileId);

            if (! $profile || ! $this->isCurrentAttempt($profile)) {
                return;
            }

            $profile->update([
                'cv_status' => 'failed',
                'cv_analysis_error_code' => $exception->category,
                'cv_analysis_error_message' => $exception->userMessage(),
                'cv_analysis_completed_at' => now(),
            ]);
        });
    }

    private function isCurrentAttempt(JobSeekerProfile $profile): bool
    {
        return $profile->cv_status === 'processing'
            && $profile->cv_path === $this->path
            && $profile->cv_analysis_attempt_id === $this->attemptId;
    }

    private function retryDelay(OpenAiException $exception): int
    {
        if ($exception->retryAfterSeconds !== null) {
            return $exception->retryAfterSeconds;
        }

        $backoff = $this->backoff();

        return $backoff[min(max($this->attempts() - 1, 0), count($backoff) - 1)];
    }

    private function safeOriginalName(): string
    {
        $name = basename(str_replace('\\', '/', $this->originalName));

        return $name !== '' ? $name : basename($this->path);
    }

    private function logFailure(OpenAiException $exception, string $message): void
    {
        $absolutePath = Storage::disk('public')->path($this->path);
        $size = is_file($absolutePath) ? filesize($absolutePath) : false;

        Log::error($message, [
            'profile_id' => $this->profileId,
            'file_extension' => strtolower(pathinfo($this->originalName, PATHINFO_EXTENSION)),
            'file_size' => $size === false ? null : $size,
            'http_status' => $exception->httpStatus,
            'provider_error_type' => $exception->providerType,
            'provider_error_code' => $exception->providerCode,
            'provider_request_id' => $exception->requestId,
            'attempt' => $this->attempts(),
            'error_category' => $exception->category,
            'exception' => $exception,
        ]);
    }
}
