import { Link, router } from '@inertiajs/react';
import { Badge, Card, CompanyVerificationBadge, DashboardLayout, StatCard, icons } from '@/Components/Madarat';
import { useState } from 'react';

const statusLabel: Record<string, string> = {
    verified: 'موثقة',
    pending_review: 'قيد المراجعة',
    draft: 'مسودة',
    rejected: 'مرفوضة',
    closed: 'مغلقة',
    archived: 'مؤرشفة',
    unverified: 'غير موثقة',
    pending: 'قيد المراجعة',
    published: 'منشورة',
};

export default function CompanyDetails({ company, jobs, stats }: any) {
    const [deleting, setDeleting] = useState(false);

    const deleteCompany = () => {
        if (deleting) {
            return;
        }

        if (!confirm(`هل أنت متأكد من حذف حساب صاحب العمل "${company.company_name}"؟ سيُحذف الحساب وجميع الوظائف والطلبات والبيانات المرتبطة به نهائيًا، ولا يمكن التراجع عن هذا الإجراء.`)) {
            return;
        }

        setDeleting(true);
        router.delete(route('admin.companies.destroy', { company: company.id }), {
            preserveScroll: true,
            onFinish: () => setDeleting(false),
        });
    };

    return (
        <DashboardLayout title="تفاصيل الشركة">
            <div className="mb-4 flex flex-wrap items-center gap-3">
                <Link href="/admin/companies" className="text-sm font-black text-madarat-blue hover:underline">العودة إلى قائمة الشركات</Link>
                {company.verification_status === 'pending' && <Link href={`/admin/companies/${company.id}/verification`} className="rounded-lg bg-madarat-blue px-3 py-1.5 text-xs font-black text-white hover:bg-madarat-navy">فتح صفحة التوثيق</Link>}
                <button
                    type="button"
                    onClick={deleteCompany}
                    disabled={deleting}
                    className="rounded-lg bg-red-600 px-3 py-1.5 text-xs font-black text-white hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    {deleting ? 'جاري الحذف...' : 'حذف حساب صاحب العمل'}
                </button>
            </div>

            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <StatCard label="إجمالي الوظائف" value={stats.jobs} icon={icons.BriefcaseBusiness} />
                <StatCard label="وظائف منشورة" value={stats.published} icon={icons.CheckCircle2} />
                <StatCard label="قيد المراجعة" value={stats.pending} icon={icons.Clock} />
                <StatCard label="إجمالي التقديمات" value={stats.applications} icon={icons.FileText} />
            </div>

            <div className="mt-6 grid gap-5 lg:grid-cols-[1fr_340px]">
                <Card>
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <div className="flex flex-wrap items-center gap-2">
                                <h2 className="text-2xl font-black text-madarat-navy">{company.company_name}</h2>
                                <CompanyVerificationBadge status={company.verification_status} />
                                {company.verification_status !== 'verified' && <Badge tone="gray">{statusLabel[company.verification_status] || 'غير محددة'}</Badge>}
                            </div>
                            <p className="mt-1 text-sm text-slate-500">صاحب الحساب: {company.user?.name || 'غير محدد'} · {company.user?.email || 'غير محدد'} · الهاتف: {company.user?.phone || 'غير محدد'}</p>
                        </div>
                        {company.logo_path && <img src={`/storage/${company.logo_path}`} alt={company.company_name} className="h-20 w-20 rounded-lg object-contain ring-1 ring-cyan-100" />}
                    </div>

                    <dl className="mt-5 grid gap-4 md:grid-cols-2">
                        <div><dt className="text-sm font-bold text-slate-500">القطاع</dt><dd className="mt-1 font-black">{company.industry || 'غير محدد'}</dd></div>
                        <div><dt className="text-sm font-bold text-slate-500">المقر</dt><dd className="mt-1 font-black">{company.headquarters || 'غير محدد'}</dd></div>
                    </dl>

                    <div className="mt-4 rounded-lg bg-slate-50 p-4">
                        <p className="text-sm font-bold text-slate-500">وصف الشركة</p>
                        <p className="mt-2 whitespace-pre-wrap leading-8 text-slate-700">{company.description || 'لا يوجد وصف.'}</p>
                    </div>
                </Card>

                <Card>
                    <h2 className="font-black text-madarat-navy">توثيق الشركة</h2>
                    <p className="mt-2 text-sm text-slate-600">الحالة الحالية: {statusLabel[company.verification_status] || 'غير محددة'}</p>
                    <div className="mt-3 space-y-2 text-sm text-slate-600">
                        <p>تاريخ طلب التوثيق: {company.verification_requested_at || 'غير متاح'}</p>
                        <p>تاريخ التوثيق: {company.verified_at || 'غير متاح'}</p>
                    </div>
                </Card>
            </div>

            <Card className="mt-6">
                <h2 className="font-black text-madarat-navy">وظائف الشركة</h2>
                <div className="mt-4 space-y-3">
                    {jobs.data.length ? jobs.data.map((job: any) => (
                        <div key={job.id} className="rounded-lg bg-slate-50 p-4">
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p className="font-black text-madarat-navy">{job.title}</p>
                                    <p className="mt-1 text-sm text-slate-500">{job.location || 'موقع غير محدد'} · {job.job_type || 'نوع غير محدد'}</p>
                                    <p className="mt-1 text-xs text-slate-500">التقديمات: {job.applications_count}</p>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Badge tone={job.status === 'published' ? 'green' : 'gray'}>{statusLabel[job.status] || job.status}</Badge>
                                    {job.status === 'pending_review' && <Link href={`/admin/jobs/${job.id}/review`} className="rounded-lg bg-madarat-blue px-3 py-1.5 text-xs font-black text-white hover:bg-madarat-navy">مراجعة</Link>}
                                </div>
                            </div>
                        </div>
                    )) : <p className="text-sm text-slate-500">لا توجد وظائف لهذه الشركة.</p>}
                </div>
                <div className="mt-4 flex items-center justify-between">
                    <p className="text-xs text-slate-500">صفحة {jobs.current_page} من {jobs.last_page}</p>
                    <div className="flex gap-2">
                        {jobs.prev_page_url && <Link href={jobs.prev_page_url} className="rounded-lg bg-white px-3 py-1.5 text-sm font-bold text-slate-700 ring-1 ring-slate-200">السابق</Link>}
                        {jobs.next_page_url && <Link href={jobs.next_page_url} className="rounded-lg bg-white px-3 py-1.5 text-sm font-bold text-slate-700 ring-1 ring-slate-200">التالي</Link>}
                    </div>
                </div>
            </Card>
        </DashboardLayout>
    );
}
