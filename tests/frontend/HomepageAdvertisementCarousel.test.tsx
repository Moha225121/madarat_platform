import { act, cleanup, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('@/Components/Madarat', async () => {
    const React = await import('react');

    return {
        AssistantRobot: () => React.createElement('span', null, 'روبوت مدارات'),
        MadaratLogo: () => React.createElement('span', null, 'شعار مدارات'),
    };
});

vi.mock('@/lib/language', () => ({
    useLanguage: () => ({
        locale: 'ar',
        t: (arabic: string) => arabic,
    }),
}));

import HomepageAdvertisementCarousel, {
    HomepageAdvertisement,
} from '../../resources/js/Components/HomepageAdvertisementCarousel';

const advertisements: HomepageAdvertisement[] = [
    { id: 1, image_url: '/storage/homepage-advertisements/first.jpg', alt_text: 'الإعلان الأول' },
    { id: 2, image_url: '/storage/homepage-advertisements/second.png', alt_text: 'الإعلان الثاني' },
    { id: 3, image_url: '/storage/homepage-advertisements/third.webp', alt_text: 'الإعلان الثالث' },
];

let documentVisibility: DocumentVisibilityState;
let reducedMotion = false;

function activeImageAlt() {
    return document
        .querySelector('[role="group"][aria-hidden="false"] img')
        ?.getAttribute('alt');
}

function setDocumentVisibility(visibility: DocumentVisibilityState) {
    documentVisibility = visibility;
    act(() => document.dispatchEvent(new Event('visibilitychange')));
}

beforeEach(() => {
    documentVisibility = 'visible';
    reducedMotion = false;

    Object.defineProperty(document, 'visibilityState', {
        configurable: true,
        get: () => documentVisibility,
    });

    Object.defineProperty(window, 'matchMedia', {
        configurable: true,
        writable: true,
        value: vi.fn().mockImplementation((query: string) => ({
            matches: reducedMotion,
            media: query,
            onchange: null,
            addEventListener: vi.fn(),
            removeEventListener: vi.fn(),
            addListener: vi.fn(),
            removeListener: vi.fn(),
            dispatchEvent: vi.fn(),
        })),
    });
});

afterEach(() => {
    cleanup();
    vi.clearAllTimers();
    vi.useRealTimers();
    vi.clearAllMocks();
});

describe('homepage advertisement carousel', () => {
    it('shows the complete original illustration when no advertisements exist', () => {
        render(<HomepageAdvertisementCarousel advertisements={[]} />);

        expect(screen.getByText('دفتر مدارات')).toBeTruthy();
        expect(screen.getByText('حيث تتحول الأفكار إلى واقع')).toBeTruthy();
        expect(screen.getByText('مساعدك الذكي يظهر بعد تسجيل الدخول')).toBeTruthy();
        expect(screen.getByText('تحليل السيرة')).toBeTruthy();
        expect(screen.getByText('اقتراح الوظائف')).toBeTruthy();
        expect(screen.getByText('تجهيز المقابلات')).toBeTruthy();
    });

    it('shows one advertisement without unnecessary controls or a timer', () => {
        vi.useFakeTimers();
        render(<HomepageAdvertisementCarousel advertisements={[advertisements[0]]} />);

        expect(screen.getByRole('img', { name: 'الإعلان الأول' }).getAttribute('src'))
            .toBe('/storage/homepage-advertisements/first.jpg');
        expect(screen.queryByRole('button', { name: 'الصورة السابقة' })).toBeNull();
        expect(screen.queryByRole('button', { name: 'الصورة التالية' })).toBeNull();
        expect(screen.queryByRole('button', { name: 'الانتقال إلى الإعلان رقم 1' })).toBeNull();
        expect(vi.getTimerCount()).toBe(0);
    });

    it('shows arrows and a dot for every image without public management controls', () => {
        render(<HomepageAdvertisementCarousel advertisements={advertisements} />);

        expect(screen.getByRole('button', { name: 'الصورة السابقة' })).toBeTruthy();
        expect(screen.getByRole('button', { name: 'الصورة التالية' })).toBeTruthy();
        expect(screen.getAllByRole('button', { name: /الانتقال إلى الإعلان رقم/ })).toHaveLength(3);
        expect(screen.queryByText('إضافة الصورة الإعلانية')).toBeNull();
        expect(screen.queryByRole('button', { name: 'حذف' })).toBeNull();
    });

    it('advances automatically after exactly five seconds and loops continuously', () => {
        vi.useFakeTimers();
        render(<HomepageAdvertisementCarousel advertisements={advertisements} />);

        expect(activeImageAlt()).toBe('الإعلان الأول');
        act(() => vi.advanceTimersByTime(4999));
        expect(activeImageAlt()).toBe('الإعلان الأول');
        act(() => vi.advanceTimersByTime(1));
        expect(activeImageAlt()).toBe('الإعلان الثاني');
        act(() => vi.advanceTimersByTime(5000));
        expect(activeImageAlt()).toBe('الإعلان الثالث');
        act(() => vi.advanceTimersByTime(5000));
        expect(activeImageAlt()).toBe('الإعلان الأول');
    });

    it('supports next, previous, and direct dot navigation', () => {
        render(<HomepageAdvertisementCarousel advertisements={advertisements} />);

        fireEvent.click(screen.getByRole('button', { name: 'الصورة التالية' }));
        expect(activeImageAlt()).toBe('الإعلان الثاني');

        fireEvent.click(screen.getByRole('button', { name: 'الصورة السابقة' }));
        expect(activeImageAlt()).toBe('الإعلان الأول');

        fireEvent.click(screen.getByRole('button', { name: 'الانتقال إلى الإعلان رقم 3' }));
        expect(activeImageAlt()).toBe('الإعلان الثالث');
        expect(screen.getByRole('button', { name: 'الانتقال إلى الإعلان رقم 3' }).getAttribute('aria-current'))
            .toBe('true');
    });

    it('restarts the full automatic delay after manual navigation', () => {
        vi.useFakeTimers();
        render(<HomepageAdvertisementCarousel advertisements={advertisements} />);

        act(() => vi.advanceTimersByTime(4000));
        fireEvent.click(screen.getByRole('button', { name: 'الصورة التالية' }));
        expect(activeImageAlt()).toBe('الإعلان الثاني');

        act(() => vi.advanceTimersByTime(4999));
        expect(activeImageAlt()).toBe('الإعلان الثاني');
        act(() => vi.advanceTimersByTime(1));
        expect(activeImageAlt()).toBe('الإعلان الثالث');
    });

    it('pauses while hovered and resumes with a full delay after the pointer leaves', () => {
        vi.useFakeTimers();
        render(<HomepageAdvertisementCarousel advertisements={advertisements} />);
        const carousel = screen.getByRole('region', { name: 'الصور الإعلانية' });

        fireEvent.mouseEnter(carousel);
        act(() => vi.advanceTimersByTime(10000));
        expect(activeImageAlt()).toBe('الإعلان الأول');

        fireEvent.mouseLeave(carousel);
        act(() => vi.advanceTimersByTime(4999));
        expect(activeImageAlt()).toBe('الإعلان الأول');
        act(() => vi.advanceTimersByTime(1));
        expect(activeImageAlt()).toBe('الإعلان الثاني');
    });

    it('pauses while keyboard focus is inside and resumes after focus leaves', () => {
        vi.useFakeTimers();
        render(<HomepageAdvertisementCarousel advertisements={advertisements} />);
        const carousel = screen.getByRole('region', { name: 'الصور الإعلانية' });

        fireEvent.focus(carousel);
        act(() => vi.advanceTimersByTime(10000));
        expect(activeImageAlt()).toBe('الإعلان الأول');

        fireEvent.blur(carousel, { relatedTarget: null });
        act(() => vi.advanceTimersByTime(5000));
        expect(activeImageAlt()).toBe('الإعلان الثاني');
    });

    it('pauses in a hidden browser tab and safely resumes when visible', () => {
        vi.useFakeTimers();
        render(<HomepageAdvertisementCarousel advertisements={advertisements} />);

        setDocumentVisibility('hidden');
        act(() => vi.advanceTimersByTime(10000));
        expect(activeImageAlt()).toBe('الإعلان الأول');

        setDocumentVisibility('visible');
        act(() => vi.advanceTimersByTime(5000));
        expect(activeImageAlt()).toBe('الإعلان الثاني');
    });

    it('disables automatic movement for reduced motion while preserving manual controls', () => {
        vi.useFakeTimers();
        reducedMotion = true;
        render(<HomepageAdvertisementCarousel advertisements={advertisements} />);

        act(() => vi.advanceTimersByTime(10000));
        expect(activeImageAlt()).toBe('الإعلان الأول');

        fireEvent.click(screen.getByRole('button', { name: 'الصورة التالية' }));
        expect(activeImageAlt()).toBe('الإعلان الثاني');
    });

    it('supports left and right keyboard controls when focused', () => {
        render(<HomepageAdvertisementCarousel advertisements={advertisements} />);
        const carousel = screen.getByRole('region', { name: 'الصور الإعلانية' });

        fireEvent.keyDown(carousel, { key: 'ArrowRight' });
        expect(activeImageAlt()).toBe('الإعلان الثاني');
        fireEvent.keyDown(carousel, { key: 'ArrowLeft' });
        expect(activeImageAlt()).toBe('الإعلان الأول');
    });

    it('supports horizontal swipe while rejecting short and vertical gestures', () => {
        render(<HomepageAdvertisementCarousel advertisements={advertisements} />);
        const carousel = screen.getByRole('region', { name: 'الصور الإعلانية' });

        fireEvent.touchStart(carousel, { touches: [{ clientX: 200, clientY: 100 }] });
        fireEvent.touchEnd(carousel, { changedTouches: [{ clientX: 165, clientY: 102 }] });
        expect(activeImageAlt()).toBe('الإعلان الأول');

        fireEvent.touchStart(carousel, { touches: [{ clientX: 200, clientY: 100 }] });
        fireEvent.touchEnd(carousel, { changedTouches: [{ clientX: 140, clientY: 190 }] });
        expect(activeImageAlt()).toBe('الإعلان الأول');

        fireEvent.touchStart(carousel, { touches: [{ clientX: 200, clientY: 100 }] });
        fireEvent.touchEnd(carousel, { changedTouches: [{ clientX: 120, clientY: 105 }] });
        expect(activeImageAlt()).toBe('الإعلان الثاني');

        fireEvent.touchStart(carousel, { touches: [{ clientX: 100, clientY: 100 }] });
        fireEvent.touchEnd(carousel, { changedTouches: [{ clientX: 180, clientY: 105 }] });
        expect(activeImageAlt()).toBe('الإعلان الأول');
    });

    it('falls back to the original illustration when the only image is broken', () => {
        render(<HomepageAdvertisementCarousel advertisements={[advertisements[0]]} />);

        fireEvent.error(screen.getByRole('img', { name: 'الإعلان الأول' }));

        expect(screen.getByText('دفتر مدارات')).toBeTruthy();
        expect(screen.queryByRole('region', { name: 'الصور الإعلانية' })).toBeNull();
    });

    it('clears the pending automatic timer when unmounted', () => {
        vi.useFakeTimers();
        const { unmount } = render(<HomepageAdvertisementCarousel advertisements={advertisements} />);

        expect(vi.getTimerCount()).toBe(1);
        unmount();
        expect(vi.getTimerCount()).toBe(0);
    });
});
