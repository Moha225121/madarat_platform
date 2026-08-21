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

                <Card>
                    <h3 className="font-black text-madarat-navy">المرشحون المحتملون</h3>
                    <p className="mt-1 text-sm text-slate-500">يمكنك الاطلاع على بيانات المرشحين والتواصل معهم مباشرة.</p>
                    <div className="mt-4 space-y-4">
                        {candidates.map((item: any) => <CandidateCard key={item.profile.id} item={item} />)}
                        {candidates.length === 0 && <p className="text-sm text-slate-500">لا يوجد مرشحون حاليًا.</p>}
                    </div>
                </Card>
            </div>
        </DashboardLayout>
    );
}

function CandidateCard({ item }: { item: any }) {
    const [expanded, setExpanded] = useState(false);
    const { user, profile, match } = item;

    return (
        <div className="rounded-xl border border-slate-100 bg-slate-50 p-4">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <strong className="text-madarat-navy">{user.name}</strong>
                    <p className="mt-1 text-sm text-slate-600">{profile.headline || profile.field || 'باحث عن عمل'}</p>
                    <p className="text-xs text-slate-500">{profile.city || 'المدينة غير محددة'}</p>
                </div>
                <div className="flex items-center gap-2">
                    <Badge tone="green">{match.score}%</Badge>
                    <Button type="button" onClick={() => setExpanded((value) => !value)}>
                        {expanded ? 'إخفاء التفاصيل' : 'عرض التفاصيل والتواصل'}
                    </Button>
                </div>
            </div>
            <p className="mt-2 text-sm text-slate-500">{match.summary}</p>

            {expanded && (
                <div className="mt-4 grid gap-4 border-t border-slate-200 pt-4 md:grid-cols-2">
                    <div className="space-y-3 text-sm">
                        <Detail label="المجال" value={profile.field} />
                        <Detail label="نبذة" value={profile.bio} />
                        <Detail label="ملخص الخبرة" value={profile.experience_summary} />
                        <Detail label="المؤهل العلمي" value={profile.education_summary} />
                        <div>
                            <span className="font-bold text-slate-500">المهارات</span>
                            <div className="mt-2 flex flex-wrap gap-2">
                                {(profile.extracted_skills || []).map((skill: string) => <Badge key={skill}>{skill}</Badge>)}
                                {!profile.extracted_skills?.length && <span className="text-slate-500">غير محددة</span>}
                            </div>
                        </div>
                    </div>
                    <div className="rounded-lg bg-white p-4 ring-1 ring-slate-200">
                        <h4 className="font-black text-madarat-navy">بيانات التواصل</h4>
                        <div className="mt-3 space-y-2 text-sm">
                            <p>البريد: {user.email}</p>
                            <p>الهاتف: {user.phone || 'غير محدد'}</p>
                        </div>
                        <div className="mt-4 flex flex-wrap gap-2">
                            <a href={`mailto:${user.email}?subject=${encodeURIComponent(`بخصوص فرصة ${item.match.score}% مطابقة`)}`} className="rounded-lg bg-madarat-blue px-4 py-2 text-sm font-bold text-white">إرسال بريد</a>
                            {user.phone && <a href={`tel:${user.phone}`} className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-bold text-white">اتصال هاتفي</a>}
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}

function Detail({ label, value }: { label: string; value?: string }) {
    return <p><span className="font-bold text-slate-500">{label}: </span><span className="whitespace-pre-wrap text-slate-700">{value || 'غير محدد'}</span></p>;
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
                            {statusLabels[application.status] || 'حالة غير محددة'}
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
