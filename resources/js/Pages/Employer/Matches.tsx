import { FormEvent, useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import { Badge, Button, Card, DashboardLayout, ProgressBar } from '@/Components/Madarat';

const statusLabels: Record<string, string> = {
    submitted: 'تم التقديم',
    shortlisted: 'القائمة المختصرة',
    interview_invited: 'دعوة مقابلة',
    rejected: 'مرفوض',
};

export default function Matches({ job, applications, candidates }: any) {
    return (
        <DashboardLayout title={`المطابقة الذكية - ${job.title}`}>
            <div className="grid gap-4">
                {applications.map((application: any) => <ApplicationCard key={application.id} application={application} />)}

                {applications.length === 0 && (
                    <Card>
                        <h3 className="font-black text-madarat-navy">مرشحون محتملون</h3>
                        <div className="mt-4 space-y-3">
                            {candidates.slice(0, 5).map((item: any) => (
                                <div key={item.profile.id} className="rounded-lg bg-slate-50 p-3">
                                    <div className="flex justify-between">
                                        <strong>{item.user.name}</strong>
                                        <Badge tone="green">{item.match.score}%</Badge>
                                    </div>
                                    <p className="mt-1 text-sm text-slate-500">{item.match.summary}</p>
                                </div>
                            ))}
                        </div>
                    </Card>
                )}
            </div>
        </DashboardLayout>
    );
}

function ApplicationCard({ application }: { application: any }) {
    const [showInvite, setShowInvite] = useState(false);
    const invitation = application.interview_invitation;
    const { data, setData, post, processing, errors } = useForm({
        scheduled_at: invitation?.scheduled_at ? toLocalInputValue(invitation.scheduled_at) : '',
        message: invitation?.message || '',
    });

    const submitInvite = (event: FormEvent) => {
        event.preventDefault();
        post(`/employer/applications/${application.id}/invite-interview`, {
            preserveScroll: true,
            onSuccess: () => setShowInvite(false),
        });
    };

    return (
        <Card>
            <div className="grid gap-4 md:grid-cols-[1fr_180px_280px] md:items-start">
                <div>
                    <div className="flex flex-wrap items-center gap-2">
                        <h3 className="font-black text-madarat-navy">{application.user.name}</h3>
                        <Badge tone={application.status === 'interview_invited' ? 'green' : 'cyan'}>
                            {statusLabels[application.status] || application.status}
                        </Badge>
                    </div>
                    <p className="mt-2 text-sm leading-6 text-slate-500">{application.user.job_seeker_profile?.experience_summary || 'لا يوجد ملخص خبرة بعد.'}</p>
                    <div className="mt-3 flex flex-wrap gap-2">
                        {(application.user.job_seeker_profile?.extracted_skills || []).map((skill: string) => <Badge key={skill}>{skill}</Badge>)}
                    </div>
                    {invitation && (
                        <div className="mt-4 rounded-lg bg-emerald-50 p-3 text-sm leading-6 text-emerald-800">
                            <strong>دعوة مقابلة مرسلة</strong>
                            <p>الموعد: {formatDate(invitation.scheduled_at)}</p>
                            {invitation.message && <p>{invitation.message}</p>}
                        </div>
                    )}
                </div>

                <div>
                    <p className="text-sm font-bold text-madarat-navy">{application.match_score}%</p>
                    <ProgressBar value={application.match_score} />
                    <p className="mt-2 text-xs leading-5 text-slate-500">{application.match_summary}</p>
                </div>

                <div className="space-y-2">
                    <Button type="button" onClick={() => router.post(`/employer/applications/${application.id}/shortlist`, {}, { preserveScroll: true })} className="w-full">
                        إضافة للقائمة المختصرة
                    </Button>
                    <Button type="button" onClick={() => setShowInvite((value) => !value)} className="w-full bg-madarat-cyan hover:bg-madarat-blue">
                        {invitation ? 'تعديل دعوة المقابلة' : 'دعوة لمقابلة'}
                    </Button>
                </div>
            </div>

            {showInvite && (
                <form onSubmit={submitInvite} className="mt-5 grid gap-4 rounded-lg border border-cyan-100 bg-madarat-sky/60 p-4 md:grid-cols-[240px_1fr_auto] md:items-start">
                    <label className="block">
                        <span className="text-sm font-bold text-madarat-navy">موعد المقابلة</span>
                        <input
                            type="datetime-local"
                            value={data.scheduled_at}
                            onChange={(event) => setData('scheduled_at', event.target.value)}
                            className="mt-1 w-full rounded-lg border-slate-200 text-sm focus:border-madarat-cyan focus:ring-madarat-cyan"
                        />
                        {errors.scheduled_at && <span className="mt-1 block text-xs font-bold text-red-600">{errors.scheduled_at}</span>}
                    </label>

                    <label className="block">
                        <span className="text-sm font-bold text-madarat-navy">رسالة للمتقدم</span>
                        <textarea
                            value={data.message}
                            onChange={(event) => setData('message', event.target.value)}
                            rows={3}
                            placeholder="اكتب تفاصيل المقابلة أو طريقة التواصل..."
                            className="mt-1 w-full rounded-lg border-slate-200 text-sm focus:border-madarat-cyan focus:ring-madarat-cyan"
                        />
                        {errors.message && <span className="mt-1 block text-xs font-bold text-red-600">{errors.message}</span>}
                    </label>

                    <Button disabled={processing} className="mt-6 whitespace-nowrap">
                        {processing ? 'جار الإرسال...' : 'إرسال الدعوة'}
                    </Button>
                </form>
            )}
        </Card>
    );
}

function toLocalInputValue(value: string) {
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '';
    const offset = date.getTimezoneOffset();
    return new Date(date.getTime() - offset * 60000).toISOString().slice(0, 16);
}

function formatDate(value?: string) {
    if (!value) return 'غير محدد';
    return new Intl.DateTimeFormat('ar-LY', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}
