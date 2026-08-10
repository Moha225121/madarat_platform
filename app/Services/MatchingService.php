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

        $required = collect($job->required_skills ?? [])
            ->map(fn ($skill) => ['label' => trim((string) $skill), 'normalized' => $this->normalize((string) $skill)])
            ->filter(fn ($skill) => $skill['normalized'] !== '')
            ->unique('normalized')
            ->values();
        $candidate = collect($profile->extracted_skills ?? [])
            ->map(fn ($skill) => $this->normalize((string) $skill))
            ->filter()
            ->unique()
            ->values();

        $matched = $required->filter(fn ($skill) => $candidate->contains(
            fn ($candidateSkill) => $this->skillsMatch($skill['normalized'], $candidateSkill)
        ));

        $base = $required->isEmpty() ? 40 : (int) round(($matched->count() / $required->count()) * 100);
        $locationMatched = $job->location && $profile->city
            && $this->normalize($job->location) === $this->normalize($profile->city);
        $fieldMatched = $profile->field
            && $this->containsPhrase($this->normalize($job->title), $this->normalize($profile->field));
        $score = min(100, $base + ($locationMatched ? 8 : 0) + ($fieldMatched ? 5 : 0));

        $missing = $required->reject(fn ($skill) => $matched->contains('normalized', $skill['normalized']))
            ->pluck('label')
            ->values();
        $bonusSummary = collect([
            $locationMatched ? 'تطابق الموقع' : null,
            $fieldMatched ? 'تطابق المجال' : null,
        ])->filter()->implode(' و');

        return [
            'score' => $score,
            'summary' => $required->isEmpty()
                ? 'لم يحدد صاحب العمل مهارات مطلوبة لهذه الوظيفة.'.($bonusSummary ? " تم احتساب {$bonusSummary}." : '')
                : "تمت مطابقة {$matched->count()} من {$required->count()} مهارات مطلوبة ({$base}%)."
                    .($bonusSummary ? " أضيفت نقاط بسبب {$bonusSummary}." : '')
                    .($missing->isNotEmpty() ? ' المهارات غير المتوفرة: '.$missing->implode('، ').'.' : ''),
            'matched_skills' => $matched->pluck('label')->values()->all(),
            'missing_skills' => $missing->all(),
        ];
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/[\x{0640}\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}]/u', '', $value) ?? $value;
        $value = strtr($value, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ى' => 'ي', 'ؤ' => 'و', 'ئ' => 'ي', 'ة' => 'ه']);

        $value = trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? $value);

        return preg_replace('/\b([a-z0-9]+) js\b/u', '$1js', $value) ?? $value;
    }

    private function skillsMatch(string $required, string $candidate): bool
    {
        if ($required === $candidate) {
            return true;
        }

        if (mb_strlen(str_replace(' ', '', $required)) >= 3
            && str_replace(' ', '', $required) === str_replace(' ', '', $candidate)) {
            return true;
        }

        return $this->containsPhrase($candidate, $required) || $this->containsPhrase($required, $candidate);
    }

    private function containsPhrase(string $text, string $phrase): bool
    {
        return mb_strlen($phrase) >= 3 && str_contains(" {$text} ", " {$phrase} ");
    }
}
