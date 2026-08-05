import { FormEvent, useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { Badge, Card, DashboardLayout, Pagination, ProgressBar, StatCard, icons } from '@/Components/Madarat';
import { arabicLabel } from '@/lib/arabicLabels';

const toneByStatus: Record<string, 'gray' | 'cyan' | 'green'> = {
    submitted: 'cyan',
    shortlisted: 'green',
    interview_invited: 'green',
    rejected: 'gray',
    accepted: 'green',
};

export default function Applications({ applications, stats, filters, statusOptions }: any) {
    const [form, setForm] = useState({
        q: filters.q || '',
        status: filters.status || '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        router.get('/admin/applications', form, { preserveState: true, replace: true });
    };

    const reset = () => {
        const empty = { q: '', status: '' };
        setForm(empty);
        router.get('/admin/applications', empty, { preserveState: true, replace: true });
    };

    return (
        <DashboardLayout title="طلبات التوظيف">
            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <StatCard label="إجمالي الطلبات" value={stats.total} icon={icons.FileText} />
                <StatCard label="طلبات جديدة" value={stats.submitted} icon={icons.Clock} />
                <StatCard label="قائمة مختصرة" value={stats.shortlisted} icon={icons.Target} />
                <StatCard label="دعوات مقابلة" value={stats.interviews} icon={icons.CheckCircle2} />
            </div>

            <Card className="mt-6">
                <h2 className="font-black text-madarat-navy">بحث وتصفية</h2>
                <form onSubmit={submit} className="mt-4 grid gap-3 md:grid-cols-[1fr_220px_220px]">
                    <input
                        value={form.q}
                        onChange={(event) => setForm((prev) => ({ ...prev, q: event.target.value }))}
                        placeholder="اسم الباحث، البريد، الوظيفة، الشركة"
                        className="rounded-lg border-slate-200 text-sm"
                    />
                    <select value={form.status} onChange={(event) => setForm((prev) => ({ ...prev, status: event.target.value }))} className="rounded-lg border-slate-200 text-sm">
                        <option value="">كل الحالات</option>
                        {statusOptions.map((status: string) => <option key={status} value={status}>{arabicLabel('applicationStatus', status)}</option>)}
                    </select>
                    <div className="flex gap-2">
                        <button type="submit" className="flex-1 rounded-lg bg-madarat-blue px-4 py-2 text-sm font-black text-white hover:bg-madarat-navy">بحث</button>
                        <button type="button" onClick={reset} className="rounded-lg bg-slate-200 px-4 py-2 text-sm font-black text-slate-700 hover:bg-slate-300">إعادة ضبط</button>
                    </div>
                </form>
            </Card>

            <Card className="mt-6">
                <h2 className="font-black text-madarat-navy">قائمة الطلبات</h2>
                <div className="mt-4 space-y-3">
                    {applications.data.length ? applications.data.map((application: any) => (
                        <div key={application.id} className="rounded-lg bg-slate-50 p-4">
                            <div className="grid gap-4 lg:grid-cols-[1fr_220px_auto] lg:items-center">
                                <div>
                                    <div className="flex flex-wrap items-center gap-2">
                                        <p className="font-black text-madarat-navy">{application.user?.name || 'باحث محذوف'}</p>
                                        <Badge tone={toneByStatus[application.status] || 'gray'}>{arabicLabel('applicationStatus', application.status)}</Badge>
                                    </div>
                                    <p className="mt-1 text-sm text-slate-500">{application.user?.email || 'لا يوجد بريد'}</p>
                                    <p className="mt-1 text-sm text-slate-600">
                                        {application.job?.title || 'وظيفة محذوفة'}
                                        {' · '}
                                        {application.job?.company_profile?.company_name || 'شركة غير متاحة'}
                                    </p>
                                </div>

                                <div>
                                    <p className="text-sm font-black text-madarat-navy">نسبة المطابقة: {application.match_score ?? 0}%</p>
                                    <ProgressBar value={application.match_score ?? 0} />
                                    <p className="mt-1 text-xs text-slate-500">تاريخ الطلب: {formatDate(application.created_at)}</p>
                                </div>

                                <div className="flex flex-wrap gap-2 lg:justify-end">
                                    {application.user && <Link href={`/admin/job-seekers/${application.user.id}`} className="rounded-lg bg-white px-3 py-1.5 text-xs font-black text-madarat-blue ring-1 ring-cyan-100 hover:bg-madarat-sky">ملف الباحث</Link>}
                                    <Link href={`/admin/applications/${application.id}`} className="rounded-lg bg-madarat-blue px-4 py-2 text-sm font-black text-white hover:bg-madarat-navy">عرض التفاصيل</Link>
                                </div>
                            </div>
                        </div>
                    )) : <p className="text-sm text-slate-500">لا توجد طلبات مطابقة.</p>}
                </div>

                <Pagination paginator={applications} />
            </Card>
        </DashboardLayout>
    );
}

function formatDate(value?: string) {
    if (!value) return 'غير محدد';

    return new Intl.DateTimeFormat('ar-LY', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
}
