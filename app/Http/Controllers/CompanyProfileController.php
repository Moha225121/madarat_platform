<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCompanyProfileRequest;
use App\Models\CompanyProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CompanyProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('Employer/Company', [
            'company' => $request->user()->companyProfile()->firstOrCreate(
                ['user_id' => $request->user()->id],
                [
                    'company_name' => $request->user()->name,
                    'verification_status' => 'unverified',
                ],
            ),
        ]);
    }

    public function store(StoreCompanyProfileRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $existingCompany = $request->user()->companyProfile;

        if ($request->hasFile('logo')) {
            $data['logo_path'] = $request->file('logo')->store('company-logos', 'public');
        }

        unset($data['logo']);

        $request->user()->companyProfile()->updateOrCreate(
            ['user_id' => $request->user()->id],
            [
                ...$data,
                'verification_status' => $existingCompany?->verification_status ?? 'unverified',
            ],
        );

        return back()->with('success', 'تم حفظ بيانات الشركة.');
    }

    public function requestVerification(Request $request): RedirectResponse
    {
        $company = $request->user()->companyProfile()->firstOrCreate(
            ['user_id' => $request->user()->id],
            [
                'company_name' => $request->user()->name,
                'verification_status' => 'unverified',
            ],
        );

        $missingFields = $this->missingVerificationFields($company);

        if ($missingFields !== []) {
            return back()->with('error', 'لا يمكن إرسال طلب التوثيق قبل إكمال ملف الشركة: '.implode('، ', $missingFields).'.');
        }

        if ($company->verification_status === 'verified') {
            return back()->with('success', 'الشركة موثقة بالفعل.');
        }

        if ($company->verification_status === 'pending') {
            return back()->with('success', 'طلب التوثيق قيد المراجعة بالفعل.');
        }

        $company->update([
            'verification_status' => 'pending',
            'verification_requested_at' => now(),
            'verified_at' => null,
        ]);

        return back()->with('success', 'تم إرسال طلب توثيق الشركة إلى الإدارة.');
    }

    public function reviewVerification(CompanyProfile $company): Response
    {
        $company->load('user');

        return Inertia::render('Admin/CompanyVerification', [
            'company' => $company,
            'missingFields' => $this->missingVerificationFields($company),
        ]);
    }

    public function approveVerification(CompanyProfile $company): RedirectResponse
    {
        $missingFields = $this->missingVerificationFields($company);

        if ($missingFields !== []) {
            return back()->with('error', 'لا يمكن توثيق الشركة قبل اكتمال الملف: '.implode('، ', $missingFields).'.');
        }

        $company->update([
            'verification_status' => 'verified',
            'verified_at' => now(),
        ]);

        return back()->with('success', 'تم توثيق الشركة بنجاح.');
    }

    public function rejectVerification(CompanyProfile $company): RedirectResponse
    {
        $company->update([
            'verification_status' => 'rejected',
            'verified_at' => null,
        ]);

        return back()->with('success', 'تم رفض طلب توثيق الشركة.');
    }

    private function missingVerificationFields(CompanyProfile $company): array
    {
        return collect([
            'اسم الشركة' => $company->company_name,
            'القطاع' => $company->industry,
            'المقر' => $company->headquarters,
            'وصف الشركة' => $company->description,
            'شعار الشركة' => $company->logo_path,
        ])
            ->filter(fn ($value) => blank($value))
            ->keys()
            ->values()
            ->all();
    }
}
