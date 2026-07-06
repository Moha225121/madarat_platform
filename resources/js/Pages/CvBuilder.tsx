import { ChangeEvent, useState } from 'react';
import { Link } from '@inertiajs/react';
import {
    ArrowUpRight,
    CheckCircle2,
    Download,
    FileText,
    Layers,
    LayoutTemplate,
    LoaderCircle,
    Palette,
    PenLine,
    Sparkles,
    Upload,
    UserRound,
    WandSparkles,
    X,
} from 'lucide-react';
import { AppLayout, Badge, Card } from '@/Components/Madarat';

const templates = [
    {
        name: 'النمط التنفيذي',
        tone: 'للمناصب الإدارية والقيادية',
        accent: 'from-madarat-navy to-madarat-blue',
        primary: '#073B5F',
        secondary: '#0B4F7A',
        solid: 'bg-madarat-navy',
        text: 'text-madarat-navy',
        sections: ['نبذة قيادية', 'خبرات مختصرة', 'إنجازات رقمية'],
    },
    {
        name: 'النمط التقني',
        tone: 'للمطورين ومحللي البيانات',
        accent: 'from-cyan-500 to-madarat-blue',
        primary: '#0B4F7A',
        secondary: '#18B7C8',
        solid: 'bg-madarat-blue',
        text: 'text-madarat-blue',
        sections: ['مشاريع', 'مهارات تقنية', 'روابط مهنية'],
    },
    {
        name: 'النمط الحديث',
        tone: 'للخريجين والباحثين عن بداية قوية',
        accent: 'from-slate-700 to-madarat-cyan',
        primary: '#334155',
        secondary: '#18B7C8',
        solid: 'bg-madarat-cyan',
        text: 'text-cyan-700',
        sections: ['تعليم', 'تدريب', 'مهارات قابلة للنقل'],
    },
] as const;

const aiFeatures = [
    ['صياغة الملخص المهني', 'يقترح الذكاء الاصطناعي ملخصا واضحا حسب المجال ومستوى الخبرة.', WandSparkles],
    ['تحسين نقاط الخبرة', 'تحويل المهام اليومية إلى إنجازات قابلة للقياس وجاهزة للقراءة.', PenLine],
    ['اختيار القالب الأنسب', 'ترشيح إطار السيرة حسب الوظيفة المستهدفة وطبيعة الخبرة.', LayoutTemplate],
    ['تنزيل PDF', 'افتح الإطار وشاهد السيرة كاملة ثم نزّلها كملف PDF.', Download],
] as const;

type Template = (typeof templates)[number];

type ResumeData = {
    name: string;
    headline: string;
    email: string;
    phone: string;
    location: string;
    photo: string;
    photoX: string;
    photoY: string;
    photoZoom: string;
    summary: string;
    skills: string;
    experience: string;
    education: string;
    languages: string;
};

type ResumeColors = {
    primary: string;
    secondary: string;
};

const initialResume: ResumeData = {
    name: 'أحمد محمد',
    headline: 'أخصائي تسويق رقمي مدعوم بتحليل البيانات',
    email: 'ahmed@example.com',
    phone: '+218 91 000 0000',
    location: 'طرابلس، ليبيا',
    photo: '',
    photoX: '50',
    photoY: '50',
    photoZoom: '100',
    summary: 'محترف يمتلك خبرة في بناء الحملات الرقمية، تحليل الأداء، وتحويل البيانات إلى قرارات عملية. أبحث عن فرصة أساهم فيها في نمو العلامة التجارية وتحسين تجربة العملاء.',
    skills: 'إدارة الحملات الرقمية\nتحليل البيانات\nكتابة المحتوى\nتحسين محركات البحث\nإدارة فرق العمل',
    experience: 'أخصائي تسويق رقمي - شركة مدارات\n- رفعت معدل الوصول للحملات بنسبة 38% خلال 6 أشهر.\n- طورت تقارير أداء أسبوعية ساعدت الإدارة على تحسين الميزانية.\n- نسقت مع فرق التصميم والمبيعات لإطلاق حملات متكاملة.',
    education: 'بكالوريوس إدارة أعمال - جامعة طرابلس\nدورات متقدمة في التسويق الرقمي وتحليل البيانات',
    languages: 'العربية: ممتاز\nالإنجليزية: جيد جدا',
};

function lines(value: string) {
    return value.split('\n').map((line) => line.trim()).filter(Boolean);
}

function Field({ label, value, onChange, textarea = false }: { label: string; value: string; onChange: (value: string) => void; textarea?: boolean }) {
    return (
        <label className="block">
            <span className="text-xs font-black text-slate-500">{label}</span>
            {textarea ? (
                <textarea
                    value={value}
                    onChange={(event) => onChange(event.target.value)}
                    rows={5}
                    className="mt-1 w-full rounded-lg border-slate-200 text-sm leading-6 focus:border-madarat-cyan focus:ring-madarat-cyan"
                />
            ) : (
                <input
                    value={value}
                    onChange={(event) => onChange(event.target.value)}
                    className="mt-1 w-full rounded-lg border-slate-200 text-sm focus:border-madarat-cyan focus:ring-madarat-cyan"
                />
            )}
        </label>
    );
}

function Section({ title, children, color }: { title: string; children: React.ReactNode; color: string }) {
    return (
        <section>
            <h3 className="border-b border-slate-200 pb-1 text-sm font-black" style={{ color }}>{title}</h3>
            <div className="mt-3 text-sm leading-7 text-slate-700">{children}</div>
        </section>
    );
}

function ResumeDocument({ data, template, colors }: { data: ResumeData; template: Template; colors: ResumeColors }) {
    return (
        <article className="cv-print-area mx-auto min-h-[1120px] w-full max-w-[794px] bg-white text-right text-slate-800 shadow-2xl shadow-slate-900/15 ring-1 ring-slate-200">
            <header className="p-8 text-white" style={{ background: `linear-gradient(270deg, ${colors.primary}, ${colors.secondary})` }}>
                <div className="flex items-start justify-between gap-6">
                    <div>
                        <h2 className="text-4xl font-black leading-tight">{data.name}</h2>
                        <p className="mt-2 text-lg font-bold text-cyan-50">{data.headline}</p>
                    </div>
                    <span className="grid h-24 w-24 shrink-0 place-items-center overflow-hidden rounded-lg bg-white/15 ring-1 ring-white/20">
                        {data.photo ? (
                            <img
                                src={data.photo}
                                alt={data.name}
                                className="h-full w-full object-cover"
                                style={{
                                    objectPosition: `${data.photoX}% ${data.photoY}%`,
                                    transform: `scale(${Number(data.photoZoom) / 100})`,
                                }}
                            />
                        ) : (
                            <UserRound className="h-10 w-10" />
                        )}
                    </span>
                </div>
                <div className="mt-6 flex flex-wrap gap-3 text-sm font-bold text-cyan-50">
                    <span>{data.email}</span>
                    <span>{data.phone}</span>
                    <span>{data.location}</span>
                </div>
            </header>

            <div className="grid gap-8 p-8 md:grid-cols-[.85fr_1.35fr]">
                <aside className="space-y-7">
                    <Section title="المهارات" color={colors.primary}>
                        <div className="flex flex-wrap gap-2">
                            {lines(data.skills).map((skill) => (
                                <span key={skill} className="rounded-full px-3 py-1 text-xs font-bold" style={{ backgroundColor: `${colors.secondary}18`, color: colors.primary }}>
                                    {skill}
                                </span>
                            ))}
                        </div>
                    </Section>
                    <Section title="التعليم" color={colors.primary}>
                        {lines(data.education).map((item) => <p key={item}>{item}</p>)}
                    </Section>
                    <Section title="اللغات" color={colors.primary}>
                        {lines(data.languages).map((item) => <p key={item}>{item}</p>)}
                    </Section>
                </aside>

                <main className="space-y-7">
                    <Section title="الملخص المهني" color={colors.primary}>
                        <p>{data.summary}</p>
                    </Section>
                    <Section title="الخبرات والإنجازات" color={colors.primary}>
                        <div className="space-y-2">
                            {lines(data.experience).map((item, index) => (
                                <p key={`${item}-${index}`} className={item.startsWith('-') ? 'pr-3' : 'font-black text-slate-900'}>
                                    {item}
                                </p>
                            ))}
                        </div>
                    </Section>
                </main>
            </div>
        </article>
    );
}

function TemplateCard({ template, index, onSelect }: { template: Template; index: number; onSelect: () => void }) {
    return (
        <Card className="group flex h-full flex-col overflow-hidden p-0 transition hover:-translate-y-1 hover:shadow-xl hover:shadow-madarat-blue/10">
            <div className={`h-2 bg-gradient-to-l ${template.accent}`} />
            <div className="p-5">
                <div className="flex items-start justify-between gap-3">
                    <div>
                        <h3 className="text-lg font-black text-madarat-navy">{template.name}</h3>
                        <p className="mt-1 text-sm font-bold text-slate-500">{template.tone}</p>
                    </div>
                    <span className="grid h-11 w-11 shrink-0 place-items-center rounded-lg bg-madarat-sky text-madarat-blue ring-1 ring-cyan-100">
                        <FileText className="h-5 w-5" />
                    </span>
                </div>

                <div className="mt-5 rounded-lg border border-slate-100 bg-slate-50 p-3">
                    <div className="flex items-center gap-3">
                        <span className={`h-12 w-12 rounded-lg bg-gradient-to-br ${template.accent}`} />
                        <div className="flex-1 space-y-2">
                            <div className="h-2.5 rounded-full bg-slate-300" />
                            <div className="h-2 rounded-full bg-slate-200" />
                            <div className="h-2 w-2/3 rounded-full bg-slate-200" />
                        </div>
                    </div>
                    <div className="mt-5 grid grid-cols-[.8fr_1.2fr] gap-3">
                        <div className="space-y-2">
                            <div className="h-2 rounded-full bg-madarat-cyan/50" />
                            <div className="h-2 rounded-full bg-slate-200" />
                            <div className="h-2 rounded-full bg-slate-200" />
                            <div className="h-2 w-3/4 rounded-full bg-slate-200" />
                        </div>
                        <div className="space-y-2">
                            <div className="h-2 rounded-full bg-slate-300" />
                            <div className="h-2 rounded-full bg-slate-200" />
                            <div className="h-2 rounded-full bg-slate-200" />
                            <div className="h-2 w-5/6 rounded-full bg-slate-200" />
                        </div>
                    </div>
                </div>

                <div className="mt-4 flex flex-wrap gap-2">
                    {template.sections.map((section) => <Badge key={section} tone={index === 1 ? 'cyan' : 'sky'}>{section}</Badge>)}
                </div>

                <button
                    type="button"
                    onClick={onSelect}
                    className="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-lg bg-madarat-blue px-4 py-2.5 text-sm font-bold text-white shadow-sm shadow-madarat-blue/20 transition group-hover:bg-madarat-navy"
                >
                    فتح الإطار
                    <ArrowUpRight className="h-4 w-4" />
                </button>
            </div>
        </Card>
    );
}

export default function CvBuilder() {
    const [selectedIndex, setSelectedIndex] = useState<number | null>(null);
    const [resume, setResume] = useState<ResumeData>(initialResume);
    const [colors, setColors] = useState<ResumeColors>({ primary: templates[0].primary, secondary: templates[0].secondary });
    const [summaryNotice, setSummaryNotice] = useState('');
    const [pdfLoading, setPdfLoading] = useState(false);
    const selectedTemplate = selectedIndex === null ? null : templates[selectedIndex];

    const openTemplate = (index: number) => {
        setSelectedIndex(index);
        setColors({ primary: templates[index].primary, secondary: templates[index].secondary });
    };

    const updateResume = (key: keyof ResumeData, value: string) => {
        setResume((current) => ({ ...current, [key]: value }));
        if (key === 'skills' || key === 'experience' || key === 'summary') {
            setSummaryNotice('');
        }
    };

    const updateColor = (key: keyof ResumeColors, value: string) => {
        setColors((current) => ({ ...current, [key]: value }));
    };

    const updatePhoto = (event: ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0];

        if (!file) {
            return;
        }

        const reader = new FileReader();
        reader.onload = () => {
            if (typeof reader.result === 'string') {
                updateResume('photo', reader.result);
            }
        };
        reader.readAsDataURL(file);
    };

    const generateAiSummary = () => {
        const skillLines = lines(resume.skills);
        const experienceLines = lines(resume.experience);

        if (!skillLines.length || !experienceLines.length) {
            setSummaryNotice('أدخل المهارات والخبرات أولا حتى يتمكن الذكاء الاصطناعي من إنشاء ملخص مهني مناسب.');
            return;
        }

        const role = resume.headline.trim() || 'محترف';
        const topSkills = skillLines.slice(0, 3).join('، ');
        const achievements = experienceLines
            .filter((item) => item.startsWith('-'))
            .map((item) => item.replace(/^-+\s*/, ''))
            .slice(0, 2);
        const experienceContext = achievements.length ? achievements.join('، ') : experienceLines.slice(0, 2).join('، ');

        setResume((current) => ({
            ...current,
            summary: `${role} يمتلك خبرة عملية في ${topSkills}. يتميز بالقدرة على ${experienceContext}، مع تركيز واضح على تحقيق نتائج قابلة للقياس وتقديم قيمة ملموسة لجهة العمل.`,
        }));
        setSummaryNotice('تم إنشاء الملخص المهني بناء على المهارات والخبرات المدخلة. يمكنك تعديله قبل تنزيل PDF.');
    };

    const downloadPdf = async () => {
        const resumeElement = document.querySelector('.cv-print-area') as HTMLElement | null;

        if (!resumeElement || pdfLoading) {
            return;
        }

        setPdfLoading(true);

        try {
            const [{ default: html2canvas }, { jsPDF }] = await Promise.all([
                import('html2canvas'),
                import('jspdf'),
            ]);
            const canvas = await html2canvas(resumeElement, {
                backgroundColor: '#ffffff',
                scale: 2,
                useCORS: true,
            });
            const imageData = canvas.toDataURL('image/png', 1);
            const pdf = new jsPDF('p', 'mm', 'a4');
            const pageWidth = pdf.internal.pageSize.getWidth();
            const pageHeight = pdf.internal.pageSize.getHeight();
            const imageHeight = (canvas.height * pageWidth) / canvas.width;

            let heightLeft = imageHeight;
            let position = 0;

            pdf.addImage(imageData, 'PNG', 0, position, pageWidth, imageHeight);
            heightLeft -= pageHeight;

            while (heightLeft > 0) {
                position -= pageHeight;
                pdf.addPage();
                pdf.addImage(imageData, 'PNG', 0, position, pageWidth, imageHeight);
                heightLeft -= pageHeight;
            }

            pdf.save(`${resume.name || 'cv'}-madarat.pdf`);
        } finally {
            setPdfLoading(false);
        }
    };

    return (
        <AppLayout>
            <section className="overflow-hidden rounded-lg border border-cyan-100 bg-white shadow-sm shadow-madarat-blue/5 lg:grid lg:grid-cols-[1fr_440px]">
                <div className="p-6 md:p-8">
                    <div className="inline-flex items-center gap-2 rounded-full bg-madarat-sky px-3 py-1 text-xs font-black text-madarat-blue ring-1 ring-cyan-100">
                        <Sparkles className="h-4 w-4 text-madarat-cyan" />
                        مدعوم بالذكاء الاصطناعي
                    </div>
                    <h1 className="mt-5 max-w-3xl text-3xl font-black leading-tight text-madarat-navy md:text-5xl">
                        أنشئ سيرة ذاتية احترافية بإطارات جاهزة تناسب مسارك المهني
                    </h1>
                    <p className="mt-5 max-w-2xl text-lg leading-8 text-slate-600">
                        اختر قالبا منظما، افتح الإطار، عدّل كل بيانات السيرة، وشاهد النتيجة كاملة قبل تنزيلها كملف PDF.
                    </p>
                    <div className="mt-7 flex flex-wrap gap-3">
                        <button
                            type="button"
                            onClick={() => openTemplate(0)}
                            className="inline-flex items-center gap-2 rounded-lg bg-madarat-blue px-5 py-3 font-bold text-white shadow-sm shadow-madarat-blue/20 transition hover:bg-madarat-navy"
                        >
                            افتح أول إطار
                            <ArrowUpRight className="h-4 w-4" />
                        </button>
                        <Link href="/seeker/cv-analysis" className="inline-flex items-center gap-2 rounded-lg bg-white px-5 py-3 font-bold text-madarat-blue shadow-sm ring-1 ring-cyan-100 hover:bg-madarat-sky">
                            حلل سيرتك الحالية
                        </Link>
                    </div>
                </div>
                <div className="relative min-h-[28rem] bg-madarat-navy p-6 text-white">
                    <div className="absolute inset-x-8 top-8 h-1 rounded-full bg-madarat-cyan" />
                    <div className="relative mx-auto mt-10 max-w-xs rounded-lg bg-white p-5 text-madarat-navy shadow-2xl shadow-slate-950/25">
                        <div className="flex items-center justify-between">
                            <div>
                                <div className="h-3 w-32 rounded-full bg-madarat-navy" />
                                <div className="mt-2 h-2 w-24 rounded-full bg-slate-200" />
                            </div>
                            <span className="grid h-14 w-14 place-items-center rounded-lg bg-madarat-sky text-madarat-blue">
                                <FileText className="h-7 w-7" />
                            </span>
                        </div>
                        <div className="mt-6 grid grid-cols-[.72fr_1.28fr] gap-4">
                            <div className="space-y-2">
                                <div className="h-2 rounded-full bg-madarat-cyan" />
                                <div className="h-2 rounded-full bg-slate-200" />
                                <div className="h-2 rounded-full bg-slate-200" />
                                <div className="h-2 rounded-full bg-slate-200" />
                            </div>
                            <div className="space-y-2">
                                <div className="h-2 rounded-full bg-slate-300" />
                                <div className="h-2 rounded-full bg-slate-200" />
                                <div className="h-2 rounded-full bg-slate-200" />
                                <div className="h-2 w-3/4 rounded-full bg-slate-200" />
                            </div>
                        </div>
                        <div className="mt-6 rounded-lg bg-madarat-sky p-3 text-sm font-black text-madarat-blue">
                            افتح الإطار، عدّل البيانات، ثم نزّل PDF
                        </div>
                    </div>
                </div>
            </section>

            <section className="mt-6 grid gap-4 md:grid-cols-4">
                {aiFeatures.map(([title, text, Icon]) => (
                    <Card key={title}>
                        <span className="grid h-11 w-11 place-items-center rounded-lg bg-madarat-sky text-madarat-blue ring-1 ring-cyan-100">
                            <Icon className="h-5 w-5" />
                        </span>
                        <h2 className="mt-4 font-black text-madarat-navy">{title}</h2>
                        <p className="mt-2 text-sm leading-7 text-slate-600">{text}</p>
                    </Card>
                ))}
            </section>

            <section className="mt-8">
                <div className="mb-4 flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <p className="text-sm font-black text-madarat-cyan">إطارات جاهزة</p>
                        <h2 className="mt-1 text-2xl font-black text-madarat-navy">اختر قالب السيرة الأنسب</h2>
                    </div>
                    <div className="flex items-center gap-2 rounded-lg bg-white px-3 py-2 text-sm font-bold text-slate-500 ring-1 ring-cyan-100">
                        <Layers className="h-4 w-4 text-madarat-cyan" />
                        اضغط على أي إطار لفتحه
                    </div>
                </div>
                <div className="grid gap-4 md:grid-cols-3">
                    {templates.map((template, index) => (
                        <TemplateCard key={template.name} template={template} index={index} onSelect={() => openTemplate(index)} />
                    ))}
                </div>
            </section>

            <section className="mt-8 grid gap-4 lg:grid-cols-[1fr_360px]">
                <Card className="bg-madarat-navy text-white">
                    <div className="flex items-start gap-3">
                        <CheckCircle2 className="mt-1 h-6 w-6 shrink-0 text-madarat-cyan" />
                        <div>
                            <h2 className="text-xl font-black">معاينة كاملة قبل التنزيل</h2>
                            <p className="mt-2 leading-8 text-cyan-50">
                                بعد فتح الإطار تظهر السيرة كاملة بجانب نموذج التعديل، ويمكن تنزيل النسخة المعروضة كملف PDF مباشرة من زر التحميل.
                            </p>
                        </div>
                    </div>
                </Card>
                <Card>
                    <Palette className="h-7 w-7 text-madarat-cyan" />
                    <h2 className="mt-3 font-black text-madarat-navy">كل إطار قابل للتخصيص</h2>
                    <p className="mt-2 text-sm leading-7 text-slate-600">
                        الاسم، المسمى، بيانات التواصل، الملخص، المهارات، الخبرات، التعليم واللغات كلها قابلة للتعديل قبل PDF.
                    </p>
                </Card>
            </section>

            {selectedTemplate && (
                <div className="fixed inset-0 z-50 overflow-y-auto bg-slate-950/70 p-3 backdrop-blur-sm md:p-6">
                    <div className="cv-no-print mx-auto mb-4 flex max-w-7xl flex-wrap items-center justify-between gap-3 rounded-lg bg-white p-3 shadow-xl">
                        <div>
                            <p className="text-xs font-black text-madarat-cyan">الإطار المختار</p>
                            <h2 className="font-black text-madarat-navy">{selectedTemplate.name}</h2>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <button
                                type="button"
                                onClick={downloadPdf}
                                disabled={pdfLoading}
                                className="inline-flex items-center gap-2 rounded-lg bg-madarat-blue px-4 py-2 text-sm font-bold text-white shadow-sm shadow-madarat-blue/20 hover:bg-madarat-navy disabled:cursor-not-allowed disabled:opacity-70"
                            >
                                {pdfLoading ? <LoaderCircle className="h-4 w-4 animate-spin" /> : <Download className="h-4 w-4" />}
                                {pdfLoading ? 'جار تجهيز PDF...' : 'تنزيل PDF'}
                            </button>
                            <button
                                type="button"
                                onClick={() => setSelectedIndex(null)}
                                className="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-slate-100 text-slate-600 hover:bg-slate-200"
                                aria-label="إغلاق"
                            >
                                <X className="h-5 w-5" />
                            </button>
                        </div>
                    </div>

                    <div className="mx-auto grid max-w-7xl gap-5 lg:grid-cols-[360px_1fr]">
                        <Card className="cv-no-print h-fit">
                            <h3 className="font-black text-madarat-navy">بيانات السيرة</h3>
                            <p className="mt-1 text-sm leading-6 text-slate-500">عدّل البيانات هنا وستتغير المعاينة مباشرة.</p>
                            <div className="mt-4 space-y-3">
                                <div className="rounded-lg border border-cyan-100 bg-white p-3">
                                    <span className="text-xs font-black text-slate-500">الصورة الشخصية</span>
                                    <div className="mt-3 flex items-center gap-3">
                                        <span className="grid h-16 w-16 shrink-0 place-items-center overflow-hidden rounded-lg bg-madarat-sky text-madarat-blue ring-1 ring-cyan-100">
                                            {resume.photo ? (
                                                <img
                                                    src={resume.photo}
                                                    alt={resume.name}
                                                    className="h-full w-full object-cover"
                                                    style={{
                                                        objectPosition: `${resume.photoX}% ${resume.photoY}%`,
                                                        transform: `scale(${Number(resume.photoZoom) / 100})`,
                                                    }}
                                                />
                                            ) : (
                                                <UserRound className="h-7 w-7" />
                                            )}
                                        </span>
                                        <div className="min-w-0 flex-1">
                                            <label className="inline-flex cursor-pointer items-center gap-2 rounded-lg bg-madarat-blue px-3 py-2 text-xs font-bold text-white shadow-sm shadow-madarat-blue/20 hover:bg-madarat-navy">
                                                <Upload className="h-4 w-4" />
                                                رفع صورة
                                                <input type="file" accept="image/*" onChange={updatePhoto} className="hidden" />
                                            </label>
                                            {resume.photo && (
                                                <button
                                                    type="button"
                                                    onClick={() => updateResume('photo', '')}
                                                    className="mr-2 rounded-lg bg-slate-100 px-3 py-2 text-xs font-bold text-slate-600 hover:bg-slate-200"
                                                >
                                                    حذف
                                                </button>
                                            )}
                                        </div>
                                    </div>
                                    {resume.photo && (
                                        <div className="mt-4 space-y-3">
                                            <label className="block">
                                                <span className="flex items-center justify-between text-xs font-bold text-slate-600">
                                                    <span>تحريك أفقي</span>
                                                    <span>{resume.photoX}%</span>
                                                </span>
                                                <input
                                                    type="range"
                                                    min="0"
                                                    max="100"
                                                    value={resume.photoX}
                                                    onChange={(event) => updateResume('photoX', event.target.value)}
                                                    className="mt-2 w-full accent-madarat-blue"
                                                />
                                            </label>
                                            <label className="block">
                                                <span className="flex items-center justify-between text-xs font-bold text-slate-600">
                                                    <span>تحريك عمودي</span>
                                                    <span>{resume.photoY}%</span>
                                                </span>
                                                <input
                                                    type="range"
                                                    min="0"
                                                    max="100"
                                                    value={resume.photoY}
                                                    onChange={(event) => updateResume('photoY', event.target.value)}
                                                    className="mt-2 w-full accent-madarat-blue"
                                                />
                                            </label>
                                            <label className="block">
                                                <span className="flex items-center justify-between text-xs font-bold text-slate-600">
                                                    <span>تكبير الصورة</span>
                                                    <span>{resume.photoZoom}%</span>
                                                </span>
                                                <input
                                                    type="range"
                                                    min="100"
                                                    max="180"
                                                    value={resume.photoZoom}
                                                    onChange={(event) => updateResume('photoZoom', event.target.value)}
                                                    className="mt-2 w-full accent-madarat-blue"
                                                />
                                            </label>
                                            <button
                                                type="button"
                                                onClick={() => {
                                                    updateResume('photoX', '50');
                                                    updateResume('photoY', '50');
                                                    updateResume('photoZoom', '100');
                                                }}
                                                className="rounded-lg bg-slate-100 px-3 py-2 text-xs font-bold text-slate-600 hover:bg-slate-200"
                                            >
                                                إعادة ضبط موضع الصورة
                                            </button>
                                        </div>
                                    )}
                                </div>

                                <div className="rounded-lg border border-cyan-100 bg-white p-3">
                                    <span className="text-xs font-black text-slate-500">ألوان السيرة</span>
                                    <div className="mt-3 grid gap-3 sm:grid-cols-2">
                                        <label className="flex items-center justify-between gap-3 rounded-lg bg-slate-50 p-2">
                                            <span className="text-xs font-bold text-slate-600">اللون الأساسي</span>
                                            <input
                                                type="color"
                                                value={colors.primary}
                                                onChange={(event) => updateColor('primary', event.target.value)}
                                                className="h-9 w-12 cursor-pointer rounded border border-slate-200 bg-white p-1"
                                            />
                                        </label>
                                        <label className="flex items-center justify-between gap-3 rounded-lg bg-slate-50 p-2">
                                            <span className="text-xs font-bold text-slate-600">اللون الثانوي</span>
                                            <input
                                                type="color"
                                                value={colors.secondary}
                                                onChange={(event) => updateColor('secondary', event.target.value)}
                                                className="h-9 w-12 cursor-pointer rounded border border-slate-200 bg-white p-1"
                                            />
                                        </label>
                                    </div>
                                </div>

                                <Field label="الاسم" value={resume.name} onChange={(value) => updateResume('name', value)} />
                                <Field label="المسمى المهني" value={resume.headline} onChange={(value) => updateResume('headline', value)} />
                                <Field label="البريد الإلكتروني" value={resume.email} onChange={(value) => updateResume('email', value)} />
                                <Field label="الهاتف" value={resume.phone} onChange={(value) => updateResume('phone', value)} />
                                <Field label="الموقع" value={resume.location} onChange={(value) => updateResume('location', value)} />
                                <Field label="المهارات - كل مهارة في سطر" value={resume.skills} onChange={(value) => updateResume('skills', value)} textarea />
                                <Field label="الخبرات والإنجازات" value={resume.experience} onChange={(value) => updateResume('experience', value)} textarea />
                                <Field label="التعليم" value={resume.education} onChange={(value) => updateResume('education', value)} textarea />
                                <Field label="اللغات" value={resume.languages} onChange={(value) => updateResume('languages', value)} textarea />
                                <div className="rounded-lg border border-cyan-100 bg-madarat-sky/70 p-3">
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <span className="text-xs font-black text-madarat-blue">الملخص المهني - آخر خطوة</span>
                                        <button
                                            type="button"
                                            onClick={generateAiSummary}
                                            className="inline-flex items-center gap-2 rounded-lg bg-madarat-blue px-3 py-2 text-xs font-bold text-white shadow-sm shadow-madarat-blue/20 hover:bg-madarat-navy"
                                        >
                                            <WandSparkles className="h-4 w-4" />
                                            تلخيص بالذكاء الاصطناعي
                                        </button>
                                    </div>
                                    {summaryNotice && (
                                        <p className={`mt-2 text-xs font-bold leading-6 ${summaryNotice.startsWith('أدخل') ? 'text-red-700' : 'text-emerald-700'}`}>
                                            {summaryNotice}
                                        </p>
                                    )}
                                    <div className="mt-3">
                                        <Field label="الملخص المهني" value={resume.summary} onChange={(value) => updateResume('summary', value)} textarea />
                                    </div>
                                </div>
                            </div>
                        </Card>

                        <div className="overflow-x-auto rounded-lg bg-slate-100 p-3 md:p-6">
                            <ResumeDocument data={resume} template={selectedTemplate} colors={colors} />
                        </div>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
