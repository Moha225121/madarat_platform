<?php

namespace App\Services;

use App\Models\Job;
use App\Models\JobSeekerProfile;
use App\Models\TrainingCourse;
use RuntimeException;

class CourseAudienceAnalysisService
{
    public function __construct(private OpenAiClient $openAi) {}

    public function analyze(TrainingCourse $course): array
    {
        if (! $this->openAi->isConfigured()) {
            throw new RuntimeException('OpenAI is not configured.');
        }
        $skills = collect($course->skills_taught)->map(fn ($s) => mb_strtolower($s));
        $jobs = Job::where('status', 'published')->get(['title', 'required_skills'])->filter(fn ($job) => collect($job->required_skills)->map(fn ($s) => mb_strtolower($s))->intersect($skills)->isNotEmpty());
        $relevantCount = JobSeekerProfile::whereNotNull('missing_skills')->get(['missing_skills'])->filter(fn ($profile) => collect($profile->missing_skills)->map(fn ($s) => mb_strtolower($s))->intersect($skills)->isNotEmpty())->count();
        $market = ['relevant_published_jobs' => $jobs->take(20)->map(fn ($j) => ['title' => $j->title, 'required_skills' => $j->required_skills])->values(), 'potential_audience_count' => $relevantCount >= 3 ? $relevantCount : null, 'privacy_threshold_applied' => $relevantCount < 3];
        $keys = ['recommended_audience', 'recommended_experience_levels', 'recommended_job_titles', 'recommended_existing_skills', 'skills_participants_will_gain', 'prerequisites', 'market_relevance', 'course_positioning', 'suggested_description_improvements', 'suggested_skills', 'confidence', 'warnings'];
        $text = $this->openAi->text('أنت محلل دورات لمنصة مدارات. أعد JSON عربي فقط بالمفاتيح: '.implode(', ', $keys).'. لا تخترع بيانات ولا تعرض أي معلومات شخصية.', json_encode(['course' => $course->only(['title', 'description', 'skills_taught', 'learning_outcomes', 'difficulty_level', 'prerequisites', 'delivery_method', 'duration_value', 'duration_unit']), 'aggregate_market_data' => $market], JSON_UNESCAPED_UNICODE));
        $result = json_decode($text, true);
        if (! is_array($result) || array_diff($keys, array_keys($result))) {
            throw new RuntimeException('Malformed AI analysis.');
        }
        $result['aggregate_market_data'] = $market;

        return $result;
    }
}
