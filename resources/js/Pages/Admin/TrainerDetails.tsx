import { Link, router } from '@inertiajs/react';
import { Badge, Card, DashboardLayout, StatCard, icons } from '@/Components/Madarat';
import { arabicLabel } from '@/lib/arabicLabels';
import { useState } from 'react';

export default function TrainerDetails({ provider, courses, stats }: any) {
    const [deleting, setDeleting] = useState(false);

    const deleteProvider = () => {
        if (deleting) {
            return;
        }

        if (!confirm(`هل أنت متأكد من حذف حساب مزود التدريب "${provider.display_name}"؟ سيُحذف الحساب وجميع الدورات والتسجيلات والبيانات المرتبطة به نهائيًا، ولا يمكن التراجع عن هذا الإجراء.`)) {
            return;
        }

        setDeleting(true);
        router.delete(route('admin.trainers.destroy', { provider: provider.id }), {
            preserveScroll: true,
            onFinish: () => setDeleting(false),
        });
    };

    return (
        <DashboardLayout title="تفاصيل مزود التدريب">
            <div className="mb-4 flex flex-wrap items-center gap-3">
                <Link href="/admin/trainers" className="text-sm font-black text-madarat-blue hover:underline">العودة إلى قائمة المزودين</Link>
                <Link href={`/admin/training/providers/${provider.id}`} className="rounded-lg bg-madarat-blue px-3 py-1.5 text-xs font-black text-white hover:bg-madarat-navy">فتح صفحة مراجعة الحساب</Link>
                <button
                    type="button"
                    onClick={deleteProvider}
                    disabled={deleting}
                    className="rounded-lg bg-red-600 px-3 py-1.5 text-xs font-black text-white hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    {deleting ? 'جاري الحذف...' : 'حذف حساب مزود التدريب'}
                </button>
            </div>

            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <StatCard label="إجمالي الدورات" value={stats.courses} icon={icons.BookOpen} />
                <StatCard label="دورات منشورة" value={stats.published} icon={icons.CheckCircle2} />
                <StatCard label="قيد المراجعة" value={stats.pending} icon={icons.Clock} />
                <StatCard label="إجمالي التسجيلات" value={stats.enrollments} icon={icons.UserRound} />
            </div>

            <div className="mt-6 grid gap-5 lg:grid-cols-[1fr_340px]">
                <Card>
                    <h2 className="text-2xl font-black text-madarat-navy">{provider.display_name}</h2>
                    <p className="mt-2 text-sm text-slate-500">صاحب الحساب: {provider.user?.name || 'غير محدد'} · {provider.user?.email || 'غير محدد'}</p>
                    <dl className="mt-5 grid gap-4 md:grid-cols-2">
                        <div><dt className="text-sm font-bold text-slate-500">نوع المزود</dt><dd className="mt-1 font-black">{arabicLabel('providerType', provider.provider_type)}</dd></div>
                        <div><dt className="text-sm font-bold text-slate-500">حالة التوثيق</dt><dd className="mt-1 font-black">{arabicLabel('providerStatus', provider.verification_status)}</dd></div>
                        <div><dt className="text-sm font-bold text-slate-500">البريد</dt><dd className="mt-1 font-black">{provider.email || 'غير محدد'}</dd></div>
                        <div><dt className="text-sm font-bold text-slate-500">الهاتف</dt><dd className="mt-1 font-black">{provider.phone || 'غير محدد'}</dd></div>
                        <div><dt className="text-sm font-bold text-slate-500">المدينة</dt><dd className="mt-1 font-black">{provider.city || 'غير محددة'}</dd></div>
                        <div><dt className="text-sm font-bold text-slate-500">الخبرة</dt><dd className="mt-1 font-black">{provider.years_of_experience || 'غير محددة'} سنة</dd></div>
                    </dl>
                    <div className="mt-4 rounded-lg bg-slate-50 p-4">
                        <p className="text-sm font-bold text-slate-500">الوصف</p>
                        <p className="mt-2 whitespace-pre-wrap leading-8 text-slate-700">{provider.description || 'لا يوجد وصف.'}</p>
                    </div>
                    <div className="mt-4 rounded-lg bg-slate-50 p-4">
                        <p className="text-sm font-bold text-slate-500">التخصصات</p>
                        <p className="mt-2 leading-7 text-slate-700">{provider.specializations?.length ? provider.specializations.join('، ') : 'غير محددة'}</p>
                    </div>
                </Card>

                <Card>
                    <h2 className="font-black text-madarat-navy">بيانات التوثيق</h2>
                    <div className="mt-3 space-y-2 text-sm text-slate-600">
                        <p>تاريخ طلب التوثيق: {provider.verification_requested_at || 'غير متاح'}</p>
                        <p>تاريخ التوثيق: {provider.verified_at || 'غير متاح'}</p>
                        <p>سبب الرفض: {provider.rejection_reason || 'لا يوجد'}</p>
                    </div>
                    {provider.verification_status === 'pending' && <p className="mt-4 rounded-lg border border-amber-100 bg-amber-50 p-3 text-xs leading-6 text-amber-800">هذا الحساب بانتظار مراجعة الإدارة. استخدم صفحة المراجعة لاتخاذ القرار.</p>}
                </Card>
            </div>

            <Card className="mt-6">
                <h2 className="font-black text-madarat-navy">دورات مزود التدريب</h2>
                <div className="mt-4 space-y-3">
                    {courses.data.length ? courses.data.map((course: any) => (
                        <div key={course.id} className="rounded-lg bg-slate-50 p-4">
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p className="font-black text-madarat-navy">{course.title}</p>
                                    <p className="mt-1 text-sm text-slate-500">{course.city || 'عن بعد'} · {course.start_date || 'بدون تاريخ بداية'}</p>
                                    <p className="mt-1 text-xs text-slate-500">تسجيلات: {course.enrollments_count} · تفاعلات: {course.feedback_count}</p>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Badge tone={course.status === 'published' ? 'green' : 'gray'}>{arabicLabel('courseStatus', course.status)}</Badge>
                                    {course.status === 'pending_review' && <Link href={`/admin/training/courses/${course.id}/review`} className="rounded-lg bg-madarat-blue px-3 py-1.5 text-xs font-black text-white hover:bg-madarat-navy">مراجعة</Link>}
                                </div>
                            </div>
                        </div>
                    )) : <p className="text-sm text-slate-500">لا توجد دورات حالياً.</p>}
                </div>
                <div className="mt-4 flex items-center justify-between">
                    <p className="text-xs text-slate-500">صفحة {courses.current_page} من {courses.last_page}</p>
                    <div className="flex gap-2">
                        {courses.prev_page_url && <Link href={courses.prev_page_url} className="rounded-lg bg-white px-3 py-1.5 text-sm font-bold text-slate-700 ring-1 ring-slate-200">السابق</Link>}
                        {courses.next_page_url && <Link href={courses.next_page_url} className="rounded-lg bg-white px-3 py-1.5 text-sm font-bold text-slate-700 ring-1 ring-slate-200">التالي</Link>}
                    </div>
                </div>
            </Card>
        </DashboardLayout>
    );
}
