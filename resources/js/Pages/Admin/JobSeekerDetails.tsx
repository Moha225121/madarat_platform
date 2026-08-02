import { Link } from '@inertiajs/react';
import { Badge, Card, DashboardLayout, StatCard, icons } from '@/Components/Madarat';
import { arabicLabel } from '@/lib/arabicLabels';

const statusLabel: Record<string, string> = {
    uploaded: 'تم رفع السيرة',
    analyzing: 'جاري التحليل',
    analyzed: 'تم التحليل',
    failed: 'فشل التحليل',
};

export default function JobSeekerDetails({ seeker, stats }: any) {
    const profile = seeker.job_seeker_profile;

    return (
        <DashboardLayout title="تفاصيل الباحث عن عمل">
            <div className="mb-4">
                <Link href="/admin/job-seekers" className="text-sm font-black text-madarat-blue hover:underline">العودة إلى القائمة</Link>
            </div>

            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
                <StatCard label="طلبات التوظيف" value={stats.applications} icon={icons.BriefcaseBusiness} />
                <StatCard label="قائمة مختصرة" value={stats.shortlisted} icon={icons.Target} />
                <StatCard label="دعوات مقابلة" value={stats.interviews} icon={icons.CheckCircle2} />
                <StatCard label="دورات مسجل بها" value={stats.courses} icon={icons.BookOpen} />
                <StatCard label="دورات محفوظة" value={stats.savedCourses} icon={icons.FileText} />
                <StatCard label="توصيات تدريب" value={stats.recommendedCourses} icon={icons.Sparkles} />
            </div>

            <div className="mt-6 grid gap-5 lg:grid-cols-[1fr_340px]">
                <div className="space-y-5">
                    <Card>
                        <h2 className="text-xl font-black text-madarat-navy">الملف الشخصي</h2>
                        <p className="mt-2 text-sm text-slate-500">{seeker.name} · {seeker.email}</p>
                        <dl className="mt-4 grid gap-4 md:grid-cols-2">
                            <div><dt className="text-sm font-bold text-slate-500">العنوان المهني</dt><dd className="mt-1 font-black">{profile?.headline || 'غير محدد'}</dd></div>
                            <div><dt className="text-sm font-bold text-slate-500">التخصص</dt><dd className="mt-1 font-black">{profile?.field || 'غير محدد'}</dd></div>
                            <div><dt className="text-sm font-bold text-slate-500">المدينة</dt><dd className="mt-1 font-black">{profile?.city || 'غير محددة'}</dd></div>
                            <div><dt className="text-sm font-bold text-slate-500">درجة الملف</dt><dd className="mt-1 font-black">{profile?.profile_score ?? 'غير متاحة'}</dd></div>
                        </dl>
                        <div className="mt-4 rounded-lg bg-slate-50 p-4">
                            <p className="text-sm font-bold text-slate-500">نبذة</p>
                            <p className="mt-2 whitespace-pre-wrap leading-7 text-slate-700">{profile?.bio || 'لا توجد نبذة.'}</p>
                        </div>
                    </Card>

                    <Card>
                        <h2 className="font-black text-madarat-navy">آخر طلبات التوظيف</h2>
                        <div className="mt-4 space-y-3">
                            {seeker.applications.length ? seeker.applications.map((application: any) => (
                                <div key={application.id} className="rounded-lg bg-slate-50 p-3">
                                    <p className="font-black">{application.job?.title || 'وظيفة محذوفة'}</p>
                                    <p className="mt-1 text-sm text-slate-500">{application.job?.company_profile?.company_name || 'شركة غير متاحة'}</p>
                                    <div className="mt-2 flex flex-wrap items-center gap-2 text-xs">
                                        <Badge tone="cyan">{arabicLabel('applicationStatus', application.status)}</Badge>
                                        {application.match_score !== null && <span className="rounded-full bg-white px-2 py-1 ring-1 ring-slate-200">تطابق: {application.match_score}%</span>}
                                        {application.interview_invitation && <span className="rounded-full bg-emerald-50 px-2 py-1 text-emerald-700 ring-1 ring-emerald-100">تمت دعوته لمقابلة</span>}
                                    </div>
                                </div>
                            )) : <p className="text-sm text-slate-500">لا توجد طلبات توظيف.</p>}
                        </div>
                    </Card>

                    <Card>
                        <h2 className="font-black text-madarat-navy">الدورات المسجل بها</h2>
                        <div className="mt-4 space-y-3">
                            {seeker.course_enrollments.length ? seeker.course_enrollments.map((enrollment: any) => (
                                <div key={enrollment.id} className="rounded-lg bg-slate-50 p-3">
                                    <p className="font-black">{enrollment.course?.title || 'دورة غير متاحة'}</p>
                                    <p className="mt-1 text-sm text-slate-500">{enrollment.course?.provider?.display_name || 'مزود غير متاح'}</p>
                                    <p className="mt-1 text-xs text-slate-500">الحالة: {enrollment.status || 'غير محددة'}</p>
                                </div>
                            )) : <p className="text-sm text-slate-500">لا توجد تسجيلات تدريبية.</p>}
                        </div>
                    </Card>
                </div>

                <div className="space-y-5">
                    <Card>
                        <h2 className="font-black text-madarat-navy">السيرة الذاتية والتحليل</h2>
                        <p className="mt-2 text-sm text-slate-600">حالة السيرة: {statusLabel[profile?.cv_status] || 'غير محددة'}</p>
                        {profile?.cv_path ? <a href={`/storage/${profile.cv_path}`} target="_blank" rel="noreferrer" className="mt-3 inline-flex rounded-lg bg-madarat-blue px-4 py-2 text-sm font-black text-white hover:bg-madarat-navy">عرض ملف السيرة</a> : <p className="mt-3 text-sm text-slate-500">لم يتم رفع ملف سيرة ذاتية.</p>}
                        <div className="mt-4">
                            <p className="text-sm font-bold text-slate-500">مهارات مستخرجة</p>
                            <p className="mt-1 leading-7 text-slate-700">{profile?.extracted_skills?.length ? profile.extracted_skills.join('، ') : 'لا توجد بيانات.'}</p>
                        </div>
                        <div className="mt-3">
                            <p className="text-sm font-bold text-slate-500">مهارات ناقصة</p>
                            <p className="mt-1 leading-7 text-slate-700">{profile?.missing_skills?.length ? profile.missing_skills.join('، ') : 'لا توجد بيانات.'}</p>
                        </div>
                    </Card>

                    <Card>
                        <h2 className="font-black text-madarat-navy">ملخصات وتحسينات مقترحة</h2>
                        <div className="mt-3 space-y-3 text-sm leading-7 text-slate-700">
                            <div>
                                <p className="font-bold text-slate-500">ملخص التعليم</p>
                                <p>{profile?.education_summary || 'غير متاح'}</p>
                            </div>
                            <div>
                                <p className="font-bold text-slate-500">ملخص الخبرة</p>
                                <p>{profile?.experience_summary || 'غير متاح'}</p>
                            </div>
                            <div>
                                <p className="font-bold text-slate-500">توصيات الذكاء الاصطناعي</p>
                                <p>{profile?.ai_recommendations?.length ? profile.ai_recommendations.join('، ') : 'لا توجد توصيات.'}</p>
                            </div>
                        </div>
                    </Card>
                </div>
            </div>
        </DashboardLayout>
    );
}
