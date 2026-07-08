import { FormEvent, ReactNode, useMemo, useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import axios from 'axios';
import { BriefcaseBusiness, CheckCircle2, FileText, ListChecks, MapPin, Save, Sparkles, WalletCards } from 'lucide-react';
import { Button, Card, DashboardLayout } from '@/Components/Madarat';

type JobFormData = {
    title: string;
    description: string;
    required_skills: string;
    responsibilities: string;
    location: string;
    job_type: string;
    contract_type: string;
    experience_level: string;
    salary_min: string;
    salary_max: string;
    status: 'draft' | 'published' | 'pending_review' | 'closed';
};

const fieldClass = 'mt-1 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-madarat-cyan focus:ring-madarat-cyan';
const options = {
    jobTypes: ['دوام كامل', 'دوام جزئي', 'عن بعد', 'هجين', 'تعاقد'],
    contractTypes: ['عقد سنوي', 'عقد مؤقت', 'مشروع مستقل', 'تدريب', 'غير محدد'],
    levels: ['مبتدئ', 'متوسط', 'خبير', 'إدارة'],
};

function Field({ label, error, children, hint }: { label: string; error?: string; children: ReactNode; hint?: string }) {
    return (
        <label className="block">
            <span className="text-sm font-bold text-madarat-navy">{label}</span>
            {children}
            {hint && <span className="mt-1 block text-xs leading-5 text-slate-500">{hint}</span>}
            {error && <span className="mt-1 block text-xs font-bold text-red-600">{error}</span>}
        </label>
    );
}

function SectionTitle({ icon: Icon, title }: { icon: any; title: string }) {
    return (
        <div className="mb-4 flex items-center gap-2 border-b border-cyan-100 pb-3">
            <span className="grid h-9 w-9 place-items-center rounded-full bg-madarat-sky text-madarat-blue">
                <Icon className="h-5 w-5" />
            </span>
            <h2 className="font-black text-madarat-navy">{title}</h2>
        </div>
    );
}

export default function JobForm({ job, companyVerified = false }: any) {
    const isEdit = Boolean(job);
    const [generated, setGenerated] = useState(false);
    const [generating, setGenerating] = useState(false);
    const [generateError, setGenerateError] = useState('');

    const { data, setData, processing, errors } = useForm<JobFormData>({
        title: job?.title || '',
        description: job?.description || '',
        required_skills: (job?.required_skills || []).join('\n'),
        responsibilities: (job?.responsibilities || []).join('\n'),
        location: job?.location || '',
        job_type: job?.job_type || 'دوام كامل',
        contract_type: job?.contract_type || 'عقد سنوي',
        experience_level: job?.experience_level || 'متوسط',
        salary_min: job?.salary_min || '',
        salary_max: job?.salary_max || '',
        status: job?.status || 'published',
    });

    const skillCount = useMemo(() => splitItems(data.required_skills).length, [data.required_skills]);
    const responsibilityCount = useMemo(() => splitItems(data.responsibilities).length, [data.responsibilities]);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        submitWithStatus('published');
    };

    const submitWithStatus = (status: JobFormData['status']) => {
        const payload = { ...normalizePayload(data), status };

        if (isEdit) {
            router.put(`/employer/jobs/${job.id}`, payload);
        } else {
            router.post('/employer/jobs', payload);
        }
    };

    const generate = () => {
        setGenerating(true);
        setGenerateError('');

        axios.post('/employer/jobs/generate-description', normalizePayload(data))
            .then((response) => {
                setGenerated(true);
                setData('description', response.data.description || '');
                setData('responsibilities', (response.data.responsibilities || []).join('\n'));
                setData('required_skills', (response.data.suggested_skills || []).join('\n'));
            })
            .catch(() => setGenerateError('تعذر توليد الوصف الآن. تأكد من تعبئة المسمى الوظيفي وحاول مرة أخرى.'))
            .finally(() => setGenerating(false));
    };

    return (
        <DashboardLayout title={isEdit ? 'تعديل وظيفة' : 'نشر وظيفة جديدة'}>
            <form onSubmit={submit} className="grid gap-5 lg:grid-cols-[1fr_320px]">
                <Card className="space-y-8">
                    <section>
                        <SectionTitle icon={BriefcaseBusiness} title="المعلومات الأساسية" />
                        <div className="grid gap-4 md:grid-cols-2">
                            <Field label="المسمى الوظيفي" error={errors.title}>
                                <input value={data.title} onChange={(e) => setData('title', e.target.value)} placeholder="مثال: مطور تطبيقات Flutter" className={fieldClass} />
                            </Field>
                            <Field label="الموقع" error={errors.location}>
                                <input value={data.location} onChange={(e) => setData('location', e.target.value)} placeholder="طرابلس، عن بعد، أو هجين" className={fieldClass} />
                            </Field>
                            <Field label="نمط العمل" error={errors.job_type}>
                                <select value={data.job_type} onChange={(e) => setData('job_type', e.target.value)} className={fieldClass}>
                                    {options.jobTypes.map((item) => <option key={item} value={item}>{item}</option>)}
                                </select>
                            </Field>
                            <Field label="نوع العقد" error={errors.contract_type}>
                                <select value={data.contract_type} onChange={(e) => setData('contract_type', e.target.value)} className={fieldClass}>
                                    {options.contractTypes.map((item) => <option key={item} value={item}>{item}</option>)}
                                </select>
                            </Field>
                        </div>
                    </section>

                    <section>
                        <SectionTitle icon={FileText} title="الوصف الوظيفي" />
                        <Field label="الوصف" error={errors.description} hint="اكتب نبذة واضحة عن الدور، الفريق، وأهم النتائج المتوقعة.">
                            <textarea value={data.description} onChange={(e) => setData('description', e.target.value)} placeholder="نبحث عن شخص قادر على..." className={fieldClass} rows={8} />
                        </Field>
                        <div className="mt-3 flex flex-wrap items-center gap-2">
                            <Button type="button" onClick={generate} disabled={generating || !data.title} className="bg-madarat-cyan hover:bg-madarat-blue">
                                <Sparkles className="h-4 w-4" />
                                {generating ? 'جار التوليد...' : 'توليد وصف ذكي'}
                            </Button>
                            {generated && <span className="text-sm font-bold text-emerald-700">تم تحديث الوصف والمهارات المقترحة.</span>}
                            {generateError && <span className="text-sm font-bold text-red-600">{generateError}</span>}
                        </div>
                    </section>

                    <section>
                        <SectionTitle icon={ListChecks} title="المهارات والمسؤوليات" />
                        <div className="grid gap-4 md:grid-cols-2">
                            <Field label="المهارات المطلوبة" error={errors.required_skills} hint="اكتب كل مهارة في سطر، أو افصل بينها بفواصل.">
                                <textarea value={data.required_skills} onChange={(e) => setData('required_skills', e.target.value)} placeholder={"Flutter\nDart\nFirebase\nREST APIs"} className={fieldClass} rows={7} />
                            </Field>
                            <Field label="المسؤوليات" error={errors.responsibilities} hint="اكتب كل مسؤولية في سطر مستقل لتظهر بشكل مرتب في صفحة الوظيفة.">
                                <textarea value={data.responsibilities} onChange={(e) => setData('responsibilities', e.target.value)} placeholder={"تطوير تطبيقات عالية الجودة\nاختبار وتحسين الأداء\nالتعاون مع فريق التصميم"} className={fieldClass} rows={7} />
                            </Field>
                        </div>
                    </section>

                    <section>
                        <SectionTitle icon={WalletCards} title="المستوى والراتب" />
                        <div className="grid gap-4 md:grid-cols-3">
                            <Field label="مستوى الخبرة" error={errors.experience_level}>
                                <select value={data.experience_level} onChange={(e) => setData('experience_level', e.target.value)} className={fieldClass}>
                                    {options.levels.map((item) => <option key={item} value={item}>{item}</option>)}
                                </select>
                            </Field>
                            <Field label="الراتب الأدنى" error={errors.salary_min}>
                                <input type="number" min="0" value={data.salary_min} onChange={(e) => setData('salary_min', e.target.value)} placeholder="اختياري" className={fieldClass} />
                            </Field>
                            <Field label="الراتب الأعلى" error={errors.salary_max}>
                                <input type="number" min="0" value={data.salary_max} onChange={(e) => setData('salary_max', e.target.value)} placeholder="اختياري" className={fieldClass} />
                            </Field>
                        </div>
                    </section>
                </Card>

                <aside className="space-y-4">
                    <Card className="sticky top-24">
                        <SectionTitle icon={CheckCircle2} title="النشر" />
                        <div className="space-y-3">
                            <div className="rounded-lg bg-slate-50 p-4 text-sm leading-7 text-slate-600">
                                {companyVerified
                                    ? 'شركتك موثقة، سيتم نشر الوظيفة مباشرة عند الإرسال.'
                                    : 'شركتك غير موثقة بعد، سيتم إرسال الوظيفة للمراجعة قبل النشر.'}
                                <span className="mt-2 block font-bold text-madarat-navy">يمكنك حفظ الوظيفة كمسودة في أي وقت.</span>
                            </div>
                            {errors.status && <p className="text-xs font-bold text-red-600">{errors.status}</p>}
                        </div>

                        <div className="mt-5 rounded-lg bg-slate-50 p-4 text-sm leading-7 text-slate-600">
                            <div className="flex items-center gap-2 font-bold text-madarat-navy"><MapPin className="h-4 w-4 text-madarat-cyan" /> {data.location || 'الموقع غير محدد'}</div>
                            <p className="mt-2">المهارات: {skillCount}</p>
                            <p>المسؤوليات: {responsibilityCount}</p>
                            <p>نوع العمل: {data.job_type}</p>
                            <p>الخبرة: {data.experience_level}</p>
                        </div>

                        <Button type="button" disabled={processing} onClick={() => submitWithStatus('draft')} className="mt-5 w-full bg-slate-600 hover:bg-slate-700">
                            <Save className="h-4 w-4" />
                            {processing ? 'جار الحفظ...' : 'حفظ كمسودة'}
                        </Button>

                        <Button disabled={processing} className="mt-3 w-full">
                            <Save className="h-4 w-4" />
                            {processing ? 'جار الحفظ...' : companyVerified ? 'نشر الوظيفة' : 'إرسال للمراجعة'}
                        </Button>
                    </Card>
                </aside>
            </form>
        </DashboardLayout>
    );
}

function splitItems(value: string): string[] {
    return value.split(/[\n,]+/).map((item) => item.trim()).filter(Boolean);
}

function normalizePayload(data: JobFormData) {
    return {
        ...data,
        required_skills: splitItems(data.required_skills),
        responsibilities: splitItems(data.responsibilities),
        salary_min: data.salary_min === '' ? '' : Number(data.salary_min),
        salary_max: data.salary_max === '' ? '' : Number(data.salary_max),
    };
}
