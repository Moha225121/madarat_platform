import { Link } from '@inertiajs/react';
import { ArrowUpRight, BrainCircuit, FileSearch, FileText, LayoutTemplate, MessageCircle, Send, Sparkles, WandSparkles } from 'lucide-react';
import { AppLayout, AssistantRobot, Card, Job, JobCard, MadaratLogo, StatCard, icons } from '@/Components/Madarat';

export default function Welcome({ featuredJobs = [] }: { featuredJobs: Job[] }) {
    const features = [
        ['تحليل السيرة الذاتية', FileSearch],
        ['إنشاء السيرة الذاتية', FileText],
        ['المطابقة الذكية', BrainCircuit],
        ['توليد الوصف الوظيفي', WandSparkles],
    ] as const;
    const assistantTasks = [
        ['يرشد الباحث عن عمل لتحسين السيرة الذاتية واختيار الوظائف الأقرب لمهاراته.', FileSearch],
        ['يساعد أصحاب العمل في صياغة وصف وظيفي واضح وجاذب للمرشحين.', WandSparkles],
        ['يجيب عن الأسئلة داخل الحساب بعد تسجيل الدخول بخطوات مختصرة وعملية.', MessageCircle],
    ] as const;

    return (
        <AppLayout>
            <section className="relative overflow-hidden rounded-lg border border-cyan-100 bg-white px-5 py-8 shadow-sm shadow-madarat-blue/5 md:px-8 lg:grid lg:grid-cols-[1.08fr_.92fr] lg:items-center lg:gap-8">
                <div className="pointer-events-none absolute -left-24 top-10 h-80 w-80 rounded-full border-[42px] border-madarat-cyan/10" />
                <div className="pointer-events-none absolute bottom-8 right-1/3 h-40 w-40 rounded-full border-[22px] border-madarat-blue/10" />

                <div className="relative z-10">
                    <MadaratLogo />
                    <h1 className="mt-8 max-w-4xl text-4xl font-black leading-tight text-madarat-navy md:text-5xl">
                        منصة مدارات الذكية للتوظيف المدعوم بالذكاء الاصطناعي
                    </h1>
                    <p className="mt-5 max-w-2xl text-lg leading-8 text-slate-600">
                        نربط الشركات بالكفاءات المناسبة عبر تحليل السير الذاتية والمطابقة الذكية وتجربة عربية واضحة من أول خطوة.
                    </p>
                    <div className="mt-8 flex flex-wrap gap-3">
                        <Link href="/jobs" className="inline-flex items-center gap-2 rounded-lg bg-madarat-blue px-5 py-3 font-bold text-white shadow-sm shadow-madarat-blue/20 transition hover:bg-madarat-navy">
                            ابحث عن وظيفة
                            <ArrowUpRight className="h-4 w-4" />
                        </Link>
                        <Link href="/register" className="rounded-lg bg-white px-5 py-3 font-bold text-madarat-blue shadow-sm ring-1 ring-cyan-100 hover:bg-madarat-sky">
                            وظف كفاءات
                        </Link>
                    </div>
                </div>

                <div className="relative z-10 mt-10 min-h-[390px] lg:mt-0">
                    <div className="absolute left-6 top-0 h-72 w-52 rotate-3 rounded-lg bg-gradient-to-b from-madarat-cyan to-madarat-blue p-4 text-white shadow-2xl shadow-madarat-blue/20">
                        <div className="absolute inset-x-0 bottom-0 h-40 overflow-hidden rounded-b-lg">
                            <div className="absolute -left-8 top-4 h-32 w-32 rounded-full border-[18px] border-white/12" />
                            <div className="absolute right-4 top-10 h-28 w-28 rounded-full border-[16px] border-white/12" />
                            <div className="absolute left-12 top-24 h-20 w-20 rounded-full border-[12px] border-white/12" />
                        </div>
                        <p className="text-left text-sm font-black">Notebook</p>
                        <div className="relative mt-16 rounded-lg bg-white p-4 text-madarat-navy shadow-lg">
                            <MadaratLogo />
                            <div className="mt-4 h-1.5 rounded-full bg-madarat-cyan" />
                            <p className="mt-3 text-sm font-black">حيث تتحول الأفكار إلى واقع</p>
                        </div>
                    </div>
                    <div className="absolute right-0 top-12 w-72 rounded-lg bg-white p-5 shadow-2xl shadow-slate-900/10 ring-1 ring-cyan-100">
                        <div className="mb-5 h-0.5 bg-madarat-cyan" />
                        <div className="mx-auto grid h-44 w-44 place-items-center rounded-full bg-madarat-sky">
                            <AssistantRobot />
                        </div>
                        <div className="mt-6 rounded-lg bg-gradient-to-l from-madarat-blue to-madarat-cyan px-4 py-3 text-sm font-black text-white">
                            مساعدك الذكي يظهر بعد تسجيل الدخول
                        </div>
                    </div>
                    <div className="absolute bottom-3 right-10 w-48 -rotate-6 rounded-lg bg-white p-4 shadow-xl shadow-slate-900/10 ring-1 ring-cyan-100">
                        <MadaratLogo />
                        <div className="mt-5 space-y-2 text-xs font-bold text-madarat-blue">
                            <p>تحليل السيرة</p>
                            <p>اقتراح الوظائف</p>
                            <p>تجهيز المقابلات</p>
                        </div>
                    </div>
                </div>
            </section>

            <section className="mt-6 grid gap-4 md:grid-cols-3">
                <StatCard label="باحث عن عمل" value="+15,000" icon={icons.UserRound} />
                <StatCard label="وظيفة منشورة" value="+3,200" icon={icons.BriefcaseBusiness} />
                <StatCard label="شركة موثوقة" value="+850" icon={icons.Building2} />
            </section>

            <section className="mt-6 grid gap-4 md:grid-cols-4">
                {features.map(([feature, Icon]) => (
                    <Card key={feature} className="relative overflow-hidden">
                        <div className="absolute -left-8 -top-8 h-24 w-24 rounded-full border-[14px] border-madarat-cyan/10" />
                        <Icon className="relative h-7 w-7 text-madarat-cyan" />
                        <h3 className="relative mt-3 font-black text-madarat-navy">{feature}</h3>
                    </Card>
                ))}
            </section>

            <section className="mt-6 overflow-hidden rounded-lg border border-cyan-100 bg-white shadow-sm shadow-madarat-blue/5 lg:grid lg:grid-cols-[1fr_360px]">
                <div className="p-5 md:p-8">
                    <div className="inline-flex items-center gap-2 rounded-full bg-madarat-sky px-3 py-1 text-xs font-black text-madarat-blue ring-1 ring-cyan-100">
                        <LayoutTemplate className="h-4 w-4 text-madarat-cyan" />
                        قسم جديد
                    </div>
                    <h2 className="mt-4 text-3xl font-black text-madarat-navy">منشئ سيرة ذاتية بإطارات احترافية جاهزة</h2>
                    <p className="mt-3 max-w-2xl text-base leading-8 text-slate-600">
                        اختر قالبا مناسبا لمسارك، ثم استخدم مساعد مدارات لصياغة الملخص والخبرات والمهارات بطريقة أكثر وضوحا وجاذبية لأصحاب العمل.
                    </p>
                    <div className="mt-5 flex flex-wrap gap-3">
                        <Link href="/cv-builder" className="inline-flex items-center gap-2 rounded-lg bg-madarat-blue px-5 py-3 text-sm font-bold text-white shadow-sm shadow-madarat-blue/20 transition hover:bg-madarat-navy">
                            استعرض الإطارات
                            <ArrowUpRight className="h-4 w-4" />
                        </Link>
                        <Link href="/seeker/cv-analysis" className="rounded-lg bg-white px-5 py-3 text-sm font-bold text-madarat-blue shadow-sm ring-1 ring-cyan-100 hover:bg-madarat-sky">
                            حلل سيرتك الحالية
                        </Link>
                    </div>
                </div>
                <div className="grid gap-3 bg-madarat-sky p-5 md:grid-cols-3 lg:grid-cols-1">
                    {['قالب تنفيذي', 'قالب تقني', 'قالب حديث'].map((template) => (
                        <div key={template} className="rounded-lg bg-white p-4 shadow-sm ring-1 ring-cyan-100">
                            <div className="mb-4 flex items-center justify-between">
                                <strong className="text-sm text-madarat-navy">{template}</strong>
                                <FileText className="h-5 w-5 text-madarat-cyan" />
                            </div>
                            <div className="space-y-2">
                                <div className="h-2 rounded-full bg-madarat-blue/80" />
                                <div className="h-2 rounded-full bg-slate-200" />
                                <div className="h-2 w-2/3 rounded-full bg-slate-200" />
                            </div>
                        </div>
                    ))}
                </div>
            </section>

            <section className="mt-6 overflow-hidden rounded-lg border border-cyan-100 bg-white shadow-sm shadow-madarat-blue/5 lg:grid lg:grid-cols-[320px_1fr]">
                <div className="relative grid min-h-72 place-items-center bg-madarat-navy p-8 text-white">
                    <div className="absolute inset-x-8 top-8 h-1 rounded-full bg-madarat-cyan" />
                    <AssistantRobot className="scale-110" />
                    <div className="mt-4 text-center">
                        <h2 className="text-2xl font-black">شخصية المساعد الذكي</h2>
                        <p className="mt-2 text-sm font-bold leading-7 text-cyan-100">روبوت واضح يرافق المستخدم داخل المنصة بعد تسجيل الدخول.</p>
                    </div>
                </div>
                <div className="p-5 md:p-8">
                    <div className="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <p className="text-sm font-black text-madarat-cyan">ماذا يفعل؟</p>
                            <h3 className="mt-2 text-3xl font-black text-madarat-navy">مساعد سريع للباحثين والشركات</h3>
                        </div>
                        <Link href="/login" className="inline-flex items-center gap-2 rounded-lg bg-madarat-blue px-5 py-3 text-sm font-bold text-white shadow-sm shadow-madarat-blue/20 transition hover:bg-madarat-navy">
                            جرّبه بعد الدخول
                            <Send className="h-4 w-4" />
                        </Link>
                    </div>
                    <div className="mt-6 grid gap-3 md:grid-cols-3">
                        {assistantTasks.map(([task, Icon]) => (
                            <div key={task} className="rounded-lg border border-cyan-100 bg-madarat-sky/70 p-4">
                                <span className="grid h-11 w-11 place-items-center rounded-full bg-white text-madarat-blue shadow-sm ring-1 ring-cyan-100">
                                    <Icon className="h-5 w-5" />
                                </span>
                                <p className="mt-4 text-sm font-bold leading-7 text-slate-700">{task}</p>
                            </div>
                        ))}
                    </div>
                    <div className="mt-5 flex items-start gap-3 rounded-lg bg-slate-50 p-4 text-sm leading-7 text-slate-600">
                        <Sparkles className="mt-1 h-5 w-5 shrink-0 text-madarat-cyan" />
                        <p>بعد تسجيل الدخول ستجد شخصية الروبوت ثابتة أسفل الصفحة، ويمكنك فتح المحادثة وطلب النصائح أو صياغة الرسائل والردود المهنية.</p>
                    </div>
                </div>
            </section>

            <section className="mt-10">
                <h2 className="mb-4 text-2xl font-black text-madarat-navy">وظائف مميزة</h2>
                <div className="grid gap-4 md:grid-cols-3">{featuredJobs.map((job) => <JobCard key={job.id} job={job} />)}</div>
            </section>
        </AppLayout>
    );
}
