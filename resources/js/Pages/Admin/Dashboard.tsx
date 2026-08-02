import { FormEvent, useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { Badge, Card, CompanyVerificationBadge, DashboardLayout, StatCard, icons } from '@/Components/Madarat';

const roleLabel: Record<string, string> = {
    admin: 'مدير',
    employer: 'شركة',
    job_seeker: 'باحث عن عمل',
    training_provider: 'مزود تدريب',
};

export default function AdminDashboard({ stats, pendingJobs, pendingCompanies = [], pendingCourses = [], growth, latestSeekers = [], latestCompanies = [], latestTrainers = [], users, userFilters = {}, roleOptions = [] }: any) {
    const [form, setForm] = useState({
        q: userFilters.q || '',
        role: userFilters.role || '',
    });

    const submitUsersSearch = (event: FormEvent) => {
        event.preventDefault();
        router.get('/admin/dashboard', form, { preserveState: true, replace: true });
    };

    const resetUsersSearch = () => {
        const empty = { q: '', role: '' };
        setForm(empty);
        router.get('/admin/dashboard', empty, { preserveState: true, replace: true });
    };

    return (
        <DashboardLayout title="لوحة الإدارة">
            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-7">
                <StatCard label="إجمالي المستخدمين" value={stats.users || 0} icon={icons.UserRound} />
                <StatCard label="إجمالي الباحثين" value={stats.seekers} icon={icons.UserRound} />
                <StatCard label="إجمالي الشركات" value={stats.companies} icon={icons.Building2} />
                <StatCard label="إجمالي مزودي التدريب" value={stats.trainers || 0} icon={icons.BookOpen} />
                <StatCard label="إجمالي الوظائف" value={stats.jobs} icon={icons.BriefcaseBusiness} />
                <StatCard label="طلبات توثيق الشركات" value={stats.pendingCompanies || 0} icon={icons.CheckCircle2} />
                <StatCard label="دورات قيد المراجعة" value={stats.pendingCourses || 0} icon={icons.BookOpen} />
            </div>

            <Card className="mt-6">
                <h2 className="font-black text-madarat-navy">جميع مستخدمي المنصة</h2>
                <p className="mt-2 text-sm text-slate-600">هذه القائمة تعرض كل المستخدمين في المنصة مع البحث بالاسم أو البريد أو الهاتف، والتصفية حسب الدور.</p>

                <div className="mt-3 flex flex-wrap gap-2 text-xs">
                    <span className="rounded-full bg-slate-100 px-3 py-1 font-bold text-slate-700">مدراء: {stats.admins || 0}</span>
                    <span className="rounded-full bg-slate-100 px-3 py-1 font-bold text-slate-700">باحثون: {stats.seekers || 0}</span>
                    <span className="rounded-full bg-slate-100 px-3 py-1 font-bold text-slate-700">شركات: {stats.employers || 0}</span>
                    <span className="rounded-full bg-slate-100 px-3 py-1 font-bold text-slate-700">مزودو تدريب: {stats.trainingProviders || 0}</span>
                </div>

                <form onSubmit={submitUsersSearch} className="mt-4 grid gap-3 md:grid-cols-[1fr_220px_auto]">
                    <input
                        value={form.q}
                        onChange={(event) => setForm((prev) => ({ ...prev, q: event.target.value }))}
                        placeholder="ابحث بالاسم أو البريد أو الهاتف"
                        className="rounded-lg border-slate-200 text-sm"
                    />
                    <select value={form.role} onChange={(event) => setForm((prev) => ({ ...prev, role: event.target.value }))} className="rounded-lg border-slate-200 text-sm">
                        <option value="">كل الأدوار</option>
                        {roleOptions.map((role: string) => <option key={role} value={role}>{roleLabel[role] || role}</option>)}
                    </select>
                    <div className="flex gap-2">
                        <button type="submit" className="rounded-lg bg-madarat-blue px-4 py-2 text-sm font-bold text-white hover:bg-madarat-navy">بحث</button>
                        <button type="button" onClick={resetUsersSearch} className="rounded-lg bg-slate-200 px-4 py-2 text-sm font-bold text-slate-700 hover:bg-slate-300">إعادة ضبط</button>
                    </div>
                </form>

                <div className="mt-4 overflow-x-auto">
                    <table className="min-w-full text-right text-sm">
                        <thead>
                            <tr className="border-b border-slate-200 text-slate-500">
                                <th className="px-3 py-2">الاسم</th>
                                <th className="px-3 py-2">البريد</th>
                                <th className="px-3 py-2">الهاتف</th>
                                <th className="px-3 py-2">الدور</th>
                                <th className="px-3 py-2">تاريخ التسجيل</th>
                            </tr>
                        </thead>
                        <tbody>
                            {users.data.length ? users.data.map((user: any) => (
                                <tr key={user.id} className="border-b border-slate-100">
                                    <td className="px-3 py-2 font-bold text-madarat-navy">{user.name}</td>
                                    <td className="px-3 py-2 text-slate-600">{user.email}</td>
                                    <td className="px-3 py-2 text-slate-600">{user.phone || 'غير متاح'}</td>
                                    <td className="px-3 py-2">
                                        <Badge tone="gray">{roleLabel[user.role] || user.role}</Badge>
                                    </td>
                                    <td className="px-3 py-2 text-slate-500">{user.created_at ? String(user.created_at).slice(0, 10) : '—'}</td>
                                </tr>
                            )) : (
                                <tr>
                                    <td colSpan={5} className="px-3 py-4 text-center text-slate-500">لا توجد نتائج مطابقة.</td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="mt-4 flex items-center justify-between">
                    <p className="text-xs text-slate-500">صفحة {users.current_page} من {users.last_page}</p>
                    <div className="flex gap-2">
                        {users.prev_page_url && <Link href={users.prev_page_url} className="rounded-lg bg-white px-3 py-1.5 text-sm font-bold text-slate-700 ring-1 ring-slate-200">السابق</Link>}
                        {users.next_page_url && <Link href={users.next_page_url} className="rounded-lg bg-white px-3 py-1.5 text-sm font-bold text-slate-700 ring-1 ring-slate-200">التالي</Link>}
                    </div>
                </div>
            </Card>

            <Card className="mt-6">
                <h2 className="font-black text-madarat-navy">إدارة شاملة للمنصة</h2>
                <p className="mt-2 text-sm text-slate-600">ابحث في بيانات الباحثين عن عمل والشركات ومزودي التدريب، وافتح صفحات التفاصيل الكاملة لكل ملف.</p>
                <div className="mt-4 grid gap-3 md:grid-cols-3">
                    <Link href="/admin/job-seekers" className="rounded-lg bg-madarat-sky p-4 text-madarat-navy ring-1 ring-cyan-100 transition hover:-translate-y-0.5">
                        <p className="font-black">إدارة الباحثين عن عمل</p>
                        <p className="mt-1 text-sm">بحث متقدم + عرض السيرة والطلبات والمسار التدريبي.</p>
                    </Link>
                    <Link href="/admin/companies" className="rounded-lg bg-madarat-sky p-4 text-madarat-navy ring-1 ring-cyan-100 transition hover:-translate-y-0.5">
                        <p className="font-black">إدارة الشركات</p>
                        <p className="mt-1 text-sm">بحث حسب الحالة والقطاع + تفاصيل الوظائف والتقديمات.</p>
                    </Link>
                    <Link href="/admin/trainers" className="rounded-lg bg-madarat-sky p-4 text-madarat-navy ring-1 ring-cyan-100 transition hover:-translate-y-0.5">
                        <p className="font-black">إدارة مزودي التدريب</p>
                        <p className="mt-1 text-sm">بحث شامل + تفاصيل الدورات والتسجيلات وحالة التوثيق.</p>
                    </Link>
                </div>
            </Card>

            <div className="mt-6 grid gap-5 lg:grid-cols-3">
                <Card>
                    <h2 className="font-black text-madarat-navy">أحدث الباحثين عن عمل</h2>
                    <div className="mt-3 space-y-2">
                        {latestSeekers.length ? latestSeekers.map((seeker: any) => (
                            <Link key={seeker.id} href={`/admin/job-seekers/${seeker.id}`} className="block rounded-lg bg-slate-50 p-3 hover:bg-slate-100">
                                <p className="font-bold">{seeker.name}</p>
                                <p className="mt-1 text-xs text-slate-500">{seeker.email}</p>
                            </Link>
                        )) : <p className="text-sm text-slate-500">لا توجد سجلات حالياً.</p>}
                    </div>
                </Card>
                <Card>
                    <h2 className="font-black text-madarat-navy">أحدث الشركات</h2>
                    <div className="mt-3 space-y-2">
                        {latestCompanies.length ? latestCompanies.map((company: any) => (
                            <Link key={company.id} href={`/admin/companies/${company.id}/details`} className="block rounded-lg bg-slate-50 p-3 hover:bg-slate-100">
                                <p className="font-bold">{company.company_name}</p>
                                <p className="mt-1 text-xs text-slate-500">{company.user?.name || 'لا يوجد صاحب حساب'}</p>
                            </Link>
                        )) : <p className="text-sm text-slate-500">لا توجد شركات مسجلة حالياً.</p>}
                    </div>
                </Card>
                <Card>
                    <h2 className="font-black text-madarat-navy">أحدث مزودي التدريب</h2>
                    <div className="mt-3 space-y-2">
                        {latestTrainers.length ? latestTrainers.map((provider: any) => (
                            <Link key={provider.id} href={`/admin/trainers/${provider.id}`} className="block rounded-lg bg-slate-50 p-3 hover:bg-slate-100">
                                <p className="font-bold">{provider.display_name}</p>
                                <p className="mt-1 text-xs text-slate-500">{provider.user?.name || 'لا يوجد صاحب حساب'}</p>
                            </Link>
                        )) : <p className="text-sm text-slate-500">لا توجد سجلات حالياً.</p>}
                    </div>
                </Card>
            </div>

            <Card className="mt-6">
                <h2 className="font-black text-madarat-navy">دورات بانتظار المراجعة</h2>
                <div className="mt-4 grid gap-3 md:grid-cols-2">
                    {pendingCourses.length ? pendingCourses.map((course: any) => (
                        <div key={course.id} className="flex flex-wrap items-center justify-between gap-3 rounded-lg bg-slate-50 p-3">
                            <div>
                                <strong>{course.title}</strong>
                                <p className="mt-1 text-sm text-slate-500">{course.provider?.display_name}</p>
                            </div>
                            <Link href={`/admin/training/courses/${course.id}/review`} className="rounded-lg bg-madarat-blue px-4 py-2 text-sm font-bold text-white hover:bg-madarat-navy">
                                عرض التفاصيل واتخاذ القرار
                            </Link>
                        </div>
                    )) : <p className="text-sm text-slate-500">لا توجد دورات بانتظار المراجعة.</p>}
                </div>
            </Card>

            <div className="mt-6 grid gap-5 lg:grid-cols-2">
                <Card>
                    <h2 className="font-black text-madarat-navy">وظائف بانتظار المراجعة</h2>
                    <div className="mt-4 space-y-3">
                        {pendingJobs.length ? pendingJobs.map((job: any) => (
                            <div key={job.id} className="rounded-lg bg-slate-50 p-3">
                                <div className="flex flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <strong>{job.title}</strong>
                                        <p className="mt-1 flex flex-wrap items-center gap-2 text-sm text-slate-500">
                                            <span>{job.company_profile.company_name}</span>
                                            <CompanyVerificationBadge status={job.company_profile.verification_status} />
                                        </p>
                                    </div>
                                    <Link href={`/admin/jobs/${job.id}/review`} className="rounded-lg bg-madarat-blue px-4 py-2 text-sm font-bold text-white shadow-sm shadow-madarat-blue/20 hover:bg-madarat-navy">
                                        عرض التفاصيل واتخاذ القرار
                                    </Link>
                                </div>
                            </div>
                        )) : <p className="text-sm text-slate-500">لا توجد وظائف بانتظار المراجعة.</p>}
                    </div>
                </Card>

                <Card>
                    <h2 className="font-black text-madarat-navy">طلبات توثيق الشركات</h2>
                    <div className="mt-4 space-y-3">
                        {pendingCompanies.length ? pendingCompanies.map((company: any) => (
                            <div key={company.id} className="rounded-lg bg-slate-50 p-3">
                                <div className="flex flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <div className="flex flex-wrap items-center gap-2">
                                            <strong>{company.company_name}</strong>
                                            <Badge tone="cyan">قيد المراجعة</Badge>
                                        </div>
                                        <p className="mt-1 text-sm text-slate-500">{company.industry || 'قطاع غير محدد'} - {company.headquarters || 'مقر غير محدد'}</p>
                                        <p className="mt-1 text-xs text-slate-400">صاحب الحساب: {company.user?.name}</p>
                                    </div>
                                    <Link href={`/admin/companies/${company.id}/verification`} className="rounded-lg bg-madarat-blue px-4 py-2 text-sm font-bold text-white shadow-sm shadow-madarat-blue/20 hover:bg-madarat-navy">
                                        عرض الملف
                                    </Link>
                                </div>
                            </div>
                        )) : <p className="text-sm text-slate-500">لا توجد طلبات توثيق شركات الآن.</p>}
                    </div>
                </Card>
            </div>

            <div className="mt-6 grid gap-5 lg:grid-cols-2">
                <Card>
                    <h2 className="font-black text-madarat-navy">نمو أسبوعي</h2>
                    <div className="mt-5 flex h-40 items-end gap-3">
                        {growth.map((v: number, i: number) => <div key={i} className="flex-1 rounded-t-lg bg-madarat-cyan" style={{ height: `${v * 2}px` }} />)}
                    </div>
                </Card>
                <Card>
                    <h2 className="font-black text-madarat-navy">تنبيهات ذكية</h2>
                    <p className="mt-2 leading-7 text-slate-600">لضمان لوحة إدارة مكتملة، راجع ملفات الشركات ومقدمي التدريب والباحثين بشكل دوري عبر صفحات البحث التفصيلية.</p>
                </Card>
            </div>
        </DashboardLayout>
    );
}
