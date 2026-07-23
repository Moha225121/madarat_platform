<?php

namespace App\Http\Controllers;

use App\Models\CourseUserFeedback;
use App\Models\CourseEnrollment;
use App\Models\TrainingCourse;
use App\Services\CourseRecommendationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CourseCatalogueController extends Controller
{
    public function index(Request $request): Response
    {
        $courses = TrainingCourse::with('provider')->where('status', 'published')->when($request->search, fn ($q, $v) => $q->where(fn ($x) => $x->where('title', 'like', "%$v%")->orWhereJsonContains('skills_taught', $v)->orWhereHas('provider', fn ($p) => $p->where('display_name', 'like', "%$v%"))))->when($request->delivery_method, fn ($q, $v) => $q->where('delivery_method', $v))->when($request->difficulty_level, fn ($q, $v) => $q->where('difficulty_level', $v))->when($request->city, fn ($q, $v) => $q->where('city', $v))->when($request->certificate_available !== null, fn ($q) => $q->where('certificate_available', $request->boolean('certificate_available')))->when($request->price_max, fn ($q, $v) => $q->where('price', '<=', $v))->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', today()))->latest('published_at')->paginate(12)->withQueryString();

        return Inertia::render('Courses/Index', ['courses' => $courses, 'filters' => $request->only(['search', 'delivery_method', 'difficulty_level', 'city', 'certificate_available', 'price_max'])]);
    }

    public function show(TrainingCourse $course, Request $request): Response
    {
        abort_unless($course->status === 'published', 404);
        $course->increment('views_count');

        $isSeeker = $request->user()?->role === 'job_seeker';

        return Inertia::render('Courses/Show', [
            'course' => $course->load('provider'),
            'feedback' => $isSeeker ? CourseUserFeedback::firstWhere(['user_id' => $request->user()->id, 'course_id' => $course->id]) : null,
            'canRegister' => $isSeeker,
            'enrollment' => $isSeeker ? CourseEnrollment::firstWhere(['user_id' => $request->user()->id, 'course_id' => $course->id]) : null,
            'remainingSeats' => $course->capacity === null ? null : max(0, $course->capacity - $course->enrollments()->where('status', 'registered')->count()),
        ]);
    }

    public function recommendations(Request $request, CourseRecommendationService $service): Response
    {
        return Inertia::render('Courses/Recommendations', ['recommendations' => $service->generate($request->user())]);
    }

    public function saved(Request $request): Response
    {
        return Inertia::render('Courses/Saved', ['feedback' => $request->user()->courseFeedback()->with('course.provider')->where(fn ($q) => $q->where('saved', true)->orWhere('completed', true))->latest()->get()]);
    }

    public function registrations(Request $request): Response
    {
        return Inertia::render('Courses/Registrations', [
            'enrollments' => $request->user()->courseEnrollments()->with('course.provider')->latest('registered_at')->get(),
        ]);
    }

    public function register(TrainingCourse $course, Request $request): RedirectResponse
    {
        abort_unless($course->status === 'published', 404);

        if ($course->registration_deadline?->isPast()) {
            return back()->with('error', 'انتهى موعد التسجيل في هذه الدورة.');
        }

        $created = DB::transaction(function () use ($course, $request) {
            $lockedCourse = TrainingCourse::query()->lockForUpdate()->findOrFail($course->id);
            $existing = CourseEnrollment::where('user_id', $request->user()->id)->where('course_id', $course->id)->first();

            if ($existing) {
                return false;
            }

            if ($lockedCourse->capacity !== null && $lockedCourse->enrollments()->where('status', 'registered')->count() >= $lockedCourse->capacity) {
                return null;
            }

            CourseEnrollment::create([
                'user_id' => $request->user()->id,
                'course_id' => $course->id,
                'status' => 'registered',
                'registered_at' => now(),
            ]);

            return true;
        });

        if ($created === null) {
            return back()->with('error', 'عذرًا، اكتمل العدد المتاح لهذه الدورة.');
        }

        return back()->with('success', $created ? 'تم تسجيلك في الدورة بنجاح.' : 'أنت مسجل في هذه الدورة بالفعل.');
    }

    public function feedback(TrainingCourse $course, Request $request): RedirectResponse
    {
        abort_unless($course->status === 'published', 404);
        $data = $request->validate(['action' => ['required', 'in:save,unsave,interest,complete,already_know,dismiss']]);
        $feedback = CourseUserFeedback::firstOrCreate(['user_id' => $request->user()->id, 'course_id' => $course->id]);
        $updates = match ($data['action']) {
            'save' => ['saved' => true], 'unsave' => ['saved' => false], 'interest' => ['interested' => true], 'complete' => ['completed' => true], 'already_know' => ['already_knows' => true], 'dismiss' => ['dismissed_at' => now()]
        };
        $feedback->update($updates);
        if ($data['action'] === 'dismiss') {
            $request->user()->courseRecommendations()->where('course_id', $course->id)->update(['dismissed_at' => now()]);
        }

return back()->with('success', 'تم تحديث تفضيل الدورة.');
    }
}
