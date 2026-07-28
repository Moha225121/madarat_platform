<?php

namespace App\Jobs;

use App\Models\JobSeekerProfile;
use App\Services\CvAnalysisService;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class AnalyzeCv
{
    use Dispatchable;

    public function __construct(
        public int $profileId,
        public string $path,
        public string $originalName,
    ) {}

    public function handle(CvAnalysisService $service): void
    {
        $profile = JobSeekerProfile::find($this->profileId);
        $absolutePath = Storage::disk('public')->path($this->path);

        if (! $profile || ! is_file($absolutePath)) {
            Log::error('CV analysis could not start because its profile or file was not found.', [
                'profile_id' => $this->profileId,
                'cv_path' => $this->path,
            ]);

            $profile?->update(['cv_status' => 'failed']);

            return;
        }

        $file = new UploadedFile($absolutePath, $this->originalName, null, null, true);

        try {
            $result = $service->analyze($file, $profile);
        } catch (Throwable $exception) {
            Log::error('CV analysis failed.', [
                'profile_id' => $profile->id,
                'file_extension' => $file->getClientOriginalExtension(),
                'file_size' => $file->getSize(),
                'exception' => $exception,
            ]);

            $profile->update(['cv_status' => 'failed']);

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
        ]);
    }
}
