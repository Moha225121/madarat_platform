import { cleanup, fireEvent, render, screen, within } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';

const inertia = vi.hoisted(() => ({
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
}));

vi.mock('@inertiajs/react', async () => {
    const React = await import('react');

    return {
        Link: ({ href, children, ...props }: any) => React.createElement('a', { href, ...props }, children),
        router: inertia,
        useForm: (initialData: Record<string, any>) => {
            const [data, updateData] = React.useState(initialData);
            const transform = React.useRef((value: Record<string, any>) => value);
            const setData = (keyOrData: string | Record<string, any>, value?: any) => {
                if (typeof keyOrData === 'string') {
                    updateData((current) => ({ ...current, [keyOrData]: value }));
                } else {
                    updateData(keyOrData);
                }
            };

            return {
                data,
                setData,
                get: (url: string, options?: Record<string, any>) => inertia.get(url, data, options),
                transform: (callback: (value: Record<string, any>) => Record<string, any>) => { transform.current = callback; },
                post: (url: string) => inertia.post(url, transform.current(data)),
                put: (url: string) => inertia.put(url, transform.current(data)),
                processing: false,
                errors: {},
            };
        },
    };
});

vi.mock('@/Components/Madarat', async () => {
    const React = await import('react');

    return {
        AppLayout: ({ children }: { children: React.ReactNode }) => React.createElement('main', null, children),
        DashboardLayout: ({ children }: { children: React.ReactNode }) => React.createElement('main', null, children),
        Card: ({ children, ...props }: { children: React.ReactNode; className?: string }) => React.createElement('section', props, children),
        Badge: ({ children }: { children: React.ReactNode }) => React.createElement('span', null, children),
        Button: ({ children, ...props }: React.ButtonHTMLAttributes<HTMLButtonElement>) => React.createElement('button', props, children),
        EmptyState: ({ title, text }: { title: string; text: string }) => React.createElement('section', null,
            React.createElement('h2', null, title),
            React.createElement('p', null, text),
        ),
        JobCard: ({ job }: { job: any }) => React.createElement('article', { 'data-testid': `job-${job.id}` },
            React.createElement('h3', null, job.title),
            React.createElement('p', null, job.company_profile?.company_name),
        ),
        Pagination: ({ paginator }: { paginator: any }) => React.createElement('nav', { 'aria-label': 'pagination' },
            (paginator.links || []).filter((link: any) => link.url).map((link: any) => (
                React.createElement('a', { key: link.label, href: link.url }, link.label)
            )),
        ),
    };
});

import JobForm from '../../resources/js/Pages/Employer/JobForm';
import JobsIndex from '../../resources/js/Pages/Jobs/Index';

const categories = [
    {
        key: 'finance',
        label: 'المالية',
        label_en: 'Finance',
        count: 1,
        subcategories: [
            { key: 'accounting', label: 'المحاسبة', label_en: 'Accounting', count: 1 },
            { key: 'treasury', label: 'الخزينة', label_en: 'Treasury', count: 0 },
        ],
    },
    {
        key: 'marketing_sales',
        label: 'التسويق والمبيعات',
        label_en: 'Marketing and sales',
        count: 1,
        subcategories: [
            { key: 'sales', label: 'المبيعات', label_en: 'Sales', count: 0 },
            { key: 'promotion_representatives', label: 'المناديب والترويج', label_en: 'Representatives and promotion', count: 0 },
            { key: 'digital_marketing', label: 'التسويق الرقمي', label_en: 'Digital marketing', count: 1 },
        ],
    },
    { key: 'content_design_media', label: 'المحتوى والتصميم والتصوير', label_en: 'Content, design and media', count: 0, subcategories: [] },
    { key: 'administration_services', label: 'الإدارة والخدمات', label_en: 'Administration and services', count: 0, subcategories: [] },
    { key: 'medical', label: 'المجال الطبي', label_en: 'Medical', count: 0, subcategories: [] },
    { key: 'hospitality_catering_travel', label: 'الضيافة والتموين والسفر', label_en: 'Hospitality, catering and travel', count: 0, subcategories: [] },
    { key: 'education', label: 'التعليم', label_en: 'Education', count: 0, subcategories: [] },
    { key: 'transport_delivery', label: 'النقل والتوصيل', label_en: 'Transport and delivery', count: 0, subcategories: [] },
];

const categorizedJob = {
    id: 1,
    title: 'محاسب',
    slug: 'accountant',
    description: 'وصف وظيفة المحاسب',
    category: 'finance',
    subcategory: 'accounting',
    category_label: 'المالية',
    subcategory_label: 'المحاسبة',
    company_profile: { company_name: 'شركة الحسابات' },
};

const legacyJob = {
    id: 2,
    title: 'وظيفة غامضة',
    slug: 'legacy-job',
    description: 'وصف وظيفة قديمة',
    category: null,
    subcategory: null,
    category_label: null,
    subcategory_label: null,
    company_profile: { company_name: 'شركة قديمة' },
};

const emptyFilters = {
    search: '',
    location: '',
    job_type: '',
    experience_level: '',
    category: '',
    subcategory: '',
};

function paginator(data: any[]) {
    return {
        current_page: 1,
        data,
        first_page_url: '/jobs?page=1',
        from: data.length ? 1 : null,
        last_page: 1,
        last_page_url: '/jobs?page=1',
        links: [
            { url: null, label: '&laquo; السابق', active: false },
            { url: '/jobs?page=1', label: '1', active: true },
            { url: null, label: 'التالي &raquo;', active: false },
        ],
        next_page_url: null,
        path: '/jobs',
        per_page: 12,
        prev_page_url: null,
        to: data.length,
        total: data.length,
    };
}

afterEach(() => {
    cleanup();
    vi.clearAllMocks();
});

describe('category-based job browsing', () => {
    it('renders all eight categories and groups the default results with an uncategorized fallback', () => {
        render(
            <JobsIndex
                jobs={paginator([categorizedJob, legacyJob])}
                categories={categories}
                counts={{ total: 2, other: 1 }}
                filters={emptyFilters}
            />,
        );

        expect(screen.getByRole('heading', { name: 'تصفح الوظائف حسب الفئة' })).toBeTruthy();
        expect(screen.getByRole('button', { name: /جميع الوظائف/ }).getAttribute('aria-pressed')).toBe('true');

        for (const category of categories) {
            expect(screen.getByRole('button', { name: new RegExp(category.label) })).toBeTruthy();
        }

        expect(screen.getByRole('heading', { name: /المالية/ })).toBeTruthy();
        expect(screen.getByRole('heading', { name: /وظائف أخرى/ })).toBeTruthy();
        expect(screen.getByText(categorizedJob.title)).toBeTruthy();
        expect(screen.getByText(legacyJob.title)).toBeTruthy();
        expect(screen.getByText('المحاسبة')).toBeTruthy();
    });

    it('selects categories, displays only related subcategories, and resets subcategory on a main-category change', () => {
        render(
            <JobsIndex
                jobs={paginator([categorizedJob])}
                categories={categories}
                counts={{ total: 2, other: 1 }}
                filters={{ ...emptyFilters, category: 'finance' }}
            />,
        );

        const finance = screen.getByRole('button', { name: /المالية/ });
        expect(finance.getAttribute('aria-pressed')).toBe('true');
        expect(screen.getByRole('button', { name: /الكل/ })).toBeTruthy();
        expect(screen.getByRole('button', { name: /المحاسبة/ })).toBeTruthy();
        expect(screen.queryByRole('button', { name: /التسويق الرقمي/ })).toBeNull();

        fireEvent.click(screen.getByRole('button', { name: /المحاسبة/ }));
        expect(inertia.get).toHaveBeenLastCalledWith(
            '/jobs',
            expect.objectContaining({ category: 'finance', subcategory: 'accounting' }),
            expect.objectContaining({ preserveState: true, preserveScroll: true, replace: true }),
        );

        fireEvent.click(screen.getByRole('button', { name: /التسويق والمبيعات/ }));
        expect(inertia.get).toHaveBeenLastCalledWith(
            '/jobs',
            expect.objectContaining({ category: 'marketing_sales', subcategory: '' }),
            expect.objectContaining({ preserveState: true, preserveScroll: true, replace: true }),
        );
    });

    it('shows the selected subcategory count beside filtered results', () => {
        const countedCategories = categories.map((category) => category.key === 'finance'
            ? {
                ...category,
                count: 7,
                subcategories: category.subcategories.map((subcategory) => subcategory.key === 'accounting'
                    ? { ...subcategory, count: 1 }
                    : subcategory),
            }
            : category);

        render(
            <JobsIndex
                jobs={paginator([categorizedJob])}
                categories={countedCategories}
                counts={{ total: 8, other: 1 }}
                filters={{ ...emptyFilters, category: 'finance', subcategory: 'accounting' }}
            />,
        );

        const resultHeading = screen.getByRole('heading', { name: 'المالية' });
        expect(within(resultHeading.parentElement as HTMLElement).getByText('1 وظيفة')).toBeTruthy();
    });

    it('submits existing filters with search and can clear all filters', () => {
        render(
            <JobsIndex
                jobs={paginator([])}
                categories={categories}
                counts={{ total: 0, other: 0 }}
                filters={{
                    ...emptyFilters,
                    location: 'طرابلس',
                    job_type: 'دوام كامل',
                    category: 'finance',
                    subcategory: 'accounting',
                }}
            />,
        );

        fireEvent.change(screen.getByPlaceholderText('العنوان أو الشركة'), { target: { value: 'محاسب' } });
        fireEvent.click(screen.getByRole('button', { name: 'بحث' }));

        expect(inertia.get).toHaveBeenLastCalledWith(
            '/jobs',
            expect.objectContaining({
                search: 'محاسب',
                location: 'طرابلس',
                job_type: 'دوام كامل',
                category: 'finance',
                subcategory: 'accounting',
            }),
            expect.anything(),
        );

        fireEvent.click(screen.getAllByRole('button', { name: 'مسح الفلاتر' })[0]);
        expect(inertia.get).toHaveBeenLastCalledWith(
            '/jobs',
            emptyFilters,
            expect.objectContaining({ preserveState: true, preserveScroll: true, replace: true }),
        );
    });

    it('uses the requested category and search empty states', () => {
        const { unmount } = render(
            <JobsIndex
                jobs={paginator([])}
                categories={categories}
                counts={{ total: 2, other: 1 }}
                filters={{ ...emptyFilters, category: 'finance' }}
            />,
        );

        expect(screen.getByText('لا توجد وظائف في هذه الفئة حاليًا')).toBeTruthy();

        unmount();

        render(
            <JobsIndex
                jobs={paginator([])}
                categories={categories}
                counts={{ total: 0, other: 0 }}
                filters={{ ...emptyFilters, search: 'نتيجة غير موجودة' }}
            />,
        );

        expect(screen.getByText('لا توجد وظائف مطابقة لبحثك')).toBeTruthy();
        expect(screen.getAllByRole('button', { name: 'مسح الفلاتر' }).length).toBeGreaterThan(0);
    });

    it('renders an arbitrary database-provided category without frontend code changes', () => {
        const dynamicCategory = {
            key: 'technology_programming',
            label: 'التقنية والبرمجة',
            label_en: 'Technology and programming',
            count: 1,
            subcategories: [],
        };
        const dynamicJob = {
            ...categorizedJob,
            id: 3,
            title: 'مطور تطبيقات',
            category: dynamicCategory.key,
            subcategory: null,
            category_label: dynamicCategory.label,
            subcategory_label: null,
        };

        render(
            <JobsIndex
                jobs={paginator([dynamicJob])}
                categories={[...categories, dynamicCategory]}
                counts={{ total: 1, other: 0 }}
                filters={emptyFilters}
            />,
        );

        expect(screen.getByRole('button', { name: /التقنية والبرمجة/ })).toBeTruthy();
        expect(screen.getByRole('heading', { name: 'التقنية والبرمجة' })).toBeTruthy();
        expect(screen.getByText('مطور تطبيقات')).toBeTruthy();
    });
});

describe('job category fields', () => {
    it('shows only related subcategories and clears an incompatible selection when the main category changes', () => {
        render(<JobForm job={null} companyVerified={false} categories={categories} />);

        const category = screen.getByLabelText('الفئة الوظيفية') as HTMLSelectElement;
        const subcategory = screen.getByLabelText('الفئة الفرعية') as HTMLSelectElement;

        expect(category.value).toBe('');
        expect(subcategory.disabled).toBe(true);

        fireEvent.change(category, { target: { value: 'finance' } });
        expect(subcategory.disabled).toBe(false);
        expect(within(subcategory).getByRole('option', { name: 'المحاسبة' })).toBeTruthy();
        expect(within(subcategory).queryByRole('option', { name: 'التسويق الرقمي' })).toBeNull();

        fireEvent.change(subcategory, { target: { value: 'accounting' } });
        expect(subcategory.value).toBe('accounting');

        fireEvent.change(category, { target: { value: 'marketing_sales' } });
        expect(subcategory.value).toBe('');
        expect(within(subcategory).getByRole('option', { name: 'التسويق الرقمي' })).toBeTruthy();
        expect(within(subcategory).queryByRole('option', { name: 'المحاسبة' })).toBeNull();
    });

    it('includes the stable category keys when submitting a job', () => {
        render(<JobForm job={null} companyVerified={false} categories={categories} />);

        fireEvent.change(screen.getByLabelText('الفئة الوظيفية'), { target: { value: 'marketing_sales' } });
        fireEvent.change(screen.getByLabelText('الفئة الفرعية'), { target: { value: 'digital_marketing' } });
        fireEvent.click(screen.getByRole('button', { name: 'إرسال للمراجعة' }));

        expect(inertia.post).toHaveBeenCalledWith(
            '/employer/jobs',
            expect.objectContaining({
                category: 'marketing_sales',
                subcategory: 'digital_marketing',
                status: 'published',
            }),
        );
    });

    it('restores persisted category assignments when editing', () => {
        render(
            <JobForm
                companyVerified
                categories={categories}
                job={{
                    ...categorizedJob,
                    status: 'draft',
                    required_skills: [],
                    responsibilities: [],
                }}
            />,
        );

        expect((screen.getByLabelText('الفئة الوظيفية') as HTMLSelectElement).value).toBe('finance');
        expect((screen.getByLabelText('الفئة الفرعية') as HTMLSelectElement).value).toBe('accounting');
    });

    it('allows a database-provided category that has no subcategories', () => {
        const dynamicCategory = {
            key: 'technology_programming',
            label: 'التقنية والبرمجة',
            label_en: 'Technology and programming',
            count: 0,
            subcategories: [],
        };

        render(<JobForm job={null} companyVerified categories={[...categories, dynamicCategory]} />);

        const category = screen.getByLabelText('الفئة الوظيفية') as HTMLSelectElement;
        const subcategory = screen.getByLabelText('الفئة الفرعية') as HTMLSelectElement;

        expect(screen.getByRole('option', { name: 'اختر الفئة الوظيفية' })).toBeTruthy();
        fireEvent.change(category, { target: { value: dynamicCategory.key } });

        expect(category.value).toBe(dynamicCategory.key);
        expect(subcategory.disabled).toBe(false);
        expect(subcategory.value).toBe('');

        expect(screen.getByText('شركتك موثقة، وستُرسل الوظيفة لمراجعة الإدارة قبل النشر.')).toBeTruthy();
        expect(screen.queryByText('شركتك موثقة، سيتم نشر الوظيفة مباشرة عند الإرسال.')).toBeNull();
        fireEvent.click(screen.getByRole('button', { name: 'إرسال للمراجعة' }));
        expect(inertia.post).toHaveBeenCalledWith(
            '/employer/jobs',
            expect.objectContaining({
                category: dynamicCategory.key,
                subcategory: '',
                status: 'published',
            }),
        );
    });
});
