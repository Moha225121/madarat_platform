<?php

namespace App\Services;

use App\Models\User;
use Throwable;

class AssistantService
{
    public function __construct(
        private OpenAiClient $openAi,
        private MockAssistantService $fallback,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function contextFor(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $user->loadMissing([
            'jobSeekerProfile',
            'applications.interviewInvitation',
            'applications.job.companyProfile',
            'courseRecommendations.course.provider',
            'courseFeedback.course.provider',
        ]);
        $profile = $user->jobSeekerProfile;

        $context = [
            'user' => $this->filledValues([
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role,
            ]),
        ];

        if ($profile) {
            $profileContext = $this->filledValues([
                'headline' => $profile->headline,
                'city' => $profile->city,
                'field' => $profile->field,
                'bio' => $profile->bio,
                'profile_score' => $profile->profile_score,
                'cv_status' => $profile->cv_status,
                'extracted_skills' => $profile->extracted_skills,
                'missing_skills' => $profile->missing_skills,
                'education_summary' => $profile->education_summary,
                'experience_summary' => $profile->experience_summary,
                'ai_recommendations' => $profile->ai_recommendations,
            ]);

            if ($profile->cv_path) {
                $profileContext['cv'] = [
                    'disk' => 'documents',
                    'path' => $profile->cv_path,
                    'available' => app(UploadStorage::class)->cvDisk($profile->cv_path) !== null,
                ];
            }

            if ($profileContext !== []) {
                $context['job_seeker_profile'] = $profileContext;
            }
        }

        $applications = $user->applications
            ->sortByDesc('created_at')
            ->take(10)
            ->map(fn ($application): array => $this->filledValues([
                'job_title' => $application->job?->title,
                'company' => $application->job?->companyProfile?->company_name,
                'location' => $application->job?->location,
                'status' => $application->status,
                'match_score' => $application->match_score,
                'match_summary' => $application->match_summary,
                'cover_letter' => $application->cover_letter,
                'interview_invitation' => $application->interviewInvitation ? $this->filledValues([
                    'scheduled_at' => $application->interviewInvitation->scheduled_at?->toDateTimeString(),
                    'status' => $application->interviewInvitation->status,
                    'message' => $application->interviewInvitation->message,
                ]) : null,
            ]))
            ->values()
            ->all();

        if ($applications !== []) {
            $context['applications'] = $applications;
        }

        if ($user->role === 'job_seeker') {
            $context['course_recommendations'] = $user->courseRecommendations->whereNull('dismissed_at')->take(10)->map(fn ($recommendation) => $this->filledValues([
                'course' => $recommendation->course?->title,
                'provider' => $recommendation->course?->provider?->display_name,
                'score' => $recommendation->score,
                'missing_skills_covered' => $recommendation->missing_skills_covered,
                'reason' => $recommendation->reason,
                'evidence' => $recommendation->evidence,
            ]))->values()->all();
            $context['course_history'] = $user->courseFeedback->map(fn ($feedback) => $this->filledValues([
                'course' => $feedback->course?->title,
                'saved' => $feedback->saved,
                'completed' => $feedback->completed,
                'already_knows' => $feedback->already_knows,
                'dismissed' => (bool) $feedback->dismissed_at,
            ]))->values()->all();
        }

        return $this->filledValues($context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function respond(string $prompt, array $context = []): string
    {
        $instructions = 'أنت مساعد منصة مدارات للتوظيف. أجب بالعربية بلغة واضحة ومهنية ومختصرة. استخدم بيانات الباحث وسيرته الذاتية المرفقة عند توفرها لتخصيص الإجابة. لا تكشف البيانات الحساسة إلا إذا طلبها صاحب الحساب، ولا تدّعي أنك نفذت إجراءات داخل النظام. قدم خطوات عملية ومناسبة لسياق الباحث.';
        $input = $this->buildInput($prompt, $context);

        if (! $this->openAi->isConfigured()) {
            return $this->fallback->respond($prompt, $this->contextText($context));
        }

        try {
            $cvPath = data_get($context, 'job_seeker_profile.cv.path');
            $cvAvailable = data_get($context, 'job_seeker_profile.cv.available', false);

            if ($cvPath && $cvAvailable) {
                return app(UploadStorage::class)->withCvFile($cvPath, fn (string $localPath): string => $this->openAi->textWithStoredFile(
                    $instructions, $localPath, basename($cvPath), UploadStorage::cvMimeType($cvPath), $input,
                ));
            }

            return $this->openAi->text($instructions, $input);
        } catch (Throwable) {
            return $this->fallback->respond($prompt, $this->contextText($context));
        }
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function filledValues(array $values): array
    {
        return array_filter($values, function (mixed $value): bool {
            return is_array($value) ? $value !== [] : filled($value);
        });
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function buildInput(string $prompt, array $context): string
    {
        $contextText = $this->contextText($context);

        if ($contextText === '') {
            return $prompt;
        }

        return "سياق الباحث المتاح:\n{$contextText}\n\nرسالة المستخدم:\n{$prompt}";
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function contextText(array $context): string
    {
        if ($context === []) {
            return '';
        }

        return json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '';
    }
}
