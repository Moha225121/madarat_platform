import { FormEvent, useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { Badge, Card, CompanyVerificationBadge, DashboardLayout, StatCard, icons } from '@/Components/Madarat';

const statusLabel: Record<string, string> = {
    verified: 'موثقة',
    pending: 'قيد المراجعة',
    unverified: 'غير موثقة',
    rejected: 'مرفوضة',
};

export default function Companies({ companies, stats, filters, filterOptions }: any) {
    const [form, setForm] = useState({
        q: filters.q || '',
        verification_status: filters.verification_status || '',
        industry: filters.industry || '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        router.get('/admin/companies', form, { preserveState: true, replace: true });
    };

    const reset = () => {
        const empty = { q: '', verification_status: '', industry: '' };
        setForm(empty);
        router.get('/admin/companies', empty, { preserveState: true, replace: true });
    };

    return (
        <DashboardLayout title="إدارة الشركات">
            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <StatCard label="إجمالي الشركات" value={stats.total} icon={icons.Building2} />
                <StatCard label="شركات موثقة" value={stats.verified} icon={icons.CheckCircle2} />
                <StatCard label="قيد المراجعة" value={stats.pending} icon={icons.Clock} />
                <StatCard label="وظائف منشورة" value={stats.jobs} icon={icons.BriefcaseBusiness} />
            </div>

            <Card className="mt-6">
                <h2 className="font-black text-madarat-navy">بحث وتصفية</h2>
                <form onSubmit={submit} className="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <input
                        value={form.q}
                        onChange={(event) => setForm((prev) => ({ ...prev, q: event.target.value }))}
                        placeholder="اسم الشركة، القطاع، البريد"
                        className="rounded-lg border-slate-200 text-sm"
                    />
                    <select value={form.verification_status} onChange={(event) => setForm((prev) => ({ ...prev, verification_status: event.target.value }))} className="rounded-lg border-slate-200 text-sm">
                        <option value="">كل حالات التوثيق</option>
                        {filterOptions.verificationStatuses.map((status: string) => <option key={status} value={status}>{statusLabel[status] || status}</option>)}
                    </select>
                    <select value={form.industry} onChange={(event) => setForm((prev) => ({ ...prev, industry: event.target.value }))} className="rounded-lg border-slate-200 text-sm">
                        <option value="">كل القطاعات</option>
                        {filterOptions.industries.map((industry: string) => <option key={industry} value={industry}>{industry}</option>)}
                    </select>
                    <div className="flex gap-2">
                        <button type="submit" className="flex-1 rounded-lg bg-madarat-blue px-4 py-2 text-sm font-black text-white hover:bg-madarat-navy">بحث</button>
                        <button type="button" onClick={reset} className="rounded-lg bg-slate-200 px-4 py-2 text-sm font-black text-slate-700 hover:bg-slate-300">إعادة ضبط</button>
                    </div>
                </form>
            </Card>

            <Card className="mt-6">
                <h2 className="font-black text-madarat-navy">قائمة الشركات</h2>
                <div className="mt-4 space-y-3">
                    {companies.data.length ? companies.data.map((company: any) => (
                        <div key={company.id} className="rounded-lg bg-slate-50 p-4">
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <div className="flex flex-wrap items-center gap-2">
                                        <p className="font-black text-madarat-navy">{company.company_name}</p>
                                        <CompanyVerificationBadge status={company.verification_status} />
                                        {company.verification_status !== 'verified' && <Badge tone="gray">{statusLabel[company.verification_status] || 'غير محددة'}</Badge>}
                                    </div>
                                    <p className="mt-1 text-sm text-slate-500">{company.user?.name || 'لا يوجد صاحب حساب'} · {company.user?.email || 'لا يوجد بريد'}</p>
                                    <p className="mt-1 text-sm text-slate-600">{company.industry || 'قطاع غير محدد'} · {company.headquarters || 'مقر غير محدد'}</p>
                                </div>
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="rounded-full bg-white px-3 py-1 text-xs font-bold text-slate-600 ring-1 ring-slate-200">وظائف: {company.jobs_count}</span>
                                    <span className="rounded-full bg-white px-3 py-1 text-xs font-bold text-slate-600 ring-1 ring-slate-200">منشورة: {company.published_jobs_count}</span>
                                    <span className="rounded-full bg-white px-3 py-1 text-xs font-bold text-slate-600 ring-1 ring-slate-200">قيد المراجعة: {company.pending_jobs_count}</span>
                                    <Link href={`/admin/companies/${company.id}/details`} className="rounded-lg bg-madarat-blue px-4 py-2 text-sm font-black text-white hover:bg-madarat-navy">عرض التفاصيل</Link>
                                </div>
                            </div>
                        </div>
                    )) : <p className="text-sm text-slate-500">لا توجد نتائج مطابقة.</p>}
                </div>

                <div className="mt-4 flex items-center justify-between">
                    <p className="text-xs text-slate-500">صفحة {companies.current_page} من {companies.last_page}</p>
                    <div className="flex gap-2">
                        {companies.prev_page_url && <Link href={companies.prev_page_url} className="rounded-lg bg-white px-3 py-1.5 text-sm font-bold text-slate-700 ring-1 ring-slate-200">السابق</Link>}
                        {companies.next_page_url && <Link href={companies.next_page_url} className="rounded-lg bg-white px-3 py-1.5 text-sm font-bold text-slate-700 ring-1 ring-slate-200">التالي</Link>}
                    </div>
                </div>
            </Card>
        </DashboardLayout>
    );
}
