import { Link } from '@inertiajs/react';
import { Badge, Card, DashboardLayout, StatCard, icons } from '@/Components/Madarat';
import { arabicLabel } from '@/lib/arabicLabels';

export default function Training({ providers, courses, stats }: any) {
    return <DashboardLayout title="إدارة التدريب">
        <div className="grid gap-4 md:grid-cols-4">
            <StatCard label="مقدمو التدريب" value={stats.providers} icon={icons.UserRound} />
            <StatCard label="موثقون" value={stats.verified} icon={icons.CheckCircle2} />
            <StatCard label="دورات منشورة" value={stats.published} icon={icons.BookOpen} />
            <StatCard label="بانتظار المراجعة" value={stats.pending} icon={icons.Clock} />
        </div>
        <div className="mt-6 grid gap-5 lg:grid-cols-2">
            <Card>
                <h2 className="font-black">مقدمو التدريب</h2>
                <div className="mt-3 space-y-2">
                    {providers.data.map((provider: any) => <Link href={`/admin/training/providers/${provider.id}`} key={provider.id} className="flex justify-between rounded-lg bg-slate-50 p-3">
                        <span>{provider.display_name} · {arabicLabel('providerType', provider.provider_type)}</span>
                        <Badge tone="cyan">{arabicLabel('providerStatus', provider.verification_status)}</Badge>
                    </Link>)}
                </div>
            </Card>
            <Card>
                <h2 className="font-black">دورات بانتظار المراجعة</h2>
                <div className="mt-3 space-y-3">
                    {courses.data.length ? courses.data.map((course: any) => <div key={course.id} className="rounded-lg bg-slate-50 p-3">
                        <strong>{course.title}</strong>
                        <p className="text-sm text-slate-500">{course.provider.display_name}</p>
                        <Link href={`/admin/training/courses/${course.id}/review`} className="mt-3 inline-flex rounded-lg bg-madarat-blue px-4 py-2 text-sm font-black text-white hover:bg-madarat-navy">
                            عرض التفاصيل واتخاذ القرار
                        </Link>
                    </div>) : <p className="text-sm text-slate-500">لا توجد دورات بانتظار المراجعة.</p>}
                </div>
            </Card>
        </div>
    </DashboardLayout>;
}
