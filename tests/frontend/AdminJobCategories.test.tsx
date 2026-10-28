import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const inertia = vi.hoisted(() => ({
    post: vi.fn(),
    delete: vi.fn(),
    formErrors: {} as Record<string, string>,
}));

vi.mock('@inertiajs/react', async () => {
    const React = await import('react');

    return {
        Head: () => null,
        router: { delete: inertia.delete },
        useForm: (initialData: Record<string, string>) => {
            const [data, updateData] = React.useState(initialData);

            return {
                data,
                setData: (key: string, value: string) => updateData((current) => ({ ...current, [key]: value })),
                post: (url: string, options?: Record<string, unknown>) => inertia.post(url, data, options),
                reset: () => updateData(initialData),
                processing: false,
                errors: inertia.formErrors,
            };
        },
    };
});

vi.mock('@/Components/Madarat', async () => {
    const React = await import('react');

    return {
        DashboardLayout: ({ title, children }: { title: string; children: React.ReactNode }) => React.createElement(
            'main',
            null,
            React.createElement('h1', null, title),
            children,
        ),
        Card: ({ children, ...props }: { children: React.ReactNode; className?: string }) => React.createElement('section', props, children),
        Button: ({ children, ...props }: React.ButtonHTMLAttributes<HTMLButtonElement>) => React.createElement('button', props, children),
    };
});

vi.mock('@/lib/language', () => ({
    useLanguage: () => ({
        locale: 'ar',
        t: (arabic: string) => arabic,
    }),
}));

import JobCategories from '../../resources/js/Pages/Admin/JobCategories';

const categories = [
    {
        id: 1,
        key: 'finance',
        label: 'المالية',
        label_en: 'Finance',
        jobs_count: 3,
        subcategories_count: 4,
    },
    {
        id: 2,
        key: 'technology_programming',
        label: 'التقنية والبرمجة',
        label_en: null,
        jobs_count: 0,
        subcategories_count: 0,
    },
];

beforeEach(() => {
    inertia.formErrors = {};
    vi.spyOn(window, 'confirm').mockReturnValue(true);
});

afterEach(() => {
    cleanup();
    vi.restoreAllMocks();
    vi.clearAllMocks();
});

describe('administrator job category management', () => {
    it('renders category names, linked-job counts, zero counts, and management actions', () => {
        render(<JobCategories categories={categories} />);

        expect(screen.getAllByText('فئات الوظائف').length).toBeGreaterThan(0);
        expect(screen.getByText('المالية')).toBeTruthy();
        expect(screen.getByText('التقنية والبرمجة')).toBeTruthy();
        expect(screen.getByText('3 وظيفة مرتبطة')).toBeTruthy();
        expect(screen.getByText('0 وظيفة مرتبطة')).toBeTruthy();
        expect(screen.getAllByRole('button', { name: 'حذف' })).toHaveLength(2);
        expect(screen.getByRole('button', { name: 'إضافة فئة' })).toBeTruthy();
    });

    it('opens the add form and submits the entered category name once', () => {
        render(<JobCategories categories={categories} />);

        fireEvent.click(screen.getByRole('button', { name: 'إضافة فئة' }));
        const name = screen.getByLabelText('اسم الفئة');
        fireEvent.change(name, { target: { value: '  التقنية والبرمجة  ' } });
        fireEvent.click(screen.getByRole('button', { name: 'حفظ' }));

        expect(inertia.post).toHaveBeenCalledTimes(1);
        expect(inertia.post).toHaveBeenCalledWith(
            '/admin/job-categories',
            { name: '  التقنية والبرمجة  ' },
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('does not delete when confirmation is cancelled', () => {
        vi.mocked(window.confirm).mockReturnValue(false);
        render(<JobCategories categories={categories} />);

        fireEvent.click(screen.getAllByRole('button', { name: 'حذف' })[0]);

        expect(window.confirm).toHaveBeenCalledOnce();
        expect(inertia.delete).not.toHaveBeenCalled();
    });

    it('confirms deletion and prevents duplicate requests while it is pending', () => {
        render(<JobCategories categories={categories} />);

        const deleteButton = screen.getAllByRole('button', { name: 'حذف' })[0];
        fireEvent.click(deleteButton);
        fireEvent.click(deleteButton);

        expect(window.confirm).toHaveBeenCalledOnce();
        expect(inertia.delete).toHaveBeenCalledTimes(1);
        expect(inertia.delete).toHaveBeenCalledWith(
            '/admin/job-categories/1',
            expect.objectContaining({ preserveScroll: true }),
        );
        expect((screen.getByRole('button', { name: 'جاري الحذف...' }) as HTMLButtonElement).disabled).toBe(true);
    });

    it('shows server validation errors for the category name', () => {
        inertia.formErrors = { name: 'اسم الفئة مستخدم بالفعل.' };
        render(<JobCategories categories={categories} />);

        fireEvent.click(screen.getByRole('button', { name: 'إضافة فئة' }));

        expect(screen.getByText('اسم الفئة مستخدم بالفعل.')).toBeTruthy();
    });

    it('shows a clear empty state when the database has no categories', () => {
        render(<JobCategories categories={[]} />);

        expect(screen.getByText('لا توجد فئات وظيفية حاليًا.')).toBeTruthy();
    });
});
