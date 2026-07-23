import { ButtonHTMLAttributes, FormEvent, PropsWithChildren, useEffect, useState } from 'react';
import { Link, router, useForm, usePage } from '@inertiajs/react';
import { Bot, BookOpen, BriefcaseBusiness, Building2, CheckCircle2, Clock, Eye, FileText, Gauge, Languages, LoaderCircle, MessageCircle, Send, Sparkles, Target, UserRound, X, XCircle } from 'lucide-react';
import axios from 'axios';

export type Job = {
    id: number;
    title: string;
    slug: string;
    description: string;
    location?: string;
    job_type?: string;
    contract_type?: string;
    experience_level?: string;
    salary_min?: number;
    salary_max?: number;
    status?: string;
    required_skills?: string[];
    responsibilities?: string[];
    company_profile?: { company_name: string; industry?: string; headquarters?: string; description?: string; verification_status?: string; verified_at?: string | null };
    match?: { score: number; summary: string } | null;
};

function OrbitMark({ compact = false }: { compact?: boolean }) {
    return (
        <span className={`relative isolate grid shrink-0 place-items-center rounded-full bg-white shadow-sm ring-1 ring-cyan-100 ${compact ? 'h-10 w-10' : 'h-12 w-12'}`}>
            <span className="absolute inset-1 rounded-full border-[6px] border-madarat-cyan/90 border-l-madarat-blue" />
            <span className="absolute h-3/5 w-3/5 rounded-full border-[5px] border-madarat-blue/15" />
            <span className="absolute -left-0.5 top-1 h-3 w-3 rounded-full bg-madarat-navy" />
            <span className="absolute -right-1 top-1.5 h-4 w-4 rotate-45 rounded-[3px] bg-madarat-blue" />
            <span className="h-2.5 w-2.5 rounded-full bg-madarat-navy" />
        </span>
    );
}

export function MadaratLogo() {
    return (
        <Link href="/" className="flex items-center gap-3">
            <img src="/assets/madarat-logo.png" onError={(e) => ((e.currentTarget.style.display = 'none'))} className="hidden h-12 w-12 rounded-full object-contain" />
            <OrbitMark />
            <span className="grid leading-tight">
                <strong className="text-lg font-black text-madarat-navy">مدارات</strong>
                <span className="text-xs font-bold tracking-[0.18em] text-madarat-cyan">MADARAT</span>
            </span>
        </Link>
    );
}

export function Button({ children, className = '', ...props }: PropsWithChildren<ButtonHTMLAttributes<HTMLButtonElement>>) {
    return (
        <button
            {...props}
            className={`inline-flex items-center justify-center gap-2 rounded-lg bg-madarat-blue px-4 py-2 text-sm font-bold text-white shadow-sm shadow-madarat-blue/20 transition hover:-translate-y-0.5 hover:bg-madarat-navy disabled:opacity-60 ${className}`}
        >
            {children}
        </button>
    );
}

export function Card({ children, className = '' }: PropsWithChildren<{ className?: string }>) {
    return <div className={`rounded-lg border border-cyan-100/70 bg-white/95 p-5 shadow-sm shadow-madarat-blue/5 ${className}`}>{children}</div>;
}

export function Badge({ children, tone = 'sky' }: PropsWithChildren<{ tone?: 'sky' | 'green' | 'gray' | 'cyan' }>) {
    const tones = {
        sky: 'bg-madarat-sky text-madarat-blue ring-cyan-100',
        green: 'bg-emerald-50 text-emerald-700 ring-emerald-100',
        gray: 'bg-slate-100 text-slate-700 ring-slate-200',
        cyan: 'bg-cyan-50 text-cyan-700 ring-cyan-100',
    };
    return <span className={`rounded-full px-3 py-1 text-xs font-bold ring-1 ${tones[tone]}`}>{children}</span>;
}

export function CompanyVerificationBadge({ status }: { status?: string | null }) {
    if (status !== 'verified') return null;

    return (
        <span className="inline-flex items-center gap-1 rounded-full bg-cyan-50 px-2 py-0.5 text-xs font-black text-madarat-blue ring-1 ring-cyan-100">
            <CheckCircle2 className="h-3.5 w-3.5 text-madarat-cyan" />
            موثقة
        </span>
    );
}

export function ProgressBar({ value }: { value: number }) {
    return (
        <div className="h-2 overflow-hidden rounded-full bg-slate-100">
            <div className="h-2 rounded-full bg-gradient-to-l from-madarat-cyan to-madarat-blue" style={{ width: `${Math.min(100, Math.max(0, value))}%` }} />
        </div>
    );
}

export function StatCard({ label, value, icon: Icon = Gauge }: { label: string; value: string | number; icon?: any }) {
    return (
        <Card className="relative overflow-hidden">
            <div className="absolute -left-10 -top-10 h-28 w-28 rounded-full border-[18px] border-madarat-cyan/10" />
            <div className="relative flex items-center justify-between">
                <div>
                    <p className="text-sm font-bold text-slate-500">{label}</p>
                    <strong className="mt-1 block text-3xl text-madarat-navy">{value}</strong>
                </div>
                <span className="grid h-12 w-12 place-items-center rounded-full bg-madarat-sky text-madarat-blue">
                    <Icon className="h-6 w-6" />
                </span>
            </div>
        </Card>
    );
}

export function FlashMessage() {
    const { props } = usePage<any>();
    const message = props.flash?.success || props.flash?.error;
    if (!message) return null;
    return <div className="mb-4 rounded-lg border border-cyan-100 bg-madarat-sky p-3 text-sm font-bold text-madarat-blue">{message}</div>;
}

export function AppLayout({ children }: PropsWithChildren) {
    const { props } = usePage<any>();
    const user = props.auth?.user;
    return (
        <div className="madarat-shell min-h-screen bg-madarat-gray">
            <header className="sticky top-0 z-30 border-b border-cyan-100/70 bg-white/90 backdrop-blur-xl">
                <div className="mx-auto flex max-w-7xl items-center justify-between px-4 py-3">
                    <MadaratLogo />
                    <nav className="flex items-center gap-2 text-sm font-bold text-madarat-dark">
                        <Link href="/jobs" className="rounded-lg px-3 py-2 hover:bg-madarat-sky">الوظائف</Link>
                        <Link href="/cv-builder" className="rounded-lg px-3 py-2 hover:bg-madarat-sky">منشئ السيرة</Link>
                        {user ? (
                            <Link href="/dashboard" className="rounded-lg px-3 py-2 hover:bg-madarat-sky">لوحتي</Link>
                        ) : (
                            <>
                                <Link href="/login" className="rounded-lg px-3 py-2 hover:bg-madarat-sky">دخول</Link>
                                <Link href="/register/job-seeker" className="rounded-lg bg-madarat-blue px-4 py-2 text-white shadow-sm shadow-madarat-blue/20 hover:bg-madarat-navy">إنشاء حساب</Link>
                            </>
                        )}
                        {user && <button onClick={() => router.post('/logout')} className="rounded-lg px-3 py-2 text-slate-500 hover:bg-slate-100">خروج</button>}
                        <span className="hidden items-center gap-1 rounded-lg bg-slate-100 px-3 py-2 text-slate-500 sm:flex"><Languages className="h-4 w-4" /> AR / EN</span>
                    </nav>
                </div>
            </header>
            <main className="relative mx-auto max-w-7xl px-4 py-8"><FlashMessage />{children}</main>
            {user && <AssistantWidget />}
        </div>
    );
}

export function DashboardLayout({ title, children }: PropsWithChildren<{ title: string }>) {
    const { props } = usePage<any>();
    const role = props.auth?.user?.role;
    const links = role === 'employer'
        ? [['/employer/dashboard', 'الرئيسية'], ['/employer/company', 'الشركة'], ['/employer/jobs/create', 'نشر وظيفة']]
        : role === 'admin'
            ? [['/admin/dashboard', 'الرئيسية'], ['/admin/jobs/pending', 'مراجعة الوظائف'], ['/admin/companies/verification', 'توثيق الشركات']]
            : [['/seeker/dashboard', 'الرئيسية'], ['/seeker/profile', 'ملفي'], ['/seeker/cv-analysis', 'تحليل السيرة'], ['/cv-builder', 'منشئ السيرة'], ['/seeker/applications', 'طلباتي']];
    return (
        <AppLayout>
            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <h1 className="text-2xl font-black text-madarat-navy">{title}</h1>
                <div className="flex flex-wrap gap-2">
                    {links.map(([href, label]) => <Link key={href} href={href} className="rounded-lg bg-white px-3 py-2 text-sm font-bold text-madarat-blue shadow-sm ring-1 ring-cyan-100 hover:bg-madarat-sky">{label}</Link>)}
                </div>
            </div>
            {children}
        </AppLayout>
    );
}

export function JobCard({ job }: { job: Job }) {
    return (
        <Card className="flex h-full flex-col gap-4 transition hover:-translate-y-1 hover:shadow-lg hover:shadow-madarat-blue/10">
            <div className="flex items-start justify-between gap-3">
                <div>
                    <h3 className="text-lg font-black text-madarat-navy">{job.title}</h3>
                    <p className="mt-1 flex flex-wrap items-center gap-2 text-sm text-slate-500">
                        <span>{job.company_profile?.company_name}</span>
                        <CompanyVerificationBadge status={job.company_profile?.verification_status} />
                        <span>- {job.location || 'عن بعد'}</span>
                    </p>
                </div>
                {job.match && <Badge tone="green">{job.match.score}%</Badge>}
            </div>
            <p className="line-clamp-2 text-sm leading-6 text-slate-600">{job.description}</p>
            <div className="flex flex-wrap gap-2">{(job.required_skills || []).slice(0, 4).map((skill) => <Badge key={skill}>{skill}</Badge>)}</div>
            <div className="mt-auto flex items-center justify-between text-sm text-slate-500">
                <span>{job.job_type || 'دوام كامل'}</span>
                <Link href={`/jobs/${job.slug}`} className="font-bold text-madarat-blue">عرض التفاصيل</Link>
            </div>
        </Card>
    );
}

export function EmptyState({ title, text }: { title: string; text: string }) {
    return <Card className="text-center"><Sparkles className="mx-auto h-10 w-10 text-madarat-cyan" /><h3 className="mt-3 font-black text-madarat-navy">{title}</h3><p className="mt-1 text-sm text-slate-500">{text}</p></Card>;
}

export function AssistantRobot({ className = '', compact = false }: { className?: string; compact?: boolean }) {
    return (
        <div className={`relative isolate ${compact ? 'h-20 w-20' : 'h-48 w-40'} ${className}`}>
            <img
                src="/assets/assistant-robot.png"
                alt="مساعد مدارات الذكي"
                className="h-full w-full object-contain drop-shadow-xl"
            />
        </div>
    );
}

function LegacyAssistantWidget() {
    const [open, setOpen] = useState(false);
    const [reply, setReply] = useState('اختر سؤالا أو اكتب ما تريد وسأساعدك بإجابة مبدئية.');
    const { data, setData, processing, reset } = useForm({ message: '' });
    const prompts = ['كيف أحسن سيرتي الذاتية؟', 'ما الوظائف المناسبة لي؟', 'كيف أستعد للمقابلة؟', 'صغ لي وصفا وظيفيا'];
    const send = (message?: string) => {
        const body = message || data.message;
        if (!body) return;
        axios.post('/assistant/message', { message: body })
            .then((response) => {
                setReply(response.data.reply);
                reset();
            })
            .catch(() => setReply('تعذر إرسال الرسالة الآن.'));
    };
    return (
        <div className="fixed bottom-5 left-5 z-40">
            <button onClick={() => setOpen(!open)} className="rounded-full bg-madarat-blue p-4 text-white shadow-lg shadow-madarat-blue/30 transition hover:bg-madarat-navy"><Bot className="h-6 w-6" /></button>
            {open && (
                <Card className="mt-3 w-80">
                    <h3 className="flex items-center gap-2 font-black text-madarat-navy"><Sparkles className="h-5 w-5 text-madarat-cyan" /> مساعد مدارات الذكي</h3>
                    <p className="mt-3 rounded-lg bg-madarat-sky p-3 text-sm leading-6 text-madarat-dark">{reply}</p>
                    <div className="mt-3 flex flex-wrap gap-2">{prompts.map((p) => <button key={p} onClick={() => send(p)} className="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-700">{p}</button>)}</div>
                    <form onSubmit={(e: FormEvent) => { e.preventDefault(); send(); }} className="mt-3 flex gap-2">
                        <input value={data.message} onChange={(e) => setData('message', e.target.value)} className="min-w-0 flex-1 rounded-lg border-slate-200 text-sm" placeholder="اكتب رسالتك" />
                        <Button disabled={processing} className="px-3"><Send className="h-4 w-4" /></Button>
                    </form>
                </Card>
            )}
        </div>
    );
}

export function AssistantWidget() {
    const [open, setOpen] = useState(false);
    const [docked, setDocked] = useState(false);
    const [reply, setReply] = useState('اختر سؤالا أو اكتب ما تريد، وسأساعدك بإجابة عملية ومختصرة.');
    const [lastQuestion, setLastQuestion] = useState('');
    const [sending, setSending] = useState(false);
    const { data, setData, reset } = useForm({ message: '' });
    const prompts = ['حسّن سيرتي الذاتية', 'اقترح وظائف مناسبة', 'جهّزني للمقابلة'];

    useEffect(() => {
        const timer = window.setTimeout(() => setDocked(true), 4200);
        const dockOnFirstInteraction = () => setDocked(true);

        if (!docked) {
            window.addEventListener('pointerdown', dockOnFirstInteraction, { once: true });
        }

        return () => {
            window.clearTimeout(timer);
            window.removeEventListener('pointerdown', dockOnFirstInteraction);
        };
    }, []);

    const send = (message?: string) => {
        const body = (message || data.message).trim();
        if (!body) return;

        setLastQuestion(body);
        setSending(true);
        axios.post('/assistant/message', { message: body })
            .then((response) => {
                setReply(response.data.reply);
                reset();
            })
            .catch(() => setReply('تعذر إرسال الرسالة الآن. حاول مرة أخرى بعد لحظات.'))
            .finally(() => setSending(false));
    };

    return (
        <div
            className={`fixed z-40 transition-all duration-700 ease-out ${
                docked
                    ? open
                        ? 'bottom-4 left-4 translate-x-0 translate-y-0 sm:bottom-5 sm:left-5'
                        : 'left-4 top-[calc(100vh-7rem)] translate-x-0 translate-y-0 sm:left-5 sm:top-[calc(100vh-7.5rem)]'
                    : 'left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2'
            }`}
        >
            <button
                type="button"
                onClick={() => {
                    if (!docked) {
                        setDocked(true);
                        return;
                    }

                    setDocked(true);
                    setOpen(!open);
                }}
                aria-label={open ? 'إغلاق مساعد مدارات' : 'فتح مساعد مدارات'}
                className={`group relative flex items-center gap-3 text-right transition hover:-translate-y-1 focus:outline-none focus:ring-4 focus:ring-madarat-cyan/25 ${
                    docked
                        ? 'rounded-lg border border-cyan-100 bg-white px-3 py-2 shadow-xl shadow-madarat-blue/20 hover:border-cyan-200 hover:bg-madarat-sky'
                        : 'bg-transparent p-0 shadow-none'
                }`}
            >
                <span className="relative">
                    <AssistantRobot compact={docked} className={docked ? '' : 'scale-110'} />
                    {docked && <span className="absolute right-1 top-1 h-3.5 w-3.5 rounded-full border-2 border-white bg-emerald-400" />}
                </span>
                {docked ? (
                    <span className="hidden min-w-0 sm:block">
                        <span className="block text-sm font-black text-madarat-navy">مساعد مدارات</span>
                        <span className="mt-0.5 flex items-center gap-1 text-xs font-bold text-slate-500">
                            <MessageCircle className="h-3.5 w-3.5 text-madarat-cyan" />
                            اسألني عن التوظيف
                        </span>
                    </span>
                ) : (
                    <span className="min-w-52 rounded-lg border border-cyan-100 bg-white px-4 py-3 text-center text-sm font-black text-madarat-navy shadow-xl shadow-madarat-blue/15">
                        مرحباً، أنا مساعدك الذكي
                    </span>
                )}
                {open && <X className="h-5 w-5 text-slate-400" />}
            </button>

            {open && (
                <section className="mt-3 flex h-[min(34rem,calc(100vh-9rem))] w-[min(calc(100vw-2rem),24rem)] flex-col overflow-hidden rounded-lg border border-cyan-100 bg-white shadow-2xl shadow-slate-900/15">
                    <div className="bg-madarat-navy px-4 py-4 text-white">
                        <div className="flex items-center gap-3">
                            <AssistantRobot compact className="scale-75" />
                            <div className="min-w-0">
                                <h3 className="font-black leading-tight">مساعد مدارات الذكي</h3>
                                <div className="mt-1 flex items-center gap-1.5 text-xs font-bold text-cyan-100">
                                    <span className="h-2 w-2 rounded-full bg-emerald-400" />
                                    جاهز لمساعدتك
                                </div>
                            </div>
                        </div>
                    </div>

                    <div className="flex-1 space-y-4 overflow-y-auto bg-gradient-to-b from-madarat-sky/60 to-white p-4">
                        <div className="flex items-start gap-2">
                            <span className="mt-1 grid h-8 w-8 shrink-0 place-items-center rounded-full bg-white text-madarat-blue shadow-sm ring-1 ring-cyan-100">
                                <Sparkles className="h-4 w-4" />
                            </span>
                            <p className="max-w-[82%] rounded-lg rounded-tr-sm bg-white px-3 py-2.5 text-sm leading-7 text-madarat-dark shadow-sm ring-1 ring-cyan-100/70">
                                {reply}
                            </p>
                        </div>

                        {lastQuestion && (
                            <div className="flex justify-end">
                                <p className="max-w-[82%] rounded-lg rounded-tl-sm bg-madarat-blue px-3 py-2.5 text-sm leading-7 text-white shadow-sm shadow-madarat-blue/20">
                                    {lastQuestion}
                                </p>
                            </div>
                        )}

                        {sending && (
                            <div className="flex items-center gap-2 text-xs font-bold text-slate-500">
                                <LoaderCircle className="h-4 w-4 animate-spin text-madarat-cyan" />
                                يتم تجهيز الرد...
                            </div>
                        )}
                    </div>

                    <div className="border-t border-cyan-100 bg-white p-3">
                        <div className="mb-3 flex gap-2 overflow-x-auto pb-1">
                            {prompts.map((p) => (
                                <button
                                    key={p}
                                    type="button"
                                    onClick={() => send(p)}
                                    disabled={sending}
                                    className="shrink-0 rounded-full bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-700 ring-1 ring-slate-200 transition hover:bg-madarat-sky hover:text-madarat-blue disabled:opacity-60"
                                >
                                    {p}
                                </button>
                            ))}
                        </div>
                        <form onSubmit={(e: FormEvent) => { e.preventDefault(); send(); }} className="flex items-center gap-2">
                            <input
                                value={data.message}
                                onChange={(e) => setData('message', e.target.value)}
                                className="min-w-0 flex-1 rounded-lg border-slate-200 bg-slate-50 text-sm text-madarat-dark placeholder:text-slate-400 focus:border-madarat-cyan focus:ring-madarat-cyan"
                                placeholder="اكتب رسالتك"
                            />
                            <Button type="submit" disabled={sending || !data.message.trim()} className="h-10 w-10 shrink-0 px-0">
                                {sending ? <LoaderCircle className="h-4 w-4 animate-spin" /> : <Send className="h-4 w-4" />}
                                <span className="sr-only">إرسال</span>
                            </Button>
                        </form>
                    </div>
                </section>
            )}
        </div>
    );
}

export const icons = { BookOpen, BriefcaseBusiness, Building2, CheckCircle2, Clock, Eye, FileText, Sparkles, Target, UserRound, XCircle };
