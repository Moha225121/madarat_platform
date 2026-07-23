import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { BookOpen, CheckCircle2, XCircle } from 'lucide-react';
import { Badge, Card, DashboardLayout } from '@/Components/Madarat';
import DecisionModal from '@/Components/DecisionModal';
import { arabicLabel } from '@/lib/arabicLabels';

const value = (text?: string | number | null) => text || 'غير محدد';
const list = (items?: string[] | null) => items?.length ? items.join('، ') : 'غير محدد';

export default function TrainingCourseReview({ course }: { course: any }) {
    const [decision, setDecision] = useState<'approve' | 'reject' | null>(null);
    const [reason, setReason] = useState('');
    const [processing, setProcessing] = useState(false);
    const submit = () => {
        if (!decision) return;
        setProcessing(true);
        router.post(`/admin/training/courses/${course.id}/${decision}`, decision === 'reject' ? { reason: reason.trim() } : {}, {
            onFinish: () => setProcessing(false),
        });
    };

    return <DashboardLayout title="مراجعة تفاصيل الدورة">
        <Head title={`مراجعة ${course.title}`} />
        <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
            <Link href="/admin/training" className="text-sm font-black text-madarat-blue hover:text-madarat-cyan">العودة إلى إدارة التدريب</Link>
            <Badge tone="cyan">بانتظار قرار الإدارة</Badge>
        </div>
        <div className="grid gap-5 lg:grid-cols-[1fr_320px]">
            <div className="space-y-5">
                <Card>
                    <div className="flex items-start gap-3">
                        <span className="grid h-12 w-12 shrink-0 place-items-center rounded-full bg-madarat-sky text-madarat-blue"><BookOpen /></span>
                        <div><h1 className="text-2xl font-black text-madarat-navy">{course.title}</h1><p className="mt-1 text-slate-500">{course.provider?.display_name}</p></div>
                    </div>
                    <h2 className="mt-6 font-black text-madarat-navy">وصف الدورة</h2>
                    <p className="mt-3 whitespace-pre-line leading-8 text-slate-700">{value(course.description)}</p>
                    <h2 className="mt-6 font-black text-madarat-navy">نبذة مختصرة</h2>
                    <p className="mt-2 leading-7 text-slate-700">{value(course.short_description)}</p>
                    <dl className="mt-6 grid gap-4 sm:grid-cols-2">
                        <div><dt className="text-sm font-bold text-slate-500">نواتج التعلم</dt><dd className="mt-1">{list(course.learning_outcomes)}</dd></div>
                        <div><dt className="text-sm font-bold text-slate-500">المهارات المقدمة</dt><dd className="mt-1">{list(course.skills_taught)}</dd></div>
                        <div><dt className="text-sm font-bold text-slate-500">الفئة المستهدفة</dt><dd className="mt-1">{value(course.target_audience)}</dd></div>
                        <div><dt className="text-sm font-bold text-slate-500">المتطلبات السابقة</dt><dd className="mt-1">{list(course.prerequisites)}</dd></div>
                    </dl>
                </Card>
                <Card>
                    <h2 className="font-black text-madarat-navy">بيانات الدورة</h2>
                    <dl className="mt-4 grid gap-4 sm:grid-cols-2">
                        <div><dt className="text-sm text-slate-500">المستوى</dt><dd className="font-black">{arabicLabel('difficulty', course.difficulty_level)}</dd></div>
                        <div><dt className="text-sm text-slate-500">طريقة التقديم</dt><dd className="font-black">{arabicLabel('delivery', course.delivery_method)}</dd></div>
                        <div><dt className="text-sm text-slate-500">المدة</dt><dd className="font-black">{value(course.duration_value)} {course.duration_unit ? arabicLabel('duration', course.duration_unit) : ''}</dd></div>
                        <div><dt className="text-sm text-slate-500">الموقع</dt><dd className="font-black">{course.is_remote ? 'عن بُعد' : `${value(course.city)} - ${value(course.location)}`}</dd></div>
                        <div><dt className="text-sm text-slate-500">السعر</dt><dd className="font-black">{course.price ? `${course.price} ${arabicLabel('currency', course.currency)}` : 'مجانية'}</dd></div>
                        <div><dt className="text-sm text-slate-500">السعة</dt><dd className="font-black">{value(course.capacity)}</dd></div>
                        <div><dt className="text-sm text-slate-500">تاريخ البداية</dt><dd className="font-black">{value(course.start_date)}</dd></div>
                        <div><dt className="text-sm text-slate-500">شهادة إتمام</dt><dd className="font-black">{course.certificate_available ? 'متاحة' : 'غير متاحة'}</dd></div>
                    </dl>
                </Card>
            </div>
            <div className="space-y-5">
                <Card>
                    <h2 className="font-black text-madarat-navy">مقدم التدريب</h2>
                    <p className="mt-3 font-black">{course.provider?.display_name}</p>
                    <p className="mt-2 text-sm text-slate-600">{arabicLabel('providerType', course.provider?.provider_type)} · {arabicLabel('providerStatus', course.provider?.verification_status)}</p>
                    <p className="mt-2 text-sm text-slate-600">صاحب الحساب: {value(course.provider?.user?.name)}</p>
                    <p className="mt-2 text-sm text-slate-600">للتواصل: {value(course.contact_email || course.provider?.email)}</p>
                </Card>
                <Card className="border-amber-200 bg-amber-50/70">
                    <h2 className="font-black text-madarat-navy">اتخاذ القرار</h2>
                    <p className="mt-2 text-sm font-bold leading-7 text-slate-600">تأكد من قراءة جميع تفاصيل الدورة. ستظهر رسالة تأكيد في وسط الشاشة قبل تنفيذ القرار.</p>
                    <div className="mt-4 grid gap-3">
                        <button onClick={() => setDecision('approve')} className="inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 py-3 font-black text-white hover:bg-emerald-700"><CheckCircle2 />الموافقة ونشر الدورة</button>
                        <button onClick={() => setDecision('reject')} className="inline-flex items-center justify-center gap-2 rounded-lg bg-red-600 px-4 py-3 font-black text-white hover:bg-red-700"><XCircle />رفض الدورة مع السبب</button>
                    </div>
                </Card>
            </div>
        </div>
        <DecisionModal show={decision !== null} title={decision === 'approve' ? 'تأكيد نشر الدورة' : 'تأكيد رفض الدورة'}
            message={decision === 'approve' ? `هل أنت متأكد من الموافقة على دورة «${course.title}» ونشرها؟` : `هل أنت متأكد من رفض دورة «${course.title}»؟`}
            confirmLabel={decision === 'approve' ? 'نعم، انشر الدورة' : 'نعم، ارفض الدورة'} danger={decision === 'reject'}
            requireReason={decision === 'reject'} reason={reason} onReasonChange={setReason} processing={processing}
            onClose={() => { setDecision(null); setReason(''); }} onConfirm={submit} />
    </DashboardLayout>;
}
