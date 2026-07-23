import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Badge, Card, DashboardLayout } from '@/Components/Madarat';
import DecisionModal from '@/Components/DecisionModal';
import { arabicLabel } from '@/lib/arabicLabels';

export default function Show({ course, feedback, canRegister, enrollment, remainingSeats }: any) {
    const [confirming, setConfirming] = useState(false);
    const [processing, setProcessing] = useState(false);
    const action = (actionName: string) => router.post(`/courses/${course.id}/feedback`, { action: actionName });
    const register = () => {
        setProcessing(true);
        router.post(`/courses/${course.id}/register`, {}, {
            onFinish: () => { setProcessing(false); setConfirming(false); },
        });
    };

    return <DashboardLayout title={course.title}>
        <div className="grid gap-5 lg:grid-cols-[1fr_320px]">
            <Card>
                <div className="flex flex-wrap gap-2">
                    <Badge tone={course.provider.verification_status === 'verified' ? 'green' : 'cyan'}>{course.provider.display_name}</Badge>
                    <Badge tone="cyan">{arabicLabel('difficulty', course.difficulty_level)}</Badge>
                    <Badge tone="cyan">{arabicLabel('delivery', course.delivery_method)}</Badge>
                </div>
                <p className="mt-5 whitespace-pre-line leading-8">{course.description}</p>
                <h3 className="mt-5 font-black">المهارات التي ستتعلمها</h3>
                <div className="mt-2 flex flex-wrap gap-2">{course.skills_taught?.map((skill: string) => <Badge key={skill} tone="cyan">{skill}</Badge>)}</div>
                <dl className="mt-5 grid gap-3 md:grid-cols-2">
                    <div>المدة: {course.duration_value} {arabicLabel('duration', course.duration_unit)}</div>
                    <div>السعر: {course.price ? `${course.price} ${arabicLabel('currency', course.currency)}` : 'مجانية'}</div>
                    <div>البداية: {course.start_date || 'مرنة'}</div>
                    <div>الشهادة: {course.certificate_available ? 'متاحة' : 'غير متاحة'}</div>
                    <div>آخر موعد للتسجيل: {course.registration_deadline || 'غير محدد'}</div>
                    <div>المقاعد المتبقية: {remainingSeats === null ? 'غير محدودة' : remainingSeats}</div>
                </dl>
                {canRegister && <div className="mt-6 flex flex-wrap gap-2">
                    <button onClick={() => action(feedback?.saved ? 'unsave' : 'save')} className="rounded border px-3 py-2">{feedback?.saved ? 'إزالة الحفظ' : 'حفظ الدورة'}</button>
                    <button onClick={() => action('interest')} className="rounded border px-3 py-2">مهتم</button>
                    <button onClick={() => action('complete')} className="rounded border px-3 py-2">أكملت الدورة</button>
                    <button onClick={() => action('already_know')} className="rounded border px-3 py-2">أعرف هذه المهارات</button>
                    <button onClick={() => action('dismiss')} className="rounded border px-3 py-2">غير مهتم</button>
                </div>}
            </Card>
            <Card className="h-fit border-cyan-200 bg-madarat-sky/60">
                <h2 className="text-lg font-black text-madarat-navy">التسجيل في الدورة</h2>
                {enrollment ? <>
                    <Badge tone="green">مسجّل في الدورة</Badge>
                    <p className="mt-3 text-sm leading-7 text-slate-600">تم تسجيل طلبك ويمكنك متابعة دوراتك من صفحة التسجيلات.</p>
                    <Link href="/seeker/courses/registrations" className="mt-4 inline-flex rounded-lg bg-white px-4 py-2 font-black text-madarat-blue ring-1 ring-cyan-100">عرض دوراتي</Link>
                </> : canRegister ? <>
                    <p className="mt-2 text-sm leading-7 text-slate-600">اضغط على الزر لإتمام تسجيلك في هذه الدورة.</p>
                    <button disabled={remainingSeats === 0} onClick={() => setConfirming(true)} className="mt-4 w-full rounded-lg bg-madarat-blue px-5 py-3 font-black text-white hover:bg-madarat-navy disabled:cursor-not-allowed disabled:bg-slate-400">
                        {remainingSeats === 0 ? 'اكتمل العدد' : 'التقديم على الدورة'}
                    </button>
                </> : <>
                    <p className="mt-2 text-sm leading-7 text-slate-600">سجّل الدخول بحساب باحث عن عمل للتقديم على الدورة.</p>
                    <Link href="/login" className="mt-4 inline-flex rounded-lg bg-madarat-blue px-5 py-3 font-black text-white">تسجيل الدخول</Link>
                </>}
            </Card>
        </div>
        <DecisionModal show={confirming} title="تأكيد التسجيل في الدورة"
            message={`هل تريد التسجيل في دورة «${course.title}»؟`}
            confirmLabel="نعم، سجّلني في الدورة" processing={processing}
            onClose={() => setConfirming(false)} onConfirm={register} />
    </DashboardLayout>;
}
