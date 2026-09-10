import { router, useForm } from '@inertiajs/react';
import { useEffect } from 'react';
import { Badge, Button, Card, DashboardLayout, ProgressBar } from '@/Components/Madarat';

type CvStatus = 'pending' | 'processing' | 'analyzed' | 'failed';

type CvAnalysisProfile = {
    cv_status: CvStatus;
    cv_analysis_error_code?: string | null;
    profile_score?: number | null;
    extracted_skills?: string[] | null;
    missing_skills?: string[] | null;
    education_summary?: string | null;
    experience_summary?: string | null;
    ai_recommendations?: {
        strengths?: string[] | null;
        recommendations?: string[] | null;
    } | null;
};

type CvAnalysisProps = {
    profile: CvAnalysisProfile;
};

const invalidFileErrorCodes = new Set([
    'invalid_request',
    'missing_file',
]);

const temporaryErrorCodes = new Set([
    'connection_failed',
    'timeout',
    'rate_limited',
    'provider_unavailable',
    'incomplete_response',
    'refusal',
    'empty_response',
    'invalid_structured_output',
]);

function failureMessage(errorCode?: string | null): string {
    if (errorCode && invalidFileErrorCodes.has(errorCode)) {
        return 'تعذر قراءة ملف السيرة الذاتية. تأكد من أن الملف غير تالف وبصيغة PDF أو DOC أو DOCX ثم حاول مرة أخرى.';
    }

    if (errorCode && temporaryErrorCodes.has(errorCode)) {
        return 'لم يكتمل التحليل بسبب مشكلة مؤقتة في خدمة الذكاء الاصطناعي. يرجى إعادة المحاولة.';
    }

    return 'خدمة تحليل السيرة الذاتية غير متاحة حاليًا. يرجى المحاولة لاحقًا أو التواصل مع إدارة المنصة.';
}

export default function CvAnalysis({ profile }: CvAnalysisProps) {
    const { data, setData, post, processing, errors, setError, clearErrors } = useForm<{ cv: File | null }>({ cv: null });
    const hasAnalysis = profile.cv_status === 'analyzed';
    const isAnalyzing = profile.cv_status === 'processing';
    const hasFailed = profile.cv_status === 'failed';
    const isBusy = processing || isAnalyzing;
    const maxFileSize = 15 * 1024 * 1024;
    const profileScore = hasAnalysis ? (profile.profile_score ?? 0) : 0;
    const extractedSkills = Array.isArray(profile.extracted_skills) ? profile.extracted_skills : [];
    const missingSkills = Array.isArray(profile.missing_skills) ? profile.missing_skills : [];
    const strengths = Array.isArray(profile.ai_recommendations?.strengths) ? profile.ai_recommendations.strengths : [];
    const recommendations = Array.isArray(profile.ai_recommendations?.recommendations) ? profile.ai_recommendations.recommendations : [];

    useEffect(() => {
        if (!isAnalyzing) {
            return;
        }

        const interval = window.setInterval(() => {
            router.reload({ only: ['profile'] });
        }, 5000);

        return () => window.clearInterval(interval);
    }, [isAnalyzing]);

    return (
        <DashboardLayout title="تحليل السيرة الذاتية">
            <div className="grid gap-5 lg:grid-cols-[360px_1fr]">
                <Card>
                    <form onSubmit={(e) => {
                        e.preventDefault();

                        if (isBusy) {
                            return;
                        }

                        if (!data.cv) {
                            setError('cv', 'يرجى اختيار ملف السيرة الذاتية.');
                            return;
                        }

                        if (data.cv.size > maxFileSize) {
                            setError('cv', 'يجب ألا يتجاوز حجم السيرة الذاتية 15 ميجابايت.');
                            return;
                        }

                        post('/seeker/cv-analysis');
                    }}>
                        <input
                            type="file"
                            accept=".pdf,.doc,.docx"
                            disabled={isBusy}
                            onChange={(e) => {
                                const file = e.target.files?.[0] || null;
                                clearErrors('cv');
                                setData('cv', file);

                                if (file && file.size > maxFileSize) {
                                    setError('cv', 'يجب ألا يتجاوز حجم السيرة الذاتية 15 ميجابايت.');
                                }
                            }}
                            className="w-full rounded-lg border border-slate-200 p-2 disabled:cursor-not-allowed disabled:bg-slate-50 disabled:opacity-60"
                        />
                        <p className="mt-2 text-xs text-slate-500">PDF أو DOC أو DOCX — بحد أقصى 15 ميجابايت.</p>
                        {errors.cv && <p className="mt-2 text-sm font-bold text-red-600">{errors.cv}</p>}
                        <Button disabled={isBusy} className="mt-4 w-full">
                            {processing
                                ? 'جاري رفع السيرة الذاتية...'
                                : isAnalyzing
                                    ? 'جاري التحليل...'
                                    : hasFailed
                                        ? 'إعادة تحليل السيرة الذاتية'
                                        : 'تحليل السيرة الذاتية'}
                        </Button>
                    </form>

                    {hasFailed && (
                        <div className="mt-4 rounded-lg bg-red-50 p-3 text-sm font-bold text-red-700" role="alert">
                            <p>{failureMessage(profile.cv_analysis_error_code)}</p>
                            <p className="mt-2">اختر ملف السيرة الذاتية ثم أعد المحاولة.</p>
                        </div>
                    )}

                    {isAnalyzing && (
                        <p className="mt-4 rounded-lg bg-cyan-50 p-3 text-sm font-bold text-madarat-blue" role="status" aria-live="polite">
                            جاري تحليل السيرة الذاتية بالذكاء الاصطناعي. قد تستغرق العملية قليلًا.
                        </p>
                    )}

                    {hasAnalysis && (
                        <p className="mt-4 rounded-lg bg-emerald-50 p-3 text-sm font-bold text-emerald-700" role="status">
                            تم تحليل السيرة الذاتية بنجاح.
                        </p>
                    )}
                </Card>

                <Card>
                    <h2 className="font-black text-madarat-navy">التقييم العام: {profileScore}%</h2>
                    <div className="mt-3"><ProgressBar value={profileScore} /></div>

                    {hasAnalysis ? (
                        <>
                            <h3 className="mt-5 font-bold">المهارات المستخرجة</h3>
                            <div className="mt-2 flex flex-wrap gap-2">
                                {extractedSkills.map((skill, index) => <Badge key={`${skill}-${index}`}>{skill}</Badge>)}
                            </div>

                            <h3 className="mt-5 font-bold">المهارات المفقودة</h3>
                            <div className="mt-2 flex flex-wrap gap-2">
                                {missingSkills.map((skill, index) => <Badge key={`${skill}-${index}`} tone="gray">{skill}</Badge>)}
                            </div>

                            <h3 className="mt-5 font-bold">ملخص التعليم</h3>
                            <p className="mt-2 whitespace-pre-line text-slate-600">{profile.education_summary}</p>

                            <h3 className="mt-5 font-bold">ملخص الخبرة</h3>
                            <p className="mt-2 whitespace-pre-line text-slate-600">{profile.experience_summary}</p>

                            <h3 className="mt-5 font-bold">نقاط القوة</h3>
                            <ul className="mt-2 list-disc space-y-2 pr-5 text-slate-600">
                                {strengths.map((strength, index) => <li key={`${strength}-${index}`}>{strength}</li>)}
                            </ul>

                            <h3 className="mt-5 font-bold">التوصيات</h3>
                            <ul className="mt-2 list-disc space-y-2 pr-5 text-slate-600">
                                {recommendations.map((recommendation, index) => <li key={`${recommendation}-${index}`}>{recommendation}</li>)}
                            </ul>
                        </>
                    ) : isAnalyzing ? (
                        <p className="mt-5 text-sm leading-6 text-slate-500">
                            تم رفع الملف بنجاح، والتحليل قيد التنفيذ الآن.
                        </p>
                    ) : hasFailed ? (
                        <p className="mt-5 text-sm leading-6 text-slate-500">
                            اختر ملف السيرة الذاتية ثم أعد المحاولة.
                        </p>
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
