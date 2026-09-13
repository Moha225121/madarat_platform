import { router, useForm, usePage } from '@inertiajs/react';
import { AppLayout, Badge, Button, Card, CompanyVerificationBadge, Job, ProgressBar } from '@/Components/Madarat';

function DetailItem({ label, value }: { label: string; value?: string | number | null }) {
    const displayValue = value === null || value === undefined || value === '' ? '—' : value;

    return (
        <div className="rounded-lg bg-slate-50 p-3 ring-1 ring-slate-100">
            <span className="block text-xs font-bold text-slate-500">{label}</span>
            <strong className="mt-1 block text-sm text-madarat-navy">{displayValue}</strong>
        </div>
    );
}

export default function JobShow({ job, match, alreadyApplied }: { job: Job; match: any; alreadyApplied: boolean }) {
    const { props } = usePage<any>();
    const { data, setData, processing } = useForm({ cover_letter: '' });
    const apply = () => router.post(`/jobs/${job.id}/apply`, data);

    return (
        <AppLayout>
            <div className="grid gap-6 lg:grid-cols-[1fr_360px]">
                <Card>
                    <h1 className="text-3xl font-black text-madarat-navy">{job.title}</h1>
                    <p className="mt-2 flex flex-wrap items-center gap-2 text-slate-500">
                        <span>{job.company_profile?.company_name}</span>
                        <CompanyVerificationBadge status={job.company_profile?.verification_status} />
                    </p>

                    <div className="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <DetailItem label="الموقع" value={job.location} />
                        <DetailItem label="نمط العمل" value={job.job_type} />
                        <DetailItem label="نوع العقد" value={job.contract_type} />
                        <DetailItem label="مستوى الخبرة" value={job.experience_level} />
                        <DetailItem label="الراتب الأدنى" value={job.salary_min} />
                        <DetailItem label="الراتب الأعلى" value={job.salary_max} />
                    </div>

                    <h2 className="mt-6 font-black text-madarat-navy">المهارات المطلوبة</h2>
                    <div className="mt-3 flex flex-wrap gap-2">{(job.required_skills || []).map((skill) => <Badge key={skill}>{skill}</Badge>)}</div>

                    <h2 className="mt-6 font-black text-madarat-navy">الوصف الوظيفي</h2>
                    <p className="mt-3 leading-8 text-slate-700">{job.description}</p>

                    <h2 className="mt-6 font-black text-madarat-navy">المسؤوليات</h2>
                    <ul className="mt-3 list-inside list-disc space-y-2 text-slate-600">{(job.responsibilities || []).map((item) => <li key={item}>{item}</li>)}</ul>
                </Card>

                <Card>
                    <h2 className="font-black text-madarat-navy">معلومات التقديم</h2>
                    {match && (
                        <div className="mt-4">
                            <div className="mb-2 flex justify-between text-sm font-bold">
                                <span>نسبة المطابقة</span>
                                <span>{match.score}%</span>
                            </div>
                            <ProgressBar value={match.score} />
                            <p className="mt-2 text-sm text-slate-500">{match.summary}</p>
                        </div>
                    )}
                    {props.auth?.user?.role === 'job_seeker' && (
                        <div className="mt-5">
                            <textarea value={data.cover_letter} onChange={(e) => setData('cover_letter', e.target.value)} className="w-full rounded-lg border-slate-200" rows={5} placeholder="رسالة تعريفية اختيارية" />
                            <Button onClick={apply} disabled={processing || alreadyApplied} className="mt-3 w-full">{alreadyApplied ? 'تم التقديم مسبقًا' : 'تقديم طلب الآن'}</Button>
                        </div>
                    )}
                </Card>
            </div>
        </AppLayout>
    );
}
