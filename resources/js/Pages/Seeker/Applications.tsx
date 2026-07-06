import { Badge, Card, DashboardLayout, EmptyState } from '@/Components/Madarat';

const statusLabels: Record<string, string> = {
    submitted: 'تم التقديم',
    shortlisted: 'القائمة المختصرة',
    interview_invited: 'دعوة مقابلة',
    rejected: 'مرفوض',
};

export default function Applications({ applications }: any) {
    return (
        <DashboardLayout title="طلبات التقديم">
            {applications.length ? (
                <div className="grid gap-4">
                    {applications.map((application: any) => (
                        <Card key={application.id}>
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <h3 className="font-black text-madarat-navy">{application.job.title}</h3>
                                    <p className="text-sm text-slate-500">{application.job.company_profile.company_name}</p>
                                </div>
                                <Badge tone={application.status === 'interview_invited' ? 'green' : 'cyan'}>
                                    {statusLabels[application.status] || application.status}
                                </Badge>
                            </div>
                            <p className="mt-3 text-sm text-slate-500">نسبة المطابقة: {application.match_score}% - {application.match_summary}</p>
                            {application.interview_invitation && (
                                <div className="mt-4 rounded-lg bg-emerald-50 p-4 text-sm leading-6 text-emerald-800">
                                    <strong>دعوة مقابلة</strong>
                                    <p>الموعد: {formatDate(application.interview_invitation.scheduled_at)}</p>
                                    {application.interview_invitation.message && <p>{application.interview_invitation.message}</p>}
                                </div>
                            )}
                        </Card>
                    ))}
                </div>
            ) : (
                <EmptyState title="لا توجد طلبات بعد" text="ابدأ بتصفح الوظائف المناسبة وقدّم على ما يناسبك." />
            )}
        </DashboardLayout>
    );
}

function formatDate(value?: string) {
    if (!value) return 'غير محدد';
    return new Intl.DateTimeFormat('ar-LY', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}
