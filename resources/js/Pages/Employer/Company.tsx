import { router, useForm } from '@inertiajs/react';
import { Badge, Button, Card, CompanyVerificationBadge, DashboardLayout } from '@/Components/Madarat';

const statusLabels: Record<string, { label: string; tone: 'sky' | 'green' | 'gray' | 'cyan'; text: string }> = {
    unverified: {
        label: 'غير موثقة',
        tone: 'gray',
        text: 'يمكنك إرسال طلب توثيق ليقوم الأدمن بمراجعته. لن تظهر علامة التوثيق حتى تتم الموافقة.',
    },
    pending: {
        label: 'قيد المراجعة',
        tone: 'cyan',
        text: 'تم إرسال طلب التوثيق وهو الآن بانتظار مراجعة الإدارة.',
    },
    verified: {
        label: 'شركة موثقة',
        tone: 'green',
        text: 'شركتك موثقة وستظهر علامة التوثيق بجانب اسمها في الوظائف.',
    },
    rejected: {
        label: 'مرفوضة',
        tone: 'gray',
        text: 'تم رفض طلب التوثيق السابق. يمكنك تحديث بيانات الشركة وإرسال طلب جديد.',
    },
};

export default function Company({ company }: any) {
    const status = company.verification_status || 'unverified';
    const statusInfo = statusLabels[status] || statusLabels.unverified;
    const missingFields = [
        !company.company_name && 'اسم الشركة',
        !company.industry && 'القطاع',
        !company.headquarters && 'المقر',
        !company.description && 'وصف الشركة',
        !company.logo_path && 'شعار الشركة',
    ].filter(Boolean);
    const canRequestVerification = ['unverified', 'rejected'].includes(status) && missingFields.length === 0;
    const { data, setData, post, processing } = useForm<any>({
        company_name: company.company_name || '',
        industry: company.industry || '',
        headquarters: company.headquarters || '',
        description: company.description || '',
        logo: null,
    });

    return (
        <DashboardLayout title="ملف الشركة">
            <div className="grid gap-5 lg:grid-cols-[1fr_320px]">
                <Card>
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            post('/employer/company');
                        }}
                        className="grid gap-4 md:grid-cols-2"
                    >
                        <input value={data.company_name} onChange={(e) => setData('company_name', e.target.value)} placeholder="اسم الشركة" className="rounded-lg border-slate-200" />
                        <input value={data.industry} onChange={(e) => setData('industry', e.target.value)} placeholder="القطاع" className="rounded-lg border-slate-200" />
                        <input value={data.headquarters} onChange={(e) => setData('headquarters', e.target.value)} placeholder="المقر" className="rounded-lg border-slate-200" />
                        <input type="file" onChange={(e) => setData('logo', e.target.files?.[0] || null)} className="rounded-lg border border-slate-200 p-2" />
                        <textarea value={data.description} onChange={(e) => setData('description', e.target.value)} placeholder="وصف الشركة" className="rounded-lg border-slate-200 md:col-span-2" rows={5} />
                        <Button disabled={processing}>حفظ بيانات الشركة</Button>
                    </form>
                </Card>

                <Card>
                    <div className="flex items-start justify-between gap-3">
                        <div>
                            <h2 className="font-black text-madarat-navy">توثيق الشركة</h2>
                            <p className="mt-1 text-sm text-slate-500">تظهر علامة التوثيق للشركات التي وافق عليها الأدمن فقط.</p>
                        </div>
                        <CompanyVerificationBadge status={status} />
                    </div>

                    <div className="mt-4 rounded-lg bg-slate-50 p-4">
                        <Badge tone={statusInfo.tone}>{statusInfo.label}</Badge>
                        <p className="mt-3 text-sm leading-7 text-slate-600">{statusInfo.text}</p>
                    </div>

                    {missingFields.length > 0 && (
                        <div className="mt-4 rounded-lg border border-amber-100 bg-amber-50 p-4 text-sm leading-7 text-amber-800">
                            <p className="font-black">أكمل الحقول التالية قبل طلب التوثيق:</p>
                            <p className="mt-1">{missingFields.join('، ')}</p>
                        </div>
                    )}

                    {['unverified', 'rejected'].includes(status) && (
                        <Button
                            type="button"
                            disabled={!canRequestVerification}
                            onClick={() => router.post('/employer/company/request-verification')}
                            className="mt-4 w-full"
                        >
                            طلب توثيق الشركة
                        </Button>
                    )}
                </Card>
            </div>
        </DashboardLayout>
    );
}
