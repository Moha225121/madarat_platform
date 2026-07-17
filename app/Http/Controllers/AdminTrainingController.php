<?php

namespace App\Http\Controllers;

use App\Models\TrainingCourse;
use App\Models\TrainingProviderProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AdminTrainingController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Admin/Training', ['providers' => TrainingProviderProfile::with('user')->when($request->status, fn ($q, $v) => $q->where('verification_status', $v))->when($request->provider_type, fn ($q, $v) => $q->where('provider_type', $v))->latest()->paginate(15), 'courses' => TrainingCourse::with('provider')->where('status', 'pending_review')->latest('submitted_at')->paginate(15, ['*'], 'courses_page'), 'stats' => ['providers' => TrainingProviderProfile::count(), 'verified' => TrainingProviderProfile::where('verification_status', 'verified')->count(), 'published' => TrainingCourse::where('status', 'published')->count(), 'pending' => TrainingCourse::where('status', 'pending_review')->count()]]);
    }

    public function provider(TrainingProviderProfile $provider): Response
    {
        return Inertia::render('Admin/TrainingProviderReview', ['provider' => $provider->load('user', 'courses')]);
    }

    public function verify(TrainingProviderProfile $provider, Request $request): RedirectResponse
    {
        abort_unless($provider->verification_status === 'pending', 422);
        DB::transaction(fn () => $provider->update(['verification_status' => 'verified', 'verified_at' => now(), 'verified_by' => $request->user()->id, 'rejection_reason' => null]));

        return back()->with('success', 'تم توثيق مقدم التدريب.');
    }

    public function rejectProvider(TrainingProviderProfile $provider, Request $request): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        abort_unless($provider->verification_status === 'pending', 422);
        $provider->update(['verification_status' => 'rejected', 'verified_at' => null, 'verified_by' => $request->user()->id, 'rejection_reason' => $data['reason']]);

        return back()->with('success', 'تم رفض طلب التحقق.');
    }

    public function approveCourse(TrainingCourse $course, Request $request): RedirectResponse
    {
        abort_unless($course->status === 'pending_review', 422);
        DB::transaction(fn () => $course->update(['status' => 'published', 'published_at' => now(), 'reviewed_at' => now(), 'reviewed_by' => $request->user()->id, 'rejection_reason' => null]));

        return back()->with('success', 'تم نشر الدورة.');
    }

    public function rejectCourse(TrainingCourse $course, Request $request): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        abort_unless($course->status === 'pending_review', 422);
        $course->update(['status' => 'rejected', 'reviewed_at' => now(), 'reviewed_by' => $request->user()->id, 'rejection_reason' => $data['reason']]);

        return back()->with('success', 'تم رفض الدورة.');
    }

    public function transition(TrainingCourse $course, Request $request): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', 'in:closed,archived']]);
        abort_unless(in_array($course->status, ['published', 'closed'], true), 422);
        $course->update(['status' => $data['status'], 'reviewed_at' => now(), 'reviewed_by' => $request->user()->id]);

        return back();
    }
}
