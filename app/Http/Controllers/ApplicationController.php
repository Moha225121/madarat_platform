<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreApplicationRequest;
use App\Models\Application;
use App\Models\InterviewInvitation;
use App\Models\Job;
use App\Services\MatchingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ApplicationController extends Controller
{
    public function store(StoreApplicationRequest $request, Job $job, MatchingService $matching): RedirectResponse
    {
        abort_unless($job->status === 'published', 403);
        abort_if($request->user()->applications()->where('job_id', $job->id)->exists(), 422, 'تم التقديم على هذه الوظيفة مسبقا.');

        $match = $matching->match($job, $request->user()->jobSeekerProfile);
        Application::create([
            'job_id' => $job->id,
            'user_id' => $request->user()->id,
            'cover_letter' => $request->validated('cover_letter'),
            'match_score' => $match['score'],
            'match_summary' => $match['summary'],
        ]);

        return back()->with('success', 'تم إرسال طلبك بنجاح.');
    }

    public function seekerIndex(Request $request): Response
    {
        return Inertia::render('Seeker/Applications', [
            'applications' => $request->user()->applications()->with('job.companyProfile', 'interviewInvitation')->latest()->get(),
        ]);
    }

    public function shortlist(Application $application, Request $request): RedirectResponse
    {
        abort_unless($application->job->companyProfile->user_id === $request->user()->id, 403);
        $application->update(['status' => 'shortlisted']);

        return back()->with('success', 'تمت إضافة المرشح إلى القائمة المختصرة.');
    }

    public function inviteInterview(Application $application, Request $request): RedirectResponse
    {
        abort_unless($application->job->companyProfile->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'scheduled_at' => ['required', 'date', 'after:now'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        $application->update(['status' => 'interview_invited']);

        InterviewInvitation::updateOrCreate(
            ['application_id' => $application->id],
            [
                'scheduled_at' => $data['scheduled_at'],
                'message' => $data['message'] ?: 'يسرنا دعوتك لمقابلة مبدئية مع فريق التوظيف. يرجى الحضور في الموعد المحدد ومراجعة تفاصيل الوظيفة قبل المقابلة.',
                'status' => 'pending',
            ]
        );

        return back()->with('success', 'تم إرسال دعوة المقابلة للمتقدم.');
    }
}
