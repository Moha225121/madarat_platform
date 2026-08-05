import { Link } from '@inertiajs/react';
import { Badge, Card, DashboardLayout, ProgressBar } from '@/Components/Madarat';
import { arabicLabel } from '@/lib/arabicLabels';

const toneByStatus: Record<string, 'gray' | 'cyan' | 'green'> = {
    submitted: 'cyan',
    shortlisted: 'green',
    interview_invited: 'green',
    rejected: 'gray',
    accepted: 'green',
};

export default function ApplicationDetails({ application }: any) {
    const seeker = application.user;
    const profile = seeker?.job_seeker_profile;
    const job = application.job;
    const company = job?.company_profile;
    const invitation = application.interview_invitation;

    return (
        <DashboardLayout title="تفاصيل طلب التوظيف">
            <div className="mb-4 flex flex-wrap items-center gap-3">
                <Link href="/admin/applications" className="text-sm font-black text-madarat-blue hover:underline">العودة إلى طلبات التوظيف</Link>
                {seeker && <Link href={`/admin/job-seekers/${seeker.id}`} className="rounded-lg bg-white px-3 py-1.5 text-xs font-black text-madarat-blue ring-1 ring-cyan-100 hover:bg-madarat-sky">ملف الباحث</Link>}
                {company && <Link href={`/admin/companies/${company.id}/details`} className="rounded-lg bg-white px-3 py-1.5 text-xs font-black text-madarat-blue ring-1 ring-cyan-100 hover:bg-madarat-sky">تفاصيل الشركة</Link>}
            </div>

            <div className="grid gap-5 lg:grid-cols-[1fr_360px]">
                <div className="space-y-5">
                    <Card>
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <div className="flex flex-wrap items-center gap-2">
                                    <h2 className="text-xl font-black text-madarat-navy">{seeker?.name || 'باحث محذوف'}</h2>
                                    <Badge tone={toneByStatus[application.status] || 'gray'}>{arabicLabel('applicationStatus', application.status)}</Badge>
                                </div>
                                <p className="mt-1 text-sm text-slate-500">{seeker?.email || 'لا يوجد بريد'} · {profile?.city || 'مدينة غير محددة'}</p>
                                <p className="mt-1 text-sm text-slate-600">{profile?.headline || profile?.field || 'تخصص غير محدد'}</p>
                            </div>
                            <div className="w-full max-w-xs">
                                <p className="text-sm font-black text-madarat-navy">نسبة المطابقة: {application.match_score ?? 0}%</p>
                                <ProgressBar value={application.match_score ?? 0} />
                            </div>
                        </div>

                        <div className="mt-5 rounded-lg bg-slate-50 p-4">
                            <p className="text-sm font-bold text-slate-500">ملخص المطابقة</p>
                            <p className="mt-2 whitespace-pre-wrap leading-7 text-slate-700">{application.match_summary || 'لا يوجد ملخص مطابقة.'}</p>
                        </div>

                        <div className="mt-4 rounded-lg bg-slate-50 p-4">
                            <p className="text-sm font-bold text-slate-500">رسالة التقديم</p>
                            <p className="mt-2 whitespace-pre-wrap leading-7 text-slate-700">{application.cover_letter || 'لم يرفق الباحث رسالة تقديم.'}</p>
                        </div>
                    </Card>

                    <Card>
                        <h2 className="font-black text-madarat-navy">بيانات الوظيفة</h2>
                        <dl className="mt-4 grid gap-4 md:grid-cols-2">
                            <div><dt className="text-sm font-bold text-slate-500">المسمى الوظيفي</dt><dd className="mt-1 font-black">{job?.title || 'وظيفة محذوفة'}</dd></div>
                            <div><dt className="text-sm font-bold text-slate-500">الشركة</dt><dd className="mt-1 font-black">{company?.company_name || 'شركة غير متاحة'}</dd></div>
                            <div><dt className="text-sm font-bold text-slate-500">الموقع</dt><dd className="mt-1 font-black">{job?.location || 'غير محدد'}</dd></div>
                            <div><dt className="text-sm font-bold text-slate-500">نوع العمل</dt><dd className="mt-1 font-black">{job?.job_type || 'غير محدد'}</dd></div>
                        </dl>
                        <div className="mt-4 flex flex-wrap gap-2">
                            {(job?.required_skills || []).map((skill: string) => <Badge key={skill}>{skill}</Badge>)}
                        </div>
                    </Card>
                </div>

                <div className="space-y-5">
                    <Card>
                        <h2 className="font-black text-madarat-navy">معلومات الطلب</h2>
                        <dl className="mt-4 space-y-3 text-sm">
                            <div><dt className="font-bold text-slate-500">تاريخ التقديم</dt><dd className="mt-1 text-slate-700">{formatDate(application.created_at)}</dd></div>
                            <div><dt className="font-bold text-slate-500">آخر تحديث</dt><dd className="mt-1 text-slate-700">{formatDate(application.updated_at)}</dd></div>
                            <div><dt className="font-bold text-slate-500">الحالة</dt><dd className="mt-1 text-slate-700">{arabicLabel('applicationStatus', application.status)}</dd></div>
                        </dl>
                    </Card>

                    <Card>
                        <h2 className="font-black text-madarat-navy">دعوة المقابلة</h2>
                        {invitation ? (
                            <div className="mt-4 space-y-3 text-sm leading-7 text-slate-700">
                                <p><span className="font-bold text-slate-500">الموعد:</span> {formatDate(invitation.scheduled_at)}</p>
                                <p><span className="font-bold text-slate-500">الحالة:</span> {invitation.status || 'غير محددة'}</p>
                                <p className="whitespace-pre-wrap">{invitation.message || 'لا توجد رسالة.'}</p>
                            </div>
                        ) : <p className="mt-3 text-sm text-slate-500">لم يتم إرسال دعوة مقابلة لهذا الطلب.</p>}
                    </Card>

                    <Card>
                        <h2 className="font-black text-madarat-navy">ملخص الباحث</h2>
                        <p className="mt-3 text-sm leading-7 text-slate-700">{profile?.experience_summary || profile?.bio || 'لا يوجد ملخص متاح.'}</p>
                        <div className="mt-4 flex flex-wrap gap-2">
                            {(profile?.extracted_skills || []).slice(0, 12).map((skill: string) => <Badge key={skill}>{skill}</Badge>)}
                        </div>
                    </Card>
                </div>
            </div>
        </DashboardLayout>
    );
}

function formatDate(value?: string) {
    if (!value) return 'غير محدد';

    return new Intl.DateTimeFormat('ar-LY', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
}
