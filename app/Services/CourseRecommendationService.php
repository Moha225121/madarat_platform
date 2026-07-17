<?php

namespace App\Services;

use App\Models\CourseRecommendation;
use App\Models\TrainingCourse;
use App\Models\User;
use Illuminate\Support\Collection;

class CourseRecommendationService
{
    public function generate(User $user): Collection
    {
        $user->loadMissing(['jobSeekerProfile', 'applications.job', 'courseFeedback']);
        $known = $this->normalized($user->jobSeekerProfile?->extracted_skills ?? []);
        $profileGaps = $this->normalized($user->jobSeekerProfile?->missing_skills ?? []);
        $jobRequirements = $user->applications->flatMap(fn ($application) => $application->job?->required_skills ?? []);
        $gaps = $profileGaps->merge($this->normalized($jobRequirements))->unique()->diff($known)->values();
        $targetJobIds = $user->applications->pluck('job_id')->unique()->values()->all();
        $feedback = $user->courseFeedback->keyBy('course_id');

        $courses = TrainingCourse::with('provider')->where('status', 'published')
            ->where(fn ($q) => $q->whereNull('registration_deadline')->orWhereDate('registration_deadline', '>=', today()))
            ->get();

        foreach ($courses as $course) {
            $response = $feedback->get($course->id);
            if ($response?->completed || $response?->already_knows || $response?->dismissed_at) {
                CourseRecommendation::where(['job_seeker_id' => $user->id, 'course_id' => $course->id])->delete();

                continue;
            }
            $skills = $this->normalized($course->skills_taught ?? []);
            $covered = $skills->intersect($gaps)->values();
            if ($covered->isEmpty()) {
                CourseRecommendation::where(['job_seeker_id' => $user->id, 'course_id' => $course->id])->delete();

                continue;
            }
            $prerequisites = $this->normalized($course->prerequisites ?? []);
            $prerequisiteMatch = $prerequisites->isEmpty() ? 1 : $prerequisites->intersect($known)->count() / $prerequisites->count();
            $coverage = $covered->count() / max(1, $gaps->count());
            $jobRelevance = $covered->intersect($this->normalized($jobRequirements))->isNotEmpty() ? 1 : .5;
            $difficulty = $course->difficulty_level === 'advanced' && $prerequisiteMatch < .75 ? .25 : 1;
            $knownPenalty = $skills->isEmpty() ? 0 : $skills->intersect($known)->count() / $skills->count();
            $score = (int) round(max(0, min(100, $coverage * 45 + $jobRelevance * 25 + $prerequisiteMatch * 15 + $difficulty * 10 + 5 - $knownPenalty * 25)));
            if ($score < 20) {
                continue;
            }
            $evidence = ['missing_skills' => $gaps->all(), 'matched_course_skills' => $covered->all(), 'target_job_requirements' => $this->normalized($jobRequirements)->all(), 'existing_user_skills' => $known->all(), 'prerequisite_match' => round($prerequisiteMatch, 2), 'difficulty_match' => $difficulty, 'availability_match' => 1, 'already_known_skill_penalty' => round($knownPenalty, 2)];
            CourseRecommendation::updateOrCreate(['job_seeker_id' => $user->id, 'course_id' => $course->id], ['score' => $score, 'missing_skills_covered' => $covered->all(), 'target_job_ids' => $targetJobIds, 'evidence' => $evidence, 'reason' => 'نرشح هذه الدورة لأنها تغطي المهارات الناقصة: '.$covered->implode('، ').($targetJobIds ? '، وهي مرتبطة بمتطلبات وظائف تقدمت إليها.' : '.'), 'confidence' => $score / 100, 'content_signature' => hash('sha256', $course->contentHash().json_encode([$known, $gaps, $targetJobIds])), 'recommended_at' => now()]);
        }

        return CourseRecommendation::with('course.provider')->where('job_seeker_id', $user->id)->whereNull('dismissed_at')->orderByDesc('score')->get();
    }

    private function normalized(iterable $skills): Collection
    {
        return collect($skills)->map(fn ($v) => mb_strtolower(trim((string) $v)))->filter()->unique()->values();
    }
}
