import { cleanup, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';

const inertia = vi.hoisted(() => ({
    post: vi.fn(),
}));

vi.mock('@inertiajs/react', async () => {
    const React = await import('react');

    return {
        Link: ({ href, children, ...props }: any) => React.createElement('a', { href, ...props }, children),
        router: { post: inertia.post },
        useForm: () => ({
            data: { cover_letter: '' },
            setData: vi.fn(),
            processing: false,
        }),
        usePage: () => ({ props: { auth: { user: { role: 'job_seeker' } } } }),
    };
});

vi.mock('@/Components/Madarat', async () => {
    const React = await import('react');
    const Icon = () => React.createElement('span');

    return {
        AppLayout: ({ children }: { children: React.ReactNode }) => React.createElement('main', null, children),
        Badge: ({ children }: { children: React.ReactNode }) => React.createElement('span', null, children),
        Button: ({ children, ...props }: React.ButtonHTMLAttributes<HTMLButtonElement>) => React.createElement('button', props, children),
        Card: ({ children, ...props }: { children: React.ReactNode; className?: string }) => React.createElement('section', props, children),
        CompanyVerificationBadge: ({ status }: { status?: string }) => status === 'verified' ? React.createElement('span', null, 'موثقة') : null,
        DashboardLayout: ({ children }: { children: React.ReactNode }) => React.createElement('main', null, children),
        ProgressBar: ({ value }: { value: number }) => React.createElement('div', { 'data-value': value }),
        StatCard: ({ label, value }: { label: string; value: string | number }) => React.createElement('div', null, `${label}: ${value}`),
        icons: { BriefcaseBusiness: Icon, CheckCircle2: Icon },
    };
});

import JobSeekerDashboard from '../../resources/js/Pages/Seeker/Dashboard';
import JobShow from '../../resources/js/Pages/Jobs/Show';

const job = {
    id: 17,
    title: 'مندوب ترويج',
    slug: 'promotions-representative',
    description: 'وصف كامل وفريد للوظيفة',
    location: 'موقع تفصيلي فريد',
    job_type: 'نمط عمل فريد',
    contract_type: 'نوع عقد فريد',
    experience_level: 'مستوى خبرة فريد',
    salary_min: 2500,
    salary_max: 3500,
    required_skills: ['مهارة فريدة'],
    responsibilities: ['مسؤولية كاملة وفريدة'],
    company_profile: {
        company_name: 'شركة الفرنسي للعطور',
        verification_status: 'verified',
    },
    match: {
        score: 91,
        summary: 'ملخص مطابقة فريد',
    },
};

afterEach(() => {
    cleanup();
    vi.clearAllMocks();
});

describe('Job Seeker dashboard job presentation', () => {
    it('keeps each recommended-job card limited to the title, company, and details action', () => {
        render(
            <JobSeekerDashboard
                profile={{ cv_status: 'pending', profile_score: 0, extracted_skills: [] }}
                recommendedJobs={[job]}
                applicationCount={0}
                interviewCount={0}
            />,
        );

        expect(screen.getByText(job.title)).toBeTruthy();
        expect(screen.getByText(job.company_profile.company_name)).toBeTruthy();
        expect(screen.getByRole('link', { name: 'عرض التفاصيل' }).getAttribute('href'))
            .toBe(`/jobs/${job.slug}`);

        expect(screen.queryByText(job.description)).toBeNull();
        expect(screen.queryByText(job.location)).toBeNull();
        expect(screen.queryByText(job.job_type)).toBeNull();
        expect(screen.queryByText(job.contract_type)).toBeNull();
        expect(screen.queryByText(job.experience_level)).toBeNull();
        expect(screen.queryByText(String(job.salary_min))).toBeNull();
        expect(screen.queryByText(String(job.salary_max))).toBeNull();
        expect(screen.queryByText(job.required_skills[0])).toBeNull();
        expect(screen.queryByText(job.responsibilities[0])).toBeNull();
        expect(screen.queryByText(`${job.match.score}%`)).toBeNull();
        expect(screen.queryByText(job.match.summary)).toBeNull();
        expect(screen.queryByText('موثقة')).toBeNull();
    });

    it('keeps all supported job information available in the details view', () => {
        render(<JobShow job={job} match={job.match} alreadyApplied={false} />);

        expect(screen.getByText(job.title)).toBeTruthy();
        expect(screen.getByText(job.company_profile.company_name)).toBeTruthy();
        expect(screen.getByText(job.description)).toBeTruthy();
        expect(screen.getByText(job.location)).toBeTruthy();
        expect(screen.getByText(job.job_type)).toBeTruthy();
        expect(screen.getByText(job.contract_type)).toBeTruthy();
        expect(screen.getByText(job.experience_level)).toBeTruthy();
        expect(screen.getByText(String(job.salary_min))).toBeTruthy();
        expect(screen.getByText(String(job.salary_max))).toBeTruthy();
        expect(screen.getByText(job.required_skills[0])).toBeTruthy();
        expect(screen.getByText(job.responsibilities[0])).toBeTruthy();
        expect(screen.getByText(job.match.summary)).toBeTruthy();
    });
});
