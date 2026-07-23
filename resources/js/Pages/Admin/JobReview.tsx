import { Head, Link, router } from '@inertiajs/react';
import { Badge, Card, CompanyVerificationBadge, DashboardLayout } from '@/Components/Madarat';
import { BriefcaseBusiness, Building2, CheckCircle2, MapPin, XCircle } from 'lucide-react';

const value = (text?: string | number | null) => text || 'غير محدد';

export default function JobReview({ job }: { job: any }) {
    const approve = () => {
        if (window.confirm(`هل أنت متأكد من الموافقة على وظيفة «${job.title}» ونشرها للباحثين عن عمل؟`)) {
            router.post(`/admin/jobs/${job.id}/approve`);
        }
    };

    const reject = () => {
        if (window.confirm(`هل أنت متأكد من رفض وظيفة «${job.title}» وإغلاقها؟ لا يمكن التراجع عن هذا الإجراء من هذه الصفحة.`)) {
            router.post(`/admin/jobs/${job.id}/reject`);
        }
    };

    return (
        <DashboardLayout title="مراجعة تفاصيل الوظيفة">
            <Head title={`مراجعة ${job.title}`} />

            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <Link href="/admin/jobs/pending" className="text-sm font-black text-madarat-blue hover:text-madarat-cyan">
                    العودة إلى الوظائف قيد المراجعة
                </Link>
                <Badge tone="cyan">بانتظار قرار الإدارة</Badge>
            </div>

            <div className="grid gap-5 lg:grid-cols-[1fr_320px]">
                <div className="space-y-5">
                    <Card>
                        <div className="flex items-start gap-3">
                            <span className="grid h-12 w-12 shrink-0 place-items-center rounded-full bg-madarat-sky text-madarat-blue">
                                <BriefcaseBusiness className="h-6 w-6" />
                            </span>
                            <div>
                                <h1 className="text-2xl font-black text-madarat-navy">{job.title}</h1>
                                <p className="mt-1 text-sm font-bold text-slate-500">{job.company_profile?.company_name}</p>
                            </div>
                        </div>

                        <h2 className="mt-6 font-black text-madarat-navy">الوصف الوظيفي</h2>
                        <p className="mt-3 whitespace-pre-line leading-8 text-slate-700">{job.description}</p>

                        <h2 className="mt-6 font-black text-madarat-navy">المسؤوليات</h2>
                        {job.responsibilities?.length ? (
                            <ul className="mt-3 list-inside list-disc space-y-2 text-slate-700">
                                {job.responsibilities.map((item: string) => <li key={item}>{item}</li>)}
                            </ul>
                        ) : <p className="mt-2 text-slate-500">لم تتم إضافة مسؤوليات.</p>}

                        <h2 className="mt-6 font-black text-madarat-navy">المهارات المطلوبة</h2>
                        <div className="mt-3 flex flex-wrap gap-2">
                            {job.required_skills?.length
                                ? job.required_skills.map((skill: string) => <Badge key={skill} tone="cyan">{skill}</Badge>)
                                : <span className="text-slate-500">لم تتم إضافة مهارات.</span>}
                        </div>
                    </Card>

                    <Card>
                        <h2 className="font-black text-madarat-navy">بيانات الوظيفة</h2>
                        <dl className="mt-4 grid gap-4 sm:grid-cols-2">
                            <div><dt className="text-sm font-bold text-slate-500">الموقع</dt><dd className="mt-1 font-black">{value(job.location)}</dd></div>
                            <div><dt className="text-sm font-bold text-slate-500">نوع الوظيفة</dt><dd className="mt-1 font-black">{value(job.job_type)}</dd></div>
                            <div><dt className="text-sm font-bold text-slate-500">نوع العقد</dt><dd className="mt-1 font-black">{value(job.contract_type)}</dd></div>
                            <div><dt className="text-sm font-bold text-slate-500">مستوى الخبرة</dt><dd className="mt-1 font-black">{value(job.experience_level)}</dd></div>
                            <div className="sm:col-span-2">
                                <dt className="text-sm font-bold text-slate-500">الراتب</dt>
                                <dd className="mt-1 font-black">
                                    {job.salary_min || job.salary_max ? `${value(job.salary_min)} – ${value(job.salary_max)}` : 'غير محدد'}
                                </dd>
                            </div>
                        </dl>
                    </Card>
                </div>

                <div className="space-y-5">
                    <Card>
                        <div className="flex items-center gap-2">
                            <Building2 className="h-5 w-5 text-madarat-cyan" />
                            <h2 className="font-black text-madarat-navy">بيانات الشركة</h2>
                        </div>
                        <div className="mt-4 flex items-center gap-2">
                            <strong>{job.company_profile?.company_name}</strong>
                            <CompanyVerificationBadge status={job.company_profile?.verification_status} />
                        </div>
                        <p className="mt-3 text-sm leading-7 text-slate-600">{value(job.company_profile?.description)}</p>
                        <div className="mt-3 space-y-2 text-sm text-slate-600">
                            <p><MapPin className="me-1 inline h-4 w-4" />{value(job.company_profile?.headquarters)}</p>
                            <p>القطاع: {value(job.company_profile?.industry)}</p>
                            <p>صاحب الحساب: {value(job.company_profile?.user?.name)}</p>
                        </div>
                    </Card>

                    <Card className="border-amber-200 bg-amber-50/70">
                        <h2 className="font-black text-madarat-navy">اتخاذ القرار</h2>
                        <p className="mt-2 text-sm font-bold leading-7 text-slate-600">
                            تأكد من قراءة جميع التفاصيل أعلاه. ستظهر رسالة تأكيد قبل تنفيذ القرار.
                        </p>
                        <div className="mt-4 grid gap-3">
                            <button onClick={approve} className="inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 py-3 font-black text-white hover:bg-emerald-700">
                                <CheckCircle2 className="h-5 w-5" />
                                الموافقة ونشر الوظيفة
                            </button>
                            <button onClick={reject} className="inline-flex items-center justify-center gap-2 rounded-lg bg-red-600 px-4 py-3 font-black text-white hover:bg-red-700">
                                <XCircle className="h-5 w-5" />
                                رفض وإغلاق الوظيفة
                            </button>
                        </div>
                    </Card>
                </div>
            </div>
        </DashboardLayout>
    );
}
