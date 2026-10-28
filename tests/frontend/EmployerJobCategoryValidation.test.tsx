import { act, cleanup, fireEvent, render, screen } from '@testing-library/react';
import { router } from '@inertiajs/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const requests = vi.hoisted(() => ({ post: vi.fn(), put: vi.fn() }));

vi.mock('@/Components/Madarat', async () => {
    const React = await import('react');

    return {
        DashboardLayout: ({ children }: { children: React.ReactNode }) => React.createElement('main', null, children),
        Card: ({ children, ...props }: { children: React.ReactNode; className?: string }) => React.createElement('section', props, children),
        Button: ({ children, ...props }: React.ButtonHTMLAttributes<HTMLButtonElement>) => React.createElement('button', props, children),
    };
});

import JobForm from '../../resources/js/Pages/Employer/JobForm';

beforeEach(() => {
    vi.spyOn(router, 'post').mockImplementation(requests.post);
    vi.spyOn(router, 'put').mockImplementation(requests.put);
});

afterEach(() => {
    cleanup();
    vi.restoreAllMocks();
    vi.clearAllMocks();
});

describe('employer category validation with the actual Inertia form hook', () => {
    it.each(['create', 'edit'] as const)('shows server category errors and preserves input during %s', (mode) => {
        const category = { key: 'finance', label: 'المالية', label_en: 'Finance', subcategories: [] };
        const job = mode === 'edit' ? {
            id: 42,
            title: 'وظيفة محفوظة',
            category: 'finance',
            required_skills: [],
            responsibilities: [],
            status: 'draft',
        } : null;
        const view = render(<JobForm job={job} companyVerified categories={[category]} />);
        fireEvent.change(screen.getByLabelText('المسمى الوظيفي'), { target: { value: 'بيانات لم تُحفظ بعد' } });
        fireEvent.click(screen.getByRole('button', { name: 'إرسال للمراجعة' }));

        const request = mode === 'edit' ? requests.put : requests.post;
        expect(request).toHaveBeenCalledOnce();
        const [url, payload, lifecycle] = request.mock.calls[0];
        expect(url).toBe(mode === 'edit' ? '/employer/jobs/42' : '/employer/jobs');
        expect(payload).toEqual(expect.objectContaining({ title: 'بيانات لم تُحفظ بعد', status: 'published' }));

        act(() => lifecycle.onStart({}));
        expect((screen.getAllByRole('button', { name: 'جار الحفظ...' })[0] as HTMLButtonElement).disabled).toBe(true);

        // The redirected server response supplies the current database catalog.
        view.rerender(<JobForm job={job} companyVerified categories={[]} />);
        const message = mode === 'edit'
            ? 'الفئة الوظيفية المحددة لم تعد متاحة. حدّث الصفحة واختر فئة أخرى.'
            : 'الفئة الوظيفية مطلوبة.';
        act(() => lifecycle.onError({ category: message }));

        expect(screen.getByText(message)).toBeTruthy();
        expect((screen.getByLabelText('المسمى الوظيفي') as HTMLInputElement).value).toBe('بيانات لم تُحفظ بعد');
        expect(screen.queryByRole('option', { name: 'المالية' })).toBeNull();
        expect((screen.getByRole('button', { name: 'إرسال للمراجعة' }) as HTMLButtonElement).disabled).toBe(false);
    });
});
