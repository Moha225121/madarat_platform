<?php

namespace App\Services;

use App\Models\Job;
use App\Models\JobSeekerProfile;

class MatchingService
{
    public function match(Job $job, ?JobSeekerProfile $profile): array
    {
        if (! $profile) {
            return ['score' => 0, 'summary' => 'أكمل ملفك المهني وتحليل السيرة الذاتية لاحتساب نسبة المطابقة.'];
        }

        $required = collect($job->required_skills ?? [])->map(fn ($skill) => mb_strtolower(trim($skill)))->filter();
        $candidate = collect($profile->extracted_skills ?? [])->map(fn ($skill) => mb_strtolower(trim($skill)))->filter();

        $base = $required->isEmpty() ? 40 : (int) round(($required->intersect($candidate)->count() / max(1, $required->count())) * 100);
        $bonus = 0;
        $bonus += $job->location && $profile->city && $job->location === $profile->city ? 8 : 0;
        $bonus += $profile->field && str_contains(mb_strtolower($job->title), mb_strtolower($profile->field)) ? 5 : 0;

        $score = min(100, $base + $bonus);

        return [
            'score' => $score,
            'summary' => "تطابق المهارات الأساسية بلغ {$base}% مع نقاط إضافية للموقع أو المجال عند توفرها.",
        ];
    }
}
