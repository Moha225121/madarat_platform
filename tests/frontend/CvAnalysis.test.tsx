import { act, cleanup, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';

const inertia = vi.hoisted(() => ({
    post: vi.fn(),
    reload: vi.fn(),
    processing: false,
}));

vi.mock('@inertiajs/react', () => ({
    router: { reload: inertia.reload },
    useForm: () => ({
        data: { cv: null },
        setData: vi.fn(),
        post: inertia.post,
        processing: inertia.processing,
        errors: {},
        setError: vi.fn(),
        clearErrors: vi.fn(),
    }),
}));

vi.mock('@/Components/Madarat', async () => {
    const React = await import('react');

    return {
        Badge: ({ children }: { children: React.ReactNode }) => React.createElement('span', null, children),
        Button: ({ children, ...props }: React.ButtonHTMLAttributes<HTMLButtonElement>) => React.createElement('button', props, children),
        Card: ({ children }: { children: React.ReactNode }) => React.createElement('section', null, children),
        DashboardLayout: ({ children }: { children: React.ReactNode }) => React.createElement('main', null, children),
        ProgressBar: ({ value }: { value: number }) => React.createElement('div', { 'data-testid': 'score-progress', 'data-value': value }),
    };
});

import CvAnalysis from '../../resources/js/Pages/Seeker/CvAnalysis';

const analyzedProfile = {
    cv_status: 'analyzed' as const,
    profile_score: 87,
    extracted_skills: ['Laravel', 'React'],
    missing_skills: ['TypeScript'],
    education_summary: 'بكالوريوس في علوم الحاسوب',
    experience_summary: 'خبرة في تطوير تطبيقات الويب',
    ai_recommendations: {
        strengths: ['حل المشكلات', 'العمل الجماعي'],
        recommendations: ['تعلم TypeScript', 'إضافة نتائج قابلة للقياس'],
    },
};

afterEach(() => {
    cleanup();
    inertia.processing = false;
    vi.clearAllMocks();
    vi.useRealTimers();
});

describe('CV analysis page', () => {
    it('renders every field from a successful structured analysis', () => {
        render(<CvAnalysis profile={analyzedProfile} />);

        expect(screen.getByText('تم تحليل السيرة الذاتية بنجاح.')).toBeTruthy();
        expect(screen.getByText('التقييم العام: 87%')).toBeTruthy();
        expect(screen.getByTestId('score-progress').getAttribute('data-value')).toBe('87');
        expect(screen.getByText('Laravel')).toBeTruthy();
        expect(screen.getByText('React')).toBeTruthy();
        expect(screen.getByText('TypeScript')).toBeTruthy();
        expect(screen.getByText('ملخص التعليم')).toBeTruthy();
        expect(screen.getByText('بكالوريوس في علوم الحاسوب')).toBeTruthy();
        expect(screen.getByText('ملخص الخبرة')).toBeTruthy();
        expect(screen.getByText('خبرة في تطوير تطبيقات الويب')).toBeTruthy();
        expect(screen.getByText('نقاط القوة')).toBeTruthy();
        expect(screen.getByText('حل المشكلات')).toBeTruthy();
        expect(screen.getByText('العمل الجماعي')).toBeTruthy();
        expect(screen.getByText('التوصيات')).toBeTruthy();
        expect(screen.getByText('تعلم TypeScript')).toBeTruthy();
        expect(screen.getByText('إضافة نتائج قابلة للقياس')).toBeTruthy();
    });

    it('shows upload progress and disables duplicate submission during the upload', () => {
        inertia.processing = true;
        const { container } = render(<CvAnalysis profile={{ cv_status: 'pending' }} />);

        expect(screen.getByRole('button').textContent).toBe('جاري رفع السيرة الذاتية...');
        expect((screen.getByRole('button') as HTMLButtonElement).disabled).toBe(true);
        expect((container.querySelector('input[type="file"]') as HTMLInputElement).disabled).toBe(true);
    });

    it('polls only while AI analysis is processing and disables another upload', () => {
        vi.useFakeTimers();
        const { container, rerender } = render(<CvAnalysis profile={{ cv_status: 'processing' }} />);

        expect(screen.getByText('جاري تحليل السيرة الذاتية بالذكاء الاصطناعي. قد تستغرق العملية قليلًا.')).toBeTruthy();
        expect((screen.getByRole('button') as HTMLButtonElement).disabled).toBe(true);
        expect((container.querySelector('input[type="file"]') as HTMLInputElement).disabled).toBe(true);

        act(() => vi.advanceTimersByTime(5000));
        expect(inertia.reload).toHaveBeenCalledTimes(1);
        expect(inertia.reload).toHaveBeenCalledWith({ only: ['profile'] });

        rerender(<CvAnalysis profile={analyzedProfile} />);
        act(() => vi.advanceTimersByTime(5000));
        expect(inertia.reload).toHaveBeenCalledTimes(1);
    });

    it('shows only whitelisted safe failures and enables retry after failure', () => {
        const { rerender } = render(<CvAnalysis profile={{
            cv_status: 'failed',
            cv_analysis_error_code: 'invalid_request',
        }} />);

        expect(screen.getByText('تعذر قراءة ملف السيرة الذاتية. تأكد من أن الملف غير تالف وبصيغة PDF أو DOC أو DOCX ثم حاول مرة أخرى.')).toBeTruthy();
        expect(screen.getByRole('button').textContent).toBe('إعادة تحليل السيرة الذاتية');
        expect((screen.getByRole('button') as HTMLButtonElement).disabled).toBe(false);

        rerender(<CvAnalysis profile={{ cv_status: 'failed', cv_analysis_error_code: 'timeout' }} />);
        expect(screen.getByText('لم يكتمل التحليل بسبب مشكلة مؤقتة في خدمة الذكاء الاصطناعي. يرجى إعادة المحاولة.')).toBeTruthy();

        rerender(<CvAnalysis profile={{ cv_status: 'failed', cv_analysis_error_code: 'raw-provider-message' }} />);
        expect(screen.getByText('خدمة تحليل السيرة الذاتية غير متاحة حاليًا. يرجى المحاولة لاحقًا أو التواصل مع إدارة المنصة.')).toBeTruthy();
        expect(screen.queryByText('raw-provider-message')).toBeNull();
    });
});
