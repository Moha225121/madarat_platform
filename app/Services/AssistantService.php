<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Storage;
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
                    'disk' => 'public',
                    'path' => $profile->cv_path,
                    'available' => Storage::disk('public')->exists($profile->cv_path),
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
                $disk = Storage::disk('public');

                return $this->openAi->textWithStoredFile(
                    $instructions,
                    $disk->path($cvPath),
                    basename($cvPath),
                    $disk->mimeType($cvPath) ?: 'application/octet-stream',
                    $input,
                );
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
