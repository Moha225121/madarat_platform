import { Link } from '@inertiajs/react';
import { Badge, Card, DashboardLayout } from '@/Components/Madarat';

export default function Registrations({ enrollments }: any) {
    return <DashboardLayout title="دوراتي المسجّل بها">
        <div className="space-y-3">
            {enrollments.length ? enrollments.map((enrollment: any) => <Card key={enrollment.id}>
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <Link className="text-lg font-black text-madarat-navy" href={`/courses/${enrollment.course.slug}`}>{enrollment.course.title}</Link>
                        <p className="mt-1 text-sm text-slate-500">{enrollment.course.provider?.display_name}</p>
                    </div>
                    <Badge tone="green">مسجّل</Badge>
                </div>
                <p className="mt-3 text-sm text-slate-600">تاريخ التسجيل: {new Date(enrollment.registered_at).toLocaleDateString('ar-LY')}</p>
            </Card>) : <Card><p className="text-slate-500">لم تسجّل في أي دورة حتى الآن.</p><Link href="/courses" className="mt-4 inline-flex rounded-lg bg-madarat-blue px-4 py-2 font-black text-white">استعراض الدورات المتاحة</Link></Card>}
        </div>
    </DashboardLayout>;
}
