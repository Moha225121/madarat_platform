import { router, useForm, usePage } from '@inertiajs/react';
import { AppLayout, Badge, Button, Card, CompanyVerificationBadge, Job, ProgressBar } from '@/Components/Madarat';

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
                        <span>- {job.location}</span>
                    </p>
                    <div className="mt-5 flex flex-wrap gap-2">{(job.required_skills || []).map((skill) => <Badge key={skill}>{skill}</Badge>)}</div>
                    <p className="mt-6 leading-8 text-slate-700">{job.description}</p>
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
