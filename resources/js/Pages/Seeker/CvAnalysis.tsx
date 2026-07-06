import { useForm } from '@inertiajs/react';
import { Badge, Button, Card, DashboardLayout, ProgressBar } from '@/Components/Madarat';

export default function CvAnalysis({ profile }: any) {
    const { data, setData, post, processing } = useForm<{ cv: File | null }>({ cv: null });
    const hasAnalysis = profile.cv_status === 'analyzed';

    return (
        <DashboardLayout title="تحليل السيرة الذاتية">
            <div className="grid gap-5 lg:grid-cols-[360px_1fr]">
                <Card>
                    <form onSubmit={(e) => { e.preventDefault(); post('/seeker/cv-analysis'); }}>
                        <input type="file" accept=".pdf,.doc,.docx" onChange={(e) => setData('cv', e.target.files?.[0] || null)} className="w-full rounded-lg border border-slate-200 p-2" />
                        <Button disabled={processing} className="mt-4 w-full">
                            {processing ? 'جار التحليل...' : 'تحليل السيرة الذاتية'}
                        </Button>
                    </form>
                    {profile.cv_status === 'failed' && (
                        <p className="mt-4 rounded-lg bg-red-50 p-3 text-sm font-bold text-red-700">
                            فشل التحليل السابق. لن يتم إنشاء نتيجة إلا بعد نجاح تحليل الذكاء الاصطناعي.
                        </p>
                    )}
                </Card>

                <Card>
                    <h2 className="font-black text-madarat-navy">التقييم العام: {hasAnalysis ? profile.profile_score || 0 : 0}%</h2>
                    <div className="mt-3"><ProgressBar value={hasAnalysis ? profile.profile_score || 0 : 0} /></div>

                    {hasAnalysis ? (
                        <>
                            <h3 className="mt-5 font-bold">المهارات المستخرجة</h3>
                            <div className="mt-2 flex flex-wrap gap-2">{(profile.extracted_skills || []).map((s: string) => <Badge key={s}>{s}</Badge>)}</div>
                            <h3 className="mt-5 font-bold">المهارات المفقودة</h3>
                            <div className="mt-2 flex flex-wrap gap-2">{(profile.missing_skills || []).map((s: string) => <Badge key={s} tone="gray">{s}</Badge>)}</div>
                            <p className="mt-5 text-slate-600">{profile.education_summary}</p>
                            <p className="mt-2 text-slate-600">{profile.experience_summary}</p>
                        </>
                    ) : (
                        <p className="mt-5 text-sm leading-6 text-slate-500">
                            ارفع السيرة الذاتية لبدء التحليل. ستظهر النتائج هنا فقط عند نجاح تحليل الذكاء الاصطناعي.
                        </p>
                    )}
                </Card>
            </div>
        </DashboardLayout>
    );
}
