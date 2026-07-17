<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTrainingCourseRequest;
use App\Models\TrainingCourse;
use App\Services\CourseAudienceAnalysisService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class TrainingCourseController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('TrainingProvider/Courses', ['courses' => $request->user()->trainingProviderProfile->courses()->latest()->get()]);
    }

    public function create(): Response
    {
        return Inertia::render('TrainingProvider/CourseForm', ['course' => null]);
    }

    public function store(StoreTrainingCourseRequest $request): RedirectResponse
    {
        $data = $this->data($request);
        $data['training_provider_id'] = $request->user()->trainingProviderProfile->id;
        $data['slug'] = $this->slug($data['title']);
        $data['status'] = 'draft';
        $course = TrainingCourse::create($data);

        return redirect()->route('training.courses.edit', $course)->with('success', 'تم إنشاء مسودة الدورة.');
    }

    public function show(TrainingCourse $course, Request $request): Response
    {
        $this->authorize('view', $course);

        return Inertia::render('TrainingProvider/CoursePreview', ['course' => $course->load('provider')]);
    }

    public function edit(TrainingCourse $course): Response
    {
        $this->authorize('update', $course);

        return Inertia::render('TrainingProvider/CourseForm', ['course' => $course]);
    }

    public function update(StoreTrainingCourseRequest $request, TrainingCourse $course): RedirectResponse
    {
        $this->authorize('update', $course);
        $course->update($this->data($request));

        return back()->with('success', 'تم تحديث الدورة. التحليل السابق سيظهر كقديم إن تغير المحتوى.');
    }

    public function submit(TrainingCourse $course): RedirectResponse
    {
        $this->authorize('submit', $course);
        DB::transaction(fn () => $course->update(['status' => 'pending_review', 'submitted_at' => now(), 'rejection_reason' => null]));

        return back()->with('success', 'تم إرسال الدورة للمراجعة.');
    }

    public function close(TrainingCourse $course): RedirectResponse
    {
        $this->authorize('close', $course);
        $course->update(['status' => 'closed']);

        return back()->with('success', 'تم إغلاق الدورة.');
    }

    public function archive(TrainingCourse $course): RedirectResponse
    {
        $this->authorize('archive', $course);
        $course->update(['status' => 'archived']);

        return back()->with('success', 'تمت أرشفة الدورة.');
    }

    public function analyze(TrainingCourse $course, CourseAudienceAnalysisService $service): RedirectResponse
    {
        $this->authorize('update', $course);
        if ($course->analysis_content_hash === $course->contentHash() && $course->audience_analysis) {
            return back()->with('success', 'التحليل الحالي ما زال محدثاً.');
        }
        try {
            $analysis = $service->analyze($course);
        } catch (Throwable) {
            return back()->with('error', 'خدمة التحليل غير متاحة حالياً. تحقق من إعداد OpenAI وحاول لاحقاً.');
        }
        $course->update(['audience_analysis' => $analysis, 'analysis_model' => config('services.openai.model'), 'analysis_content_hash' => $course->contentHash(), 'analyzed_at' => now()]);

        return back()->with('success', 'تم إنشاء تحليل الفئة المستهدفة دون تغيير محتوى الدورة.');
    }

    private function data(StoreTrainingCourseRequest $request): array
    {
        $data = $request->validated();
        foreach (['learning_outcomes', 'skills_taught', 'prerequisites'] as $field) {
            $data[$field] = is_array($data[$field] ?? null) ? array_values(array_filter($data[$field])) : preg_split('/[\r\n,]+/', (string) ($data[$field] ?? ''), -1, PREG_SPLIT_NO_EMPTY);
        } if ($request->hasFile('cover_image')) {
            $data['cover_image_path'] = $request->file('cover_image')->store('training-courses', 'public');
        } unset($data['cover_image']);

        return $data;
    }

    private function slug(string $title): string
    {
        $base = Str::slug($title) ?: Str::random(8);
        $slug = $base;
        for ($i = 2; TrainingCourse::where('slug', $slug)->exists(); $i++) {
            $slug = "$base-$i";
        }

return $slug;
    }
}
