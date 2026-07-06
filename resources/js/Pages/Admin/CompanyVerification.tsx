import { Link, router } from '@inertiajs/react';
import { Badge, Button, Card, CompanyVerificationBadge, DashboardLayout } from '@/Components/Madarat';

export default function CompanyVerification({ company, missingFields = [] }: any) {
    const isComplete = missingFields.length === 0;

    return (
        <DashboardLayout title="مراجعة ملف الشركة">
            <div className="mb-4">
                <Link href="/admin/companies/verification" className="text-sm font-bold text-madarat-blue hover:underline">
                    العودة إلى طلبات التوثيق
                </Link>
            </div>

            <div className="grid gap-5 lg:grid-cols-[1fr_340px]">
                <Card>
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <div className="flex flex-wrap items-center gap-2">
                                <h2 className="text-2xl font-black text-madarat-navy">{company.company_name}</h2>
                                <CompanyVerificationBadge status={company.verification_status} />
                                {company.verification_status === 'pending' && <Badge tone="cyan">قيد المراجعة</Badge>}
                            </div>
                            <p className="mt-2 text-sm text-slate-500">صاحب الحساب: {company.user?.name} - {company.user?.email}</p>
                        </div>
                        {company.logo_path && (
                            <img src={`/storage/${company.logo_path}`} alt={company.company_name} className="h-20 w-20 rounded-lg object-contain ring-1 ring-cyan-100" />
                        )}
                    </div>

                    <div className="mt-6 grid gap-4 md:grid-cols-2">
                        <div className="rounded-lg bg-slate-50 p-4">
                            <p className="text-sm font-bold text-slate-500">القطاع</p>
                            <p className="mt-1 font-black text-madarat-navy">{company.industry || 'غير محدد'}</p>
                        </div>
                        <div className="rounded-lg bg-slate-50 p-4">
                            <p className="text-sm font-bold text-slate-500">المقر</p>
                            <p className="mt-1 font-black text-madarat-navy">{company.headquarters || 'غير محدد'}</p>
                        </div>
                    </div>

                    <div className="mt-4 rounded-lg bg-slate-50 p-4">
                        <p className="text-sm font-bold text-slate-500">وصف الشركة</p>
                        <p className="mt-2 whitespace-pre-wrap leading-8 text-slate-700">{company.description || 'لا يوجد وصف.'}</p>
                    </div>
                </Card>

                <Card>
                    <h2 className="font-black text-madarat-navy">قرار التوثيق</h2>
                    {isComplete ? (
                        <p className="mt-2 text-sm leading-7 text-slate-600">ملف الشركة مكتمل. يمكنك الموافقة على التوثيق بعد مراجعة البيانات المعروضة.</p>
                    ) : (
                        <div className="mt-3 rounded-lg border border-amber-100 bg-amber-50 p-4 text-sm leading-7 text-amber-800">
                            <p className="font-black">لا يمكن توثيق الشركة قبل اكتمال الملف:</p>
                            <p className="mt-1">{missingFields.join('، ')}</p>
                        </div>
                    )}

                    <div className="mt-5 grid gap-2">
                        <Button
                            type="button"
                            disabled={!isComplete}
                            onClick={() => router.post(`/admin/companies/${company.id}/verify`)}
                            className="w-full"
                        >
                            الموافقة على التوثيق
                        </Button>
                        <Button
                            type="button"
                            onClick={() => router.post(`/admin/companies/${company.id}/reject-verification`)}
                            className="w-full bg-slate-500"
                        >
                            رفض الطلب
                        </Button>
                    </div>
                </Card>
            </div>
        </DashboardLayout>
    );
}
