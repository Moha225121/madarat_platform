import { FormEvent, useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { Badge, Card, DashboardLayout, StatCard, icons } from '@/Components/Madarat';

const statusLabel: Record<string, string> = {
    uploaded: 'تم رفع السيرة',
    analyzing: 'جاري التحليل',
    analyzed: 'تم التحليل',
    failed: 'فشل التحليل',
};

const toneByStatus: Record<string, 'gray' | 'cyan' | 'green'> = {
    uploaded: 'gray',
    analyzing: 'cyan',
    analyzed: 'green',
    failed: 'gray',
};

export default function JobSeekers({ seekers, stats, filters, filterOptions }: any) {
    const [form, setForm] = useState({
        q: filters.q || '',
        city: filters.city || '',
        field: filters.field || '',
        cv_status: filters.cv_status || '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        router.get('/admin/job-seekers', form, { preserveState: true, replace: true });
    };

    const reset = () => {
        const empty = { q: '', city: '', field: '', cv_status: '' };
        setForm(empty);
        router.get('/admin/job-seekers', empty, { preserveState: true, replace: true });
    };

    return (
        <DashboardLayout title="إدارة الباحثين عن عمل">
            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <StatCard label="إجمالي الباحثين" value={stats.total} icon={icons.UserRound} />
                <StatCard label="ملفات مكتملة" value={stats.withProfile} icon={icons.FileText} />
                <StatCard label="سير ذاتية مرفوعة" value={stats.cvUploaded} icon={icons.CheckCircle2} />
                <StatCard label="لديهم طلبات توظيف" value={stats.withApplications} icon={icons.Target} />
            </div>

            <Card className="mt-6">
                <h2 className="font-black text-madarat-navy">بحث وتصفية</h2>
                <form onSubmit={submit} className="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                    <input
                        value={form.q}
                        onChange={(event) => setForm((prev) => ({ ...prev, q: event.target.value }))}
                        placeholder="اسم، بريد، مدينة، تخصص"
                        className="rounded-lg border-slate-200 text-sm"
                    />
                    <select value={form.city} onChange={(event) => setForm((prev) => ({ ...prev, city: event.target.value }))} className="rounded-lg border-slate-200 text-sm">
                        <option value="">كل المدن</option>
                        {filterOptions.cities.map((city: string) => <option key={city} value={city}>{city}</option>)}
                    </select>
                    <select value={form.field} onChange={(event) => setForm((prev) => ({ ...prev, field: event.target.value }))} className="rounded-lg border-slate-200 text-sm">
                        <option value="">كل التخصصات</option>
                        {filterOptions.fields.map((field: string) => <option key={field} value={field}>{field}</option>)}
                    </select>
                    <select value={form.cv_status} onChange={(event) => setForm((prev) => ({ ...prev, cv_status: event.target.value }))} className="rounded-lg border-slate-200 text-sm">
                        <option value="">كل حالات السيرة</option>
                        {filterOptions.cvStatuses.map((status: string) => <option key={status} value={status}>{statusLabel[status] || status}</option>)}
                    </select>
                    <div className="flex gap-2">
                        <button type="submit" className="flex-1 rounded-lg bg-madarat-blue px-4 py-2 text-sm font-black text-white hover:bg-madarat-navy">بحث</button>
                        <button type="button" onClick={reset} className="rounded-lg bg-slate-200 px-4 py-2 text-sm font-black text-slate-700 hover:bg-slate-300">إعادة ضبط</button>
                    </div>
                </form>
            </Card>

            <Card className="mt-6">
                <h2 className="font-black text-madarat-navy">قائمة الباحثين عن عمل</h2>
                <div className="mt-4 space-y-3">
                    {seekers.data.length ? seekers.data.map((seeker: any) => (
                        <div key={seeker.id} className="rounded-lg bg-slate-50 p-4">
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p className="font-black text-madarat-navy">{seeker.name}</p>
                                    <p className="mt-1 text-sm text-slate-500">{seeker.email}</p>
                                    <p className="mt-1 text-sm text-slate-600">
                                        {seeker.job_seeker_profile?.field || 'تخصص غير محدد'}
                                        {' · '}
                                        {seeker.job_seeker_profile?.city || 'مدينة غير محددة'}
                                    </p>
                                </div>
                                <div className="flex flex-wrap items-center gap-2">
                                    <Badge tone={toneByStatus[seeker.job_seeker_profile?.cv_status] || 'gray'}>
                                        {statusLabel[seeker.job_seeker_profile?.cv_status] || 'لا توجد سيرة'}
                                    </Badge>
                                    <span className="rounded-full bg-white px-3 py-1 text-xs font-bold text-slate-600 ring-1 ring-slate-200">طلبات: {seeker.applications_count}</span>
                                    <span className="rounded-full bg-white px-3 py-1 text-xs font-bold text-slate-600 ring-1 ring-slate-200">دورات: {seeker.course_enrollments_count}</span>
                                    <Link href={`/admin/job-seekers/${seeker.id}`} className="rounded-lg bg-madarat-blue px-4 py-2 text-sm font-black text-white hover:bg-madarat-navy">
                                        عرض التفاصيل
                                    </Link>
                                </div>
                            </div>
                        </div>
                    )) : <p className="text-sm text-slate-500">لا توجد نتائج مطابقة.</p>}
                </div>

                <div className="mt-4 flex items-center justify-between">
                    <p className="text-xs text-slate-500">صفحة {seekers.current_page} من {seekers.last_page}</p>
                    <div className="flex gap-2">
                        {seekers.prev_page_url && <Link href={seekers.prev_page_url} className="rounded-lg bg-white px-3 py-1.5 text-sm font-bold text-slate-700 ring-1 ring-slate-200">السابق</Link>}
                        {seekers.next_page_url && <Link href={seekers.next_page_url} className="rounded-lg bg-white px-3 py-1.5 text-sm font-bold text-slate-700 ring-1 ring-slate-200">التالي</Link>}
                    </div>
                </div>
            </Card>
        </DashboardLayout>
    );
}
