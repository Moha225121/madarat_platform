import { Link } from '@inertiajs/react';
import { Badge, Card, CompanyVerificationBadge, DashboardLayout, StatCard, icons } from '@/Components/Madarat';

export default function AdminDashboard({ stats, pendingJobs, pendingCompanies = [], growth }: any) {
    return (
        <DashboardLayout title="لوحة الإدارة">
            <div className="grid gap-4 md:grid-cols-4">
                <StatCard label="إجمالي الباحثين" value={stats.seekers} icon={icons.UserRound} />
                <StatCard label="إجمالي الشركات" value={stats.companies} icon={icons.Building2} />
                <StatCard label="إجمالي الوظائف" value={stats.jobs} icon={icons.BriefcaseBusiness} />
                <StatCard label="طلبات توثيق الشركات" value={stats.pendingCompanies || 0} icon={icons.CheckCircle2} />
            </div>

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
                    <p className="mt-2 leading-7 text-slate-600">راجع طلبات توثيق الشركات قبل إظهار علامة التوثيق للزوار والباحثين عن عمل.</p>
                </Card>
            </div>
        </DashboardLayout>
    );
}
