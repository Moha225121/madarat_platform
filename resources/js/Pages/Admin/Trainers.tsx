import { FormEvent, useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { Badge, Card, DashboardLayout, StatCard, icons } from '@/Components/Madarat';
import { arabicLabel } from '@/lib/arabicLabels';

export default function Trainers({ trainers, stats, filters, filterOptions }: any) {
    const [form, setForm] = useState({
        q: filters.q || '',
        verification_status: filters.verification_status || '',
        provider_type: filters.provider_type || '',
        city: filters.city || '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        router.get('/admin/trainers', form, { preserveState: true, replace: true });
    };

    const reset = () => {
        const empty = { q: '', verification_status: '', provider_type: '', city: '' };
        setForm(empty);
        router.get('/admin/trainers', empty, { preserveState: true, replace: true });
    };

    return (
        <DashboardLayout title="إدارة مزودي التدريب">
            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <StatCard label="إجمالي المزودين" value={stats.total} icon={icons.UserRound} />
                <StatCard label="حسابات موثقة" value={stats.verified} icon={icons.CheckCircle2} />
                <StatCard label="قيد المراجعة" value={stats.pending} icon={icons.Clock} />
                <StatCard label="إجمالي الدورات" value={stats.courses} icon={icons.BookOpen} />
            </div>

            <Card className="mt-6">
                <h2 className="font-black text-madarat-navy">بحث وتصفية</h2>
                <form onSubmit={submit} className="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                    <input
                        value={form.q}
                        onChange={(event) => setForm((prev) => ({ ...prev, q: event.target.value }))}
                        placeholder="الاسم، البريد، المدينة"
                        className="rounded-lg border-slate-200 text-sm"
                    />
                    <select value={form.verification_status} onChange={(event) => setForm((prev) => ({ ...prev, verification_status: event.target.value }))} className="rounded-lg border-slate-200 text-sm">
                        <option value="">كل حالات التوثيق</option>
                        {filterOptions.verificationStatuses.map((status: string) => <option key={status} value={status}>{arabicLabel('providerStatus', status)}</option>)}
                    </select>
                    <select value={form.provider_type} onChange={(event) => setForm((prev) => ({ ...prev, provider_type: event.target.value }))} className="rounded-lg border-slate-200 text-sm">
                        <option value="">كل الأنواع</option>
                        {filterOptions.providerTypes.map((providerType: string) => <option key={providerType} value={providerType}>{arabicLabel('providerType', providerType)}</option>)}
                    </select>
                    <select value={form.city} onChange={(event) => setForm((prev) => ({ ...prev, city: event.target.value }))} className="rounded-lg border-slate-200 text-sm">
                        <option value="">كل المدن</option>
                        {filterOptions.cities.map((city: string) => <option key={city} value={city}>{city}</option>)}
                    </select>
                    <div className="flex gap-2">
                        <button type="submit" className="flex-1 rounded-lg bg-madarat-blue px-4 py-2 text-sm font-black text-white hover:bg-madarat-navy">بحث</button>
                        <button type="button" onClick={reset} className="rounded-lg bg-slate-200 px-4 py-2 text-sm font-black text-slate-700 hover:bg-slate-300">إعادة ضبط</button>
                    </div>
                </form>
            </Card>

            <Card className="mt-6">
                <h2 className="font-black text-madarat-navy">قائمة مزودي التدريب</h2>
                <div className="mt-4 space-y-3">
                    {trainers.data.length ? trainers.data.map((provider: any) => (
                        <div key={provider.id} className="rounded-lg bg-slate-50 p-4">
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p className="font-black text-madarat-navy">{provider.display_name}</p>
                                    <p className="mt-1 text-sm text-slate-500">{provider.user?.name || 'بدون حساب'} · {provider.user?.email || 'بدون بريد'}</p>
                                    <p className="mt-1 text-sm text-slate-600">{arabicLabel('providerType', provider.provider_type)} · {provider.city || 'مدينة غير محددة'}</p>
                                </div>
                                <div className="flex flex-wrap items-center gap-2">
                                    <Badge tone={provider.verification_status === 'verified' ? 'green' : 'cyan'}>{arabicLabel('providerStatus', provider.verification_status)}</Badge>
                                    <span className="rounded-full bg-white px-3 py-1 text-xs font-bold text-slate-600 ring-1 ring-slate-200">دورات: {provider.courses_count}</span>
                                    <span className="rounded-full bg-white px-3 py-1 text-xs font-bold text-slate-600 ring-1 ring-slate-200">منشورة: {provider.published_courses_count}</span>
                                    <span className="rounded-full bg-white px-3 py-1 text-xs font-bold text-slate-600 ring-1 ring-slate-200">قيد المراجعة: {provider.pending_courses_count}</span>
                                    <Link href={`/admin/trainers/${provider.id}`} className="rounded-lg bg-madarat-blue px-4 py-2 text-sm font-black text-white hover:bg-madarat-navy">عرض التفاصيل</Link>
                                </div>
                            </div>
                        </div>
                    )) : <p className="text-sm text-slate-500">لا توجد نتائج مطابقة.</p>}
                </div>
                <div className="mt-4 flex items-center justify-between">
                    <p className="text-xs text-slate-500">صفحة {trainers.current_page} من {trainers.last_page}</p>
                    <div className="flex gap-2">
                        {trainers.prev_page_url && <Link href={trainers.prev_page_url} className="rounded-lg bg-white px-3 py-1.5 text-sm font-bold text-slate-700 ring-1 ring-slate-200">السابق</Link>}
                        {trainers.next_page_url && <Link href={trainers.next_page_url} className="rounded-lg bg-white px-3 py-1.5 text-sm font-bold text-slate-700 ring-1 ring-slate-200">التالي</Link>}
                    </div>
                </div>
            </Card>
        </DashboardLayout>
    );
}
