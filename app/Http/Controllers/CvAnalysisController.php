<?php

namespace App\Http\Controllers;

use App\Exceptions\OpenAiException;
use App\Http\Requests\AnalyzeCvRequest;
use App\Jobs\AnalyzeCv;
use App\Models\JobSeekerProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Throwable;

class CvAnalysisController extends Controller
{
    public function show(Request $request): Response
    {
        return Inertia::render('Seeker/CvAnalysis', [
            'profile' => $request->user()->jobSeekerProfile()->firstOrCreate(['user_id' => $request->user()->id]),
        ]);
    }

    public function store(AnalyzeCvRequest $request): RedirectResponse
    {
        $profile = $request->user()->jobSeekerProfile()->firstOrCreate(['user_id' => $request->user()->id]);
        $uploadedFile = $request->file('cv');
        $path = $uploadedFile->store('cvs', 'public');
        $attemptId = (string) Str::uuid();
        $originalName = basename(str_replace('\\', '/', $uploadedFile->getClientOriginalName()));

        if (! is_string($path) || $path === '') {
            throw new RuntimeException('The CV could not be stored.');
        }

        try {
            DB::transaction(function () use ($profile, $path, $attemptId): void {
                $lockedProfile = JobSeekerProfile::query()->lockForUpdate()->findOrFail($profile->id);

                if ($lockedProfile->cv_status === 'processing') {
                    throw ValidationException::withMessages([
                        'cv' => 'يوجد تحليل قيد التنفيذ حاليًا. انتظر اكتماله قبل رفع سيرة أخرى.',
                    ]);
                }

                $lockedProfile->update([
                    'cv_path' => $path,
                    'cv_status' => 'processing',
                    'cv_analysis_attempt_id' => $attemptId,
                    'cv_analysis_error_code' => null,
                    'cv_analysis_error_message' => null,
                    'cv_analysis_started_at' => now(),
                    'cv_analysis_completed_at' => null,
                ]);
            });
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($path);

            throw $exception;
        }

        try {
            AnalyzeCv::dispatch($profile->id, $path, $originalName, $attemptId);
        } catch (Throwable $exception) {
            $failure = OpenAiException::queueUnavailable($exception);

            DB::transaction(function () use ($profile, $path, $attemptId, $failure): void {
                $lockedProfile = JobSeekerProfile::query()->lockForUpdate()->find($profile->id);

                if (! $lockedProfile
                    || $lockedProfile->cv_path !== $path
                    || $lockedProfile->cv_analysis_attempt_id !== $attemptId
                    || $lockedProfile->cv_status !== 'processing') {
                    return;
                }

                $lockedProfile->update([
                    'cv_status' => 'failed',
                    'cv_analysis_error_code' => $failure->category,
                    'cv_analysis_error_message' => $failure->userMessage(),
                    'cv_analysis_completed_at' => now(),
                ]);
            });

            Log::error('CV analysis job could not be dispatched.', [
                'profile_id' => $profile->id,
                'file_extension' => strtolower($uploadedFile->getClientOriginalExtension()),
                'file_size' => $uploadedFile->getSize(),
                'attempt' => 0,
                'error_category' => $failure->category,
                'exception' => $failure,
            ]);

            return back()->withErrors(['cv' => $failure->userMessage()]);
        }

        return back()->with('success', 'تم رفع السيرة الذاتية وبدأ تحليلها.');
    }
}
