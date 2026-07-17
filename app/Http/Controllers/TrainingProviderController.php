<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTrainingProviderProfileRequest;
use App\Models\TrainingProviderProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TrainingProviderController extends Controller
{
    public function dashboard(Request $request): Response
    {
        $provider = $this->profile($request)->load(['courses.feedback', 'courses.recommendations']);
        $courses = $provider->courses;

        return Inertia::render('TrainingProvider/Dashboard', ['provider' => $provider, 'recentCourses' => $courses->sortByDesc('updated_at')->take(5)->values(), 'stats' => ['total' => $courses->count(), 'draft' => $courses->where('status', 'draft')->count(), 'pending' => $courses->where('status', 'pending_review')->count(), 'published' => $courses->where('status', 'published')->count(), 'rejected' => $courses->where('status', 'rejected')->count(), 'views' => $courses->sum('views_count'), 'recommendations' => $courses->sum(fn ($c) => $c->recommendations->count()), 'interested' => $courses->sum(fn ($c) => $c->feedback->where('interested', true)->count()), 'average_relevance' => round((float) $courses->flatMap->recommendations->avg('score'), 1)]]);
    }

    public function edit(Request $request): Response
    {
        return Inertia::render('TrainingProvider/Profile', ['provider' => $this->profile($request)]);
    }

    public function update(StoreTrainingProviderProfileRequest $request): RedirectResponse
    {
        $provider = $this->profile($request);
        $data = $request->validated();
        foreach (['specializations', 'certifications'] as $field) {
            $data[$field] = $this->list($data[$field] ?? []);
        }
        foreach (['logo' => 'logo_path', 'profile_image' => 'profile_image_path'] as $input => $column) {
            if ($request->hasFile($input)) {
                $data[$column] = $request->file($input)->store('training-providers', 'public');
            } unset($data[$input]);
        }
        if ($provider->verification_status === 'rejected') {
            $data['verification_status'] = 'incomplete';
            $data['rejection_reason'] = null;
        }
        $provider->update($data);

        return back()->with('success', 'تم حفظ ملف مقدم التدريب.');
    }

    public function requestVerification(Request $request): RedirectResponse
    {
        $provider = $this->profile($request);
        $required = ['display_name', 'description', 'email', 'phone', 'city', 'specializations'];
        if ($provider->provider_type === 'company') {
            $required[] = 'legal_name';
            $required[] = 'commercial_registration_number';
        }
        $missing = collect($required)->filter(fn ($field) => blank($provider->{$field}))->values();
        if ($missing->isNotEmpty()) {
            return back()->with('error', 'يرجى إكمال الحقول المطلوبة قبل طلب التحقق: '.$missing->implode('، '));
        }
        if ($provider->verification_status === 'verified') {
            return back()->with('success', 'الملف موثق بالفعل.');
        }
        $provider->update(['verification_status' => 'pending', 'verification_requested_at' => now(), 'rejection_reason' => null]);

        return back()->with('success', 'تم إرسال طلب التحقق.');
    }

    private function profile(Request $request): TrainingProviderProfile
    {
        return $request->user()->trainingProviderProfile()->firstOrCreate(['user_id' => $request->user()->id], ['provider_type' => 'company', 'display_name' => $request->user()->name, 'email' => $request->user()->email, 'phone' => $request->user()->phone, 'verification_status' => 'incomplete']);
    }

    private function list(mixed $value): array
    {
        return is_array($value) ? array_values(array_filter($value)) : preg_split('/[\r\n,]+/', (string) $value, -1, PREG_SPLIT_NO_EMPTY);
    }
}
