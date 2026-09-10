import {
    FocusEvent,
    KeyboardEvent,
    TouchEvent,
    useCallback,
    useEffect,
    useMemo,
    useRef,
    useState,
} from 'react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { AssistantRobot, MadaratLogo } from '@/Components/Madarat';
import { useLanguage } from '@/lib/language';

export type HomepageAdvertisement = {
    id: number;
    image_url: string;
    alt_text: string | null;
};

type HomepageAdvertisementCarouselProps = {
    advertisements: HomepageAdvertisement[];
};

const ROTATION_INTERVAL_MS = 5000;
const SWIPE_THRESHOLD_PX = 50;
const REDUCED_MOTION_QUERY = '(prefers-reduced-motion: reduce)';

function HomepageHeroIllustration() {
    return (
        <>
            <div className="absolute left-6 top-0 h-72 w-52 rotate-3 rounded-lg bg-gradient-to-b from-madarat-cyan to-madarat-blue p-4 text-white shadow-2xl shadow-madarat-blue/20">
                <div className="absolute inset-x-0 bottom-0 h-40 overflow-hidden rounded-b-lg">
                    <div className="absolute -left-8 top-4 h-32 w-32 rounded-full border-[18px] border-white/12" />
                    <div className="absolute right-4 top-10 h-28 w-28 rounded-full border-[16px] border-white/12" />
                    <div className="absolute left-12 top-24 h-20 w-20 rounded-full border-[12px] border-white/12" />
                </div>
                <p className="text-left text-sm font-black">دفتر مدارات</p>
                <div className="relative mt-16 rounded-lg bg-white p-4 text-madarat-navy shadow-lg">
                    <MadaratLogo />
                    <div className="mt-4 h-1.5 rounded-full bg-madarat-cyan" />
                    <p className="mt-3 text-sm font-black">حيث تتحول الأفكار إلى واقع</p>
                </div>
            </div>
            <div className="absolute right-0 top-12 w-72 rounded-lg bg-white p-5 shadow-2xl shadow-slate-900/10 ring-1 ring-cyan-100">
                <div className="mb-5 h-0.5 bg-madarat-cyan" />
                <div className="mx-auto grid h-44 w-44 place-items-center rounded-full bg-madarat-sky">
                    <AssistantRobot />
                </div>
                <div className="mt-6 rounded-lg bg-gradient-to-l from-madarat-blue to-madarat-cyan px-4 py-3 text-sm font-black text-white">
                    مساعدك الذكي يظهر بعد تسجيل الدخول
                </div>
            </div>
            <div className="absolute bottom-3 right-10 w-48 -rotate-6 rounded-lg bg-white p-4 shadow-xl shadow-slate-900/10 ring-1 ring-cyan-100">
                <MadaratLogo />
                <div className="mt-5 space-y-2 text-xs font-bold text-madarat-blue">
                    <p>تحليل السيرة</p>
                    <p>اقتراح الوظائف</p>
                    <p>تجهيز المقابلات</p>
                </div>
            </div>
        </>
    );
}

function initiallyPrefersReducedMotion() {
    return typeof window !== 'undefined'
        && typeof window.matchMedia === 'function'
        && window.matchMedia(REDUCED_MOTION_QUERY).matches;
}

export default function HomepageAdvertisementCarousel({ advertisements }: HomepageAdvertisementCarouselProps) {
    const { t } = useLanguage();
    const [activeIndex, setActiveIndex] = useState(0);
    const [timerResetToken, setTimerResetToken] = useState(0);
    const [isHovered, setIsHovered] = useState(false);
    const [hasFocusWithin, setHasFocusWithin] = useState(false);
    const [isDocumentVisible, setIsDocumentVisible] = useState(
        () => typeof document === 'undefined' || document.visibilityState !== 'hidden',
    );
    const [prefersReducedMotion, setPrefersReducedMotion] = useState(initiallyPrefersReducedMotion);
    const [failedImageUrls, setFailedImageUrls] = useState<Set<string>>(() => new Set());
    const touchStart = useRef<{ x: number; y: number } | null>(null);
    const advertisementSignature = advertisements
        .map((advertisement) => `${advertisement.id}:${advertisement.image_url}`)
        .join('|');
    const previousAdvertisementSignature = useRef(advertisementSignature);

    const usableAdvertisements = useMemo(
        () => advertisements.filter(
            (advertisement) => Boolean(advertisement.image_url) && !failedImageUrls.has(advertisement.image_url),
        ),
        [advertisements, failedImageUrls],
    );
    const advertisementCount = usableAdvertisements.length;
    const safeActiveIndex = advertisementCount === 0
        ? 0
        : Math.min(activeIndex, advertisementCount - 1);

    useEffect(() => {
        if (previousAdvertisementSignature.current === advertisementSignature) {
            return;
        }

        previousAdvertisementSignature.current = advertisementSignature;
        setActiveIndex(0);
        setFailedImageUrls(new Set());
        setTimerResetToken((current) => current + 1);
    }, [advertisementSignature]);

    useEffect(() => {
        setActiveIndex((current) => {
            if (advertisementCount === 0) {
                return 0;
            }

            return Math.min(current, advertisementCount - 1);
        });
    }, [advertisementCount]);

    useEffect(() => {
        if (typeof document === 'undefined') {
            return;
        }

        const updateVisibility = () => setIsDocumentVisible(document.visibilityState !== 'hidden');

        document.addEventListener('visibilitychange', updateVisibility);

        return () => document.removeEventListener('visibilitychange', updateVisibility);
    }, []);

    useEffect(() => {
        if (typeof window === 'undefined' || typeof window.matchMedia !== 'function') {
            return;
        }

        const mediaQuery = window.matchMedia(REDUCED_MOTION_QUERY);
        const updatePreference = (event: MediaQueryListEvent) => setPrefersReducedMotion(event.matches);

        setPrefersReducedMotion(mediaQuery.matches);
        mediaQuery.addEventListener('change', updatePreference);

        return () => mediaQuery.removeEventListener('change', updatePreference);
    }, []);

    useEffect(() => {
        if (
            advertisementCount < 2
            || isHovered
            || hasFocusWithin
            || !isDocumentVisible
            || prefersReducedMotion
        ) {
            return;
        }

        const timeout = window.setTimeout(() => {
            setActiveIndex((current) => (current + 1) % advertisementCount);
        }, ROTATION_INTERVAL_MS);

        return () => window.clearTimeout(timeout);
    }, [
        advertisementCount,
        hasFocusWithin,
        isDocumentVisible,
        isHovered,
        prefersReducedMotion,
        safeActiveIndex,
        timerResetToken,
    ]);

    const resetAutomaticRotation = useCallback(() => {
        setTimerResetToken((current) => current + 1);
    }, []);

    const showNext = useCallback(() => {
        if (advertisementCount < 2) {
            return;
        }

        setActiveIndex((current) => (current + 1) % advertisementCount);
        resetAutomaticRotation();
    }, [advertisementCount, resetAutomaticRotation]);

    const showPrevious = useCallback(() => {
        if (advertisementCount < 2) {
            return;
        }

        setActiveIndex((current) => (current - 1 + advertisementCount) % advertisementCount);
        resetAutomaticRotation();
    }, [advertisementCount, resetAutomaticRotation]);

    const showAdvertisement = useCallback((index: number) => {
        if (index < 0 || index >= advertisementCount) {
            return;
        }

        setActiveIndex(index);
        resetAutomaticRotation();
    }, [advertisementCount, resetAutomaticRotation]);

    const handleKeyDown = (event: KeyboardEvent<HTMLDivElement>) => {
        if (event.key === 'ArrowRight') {
            event.preventDefault();
            showNext();
        }

        if (event.key === 'ArrowLeft') {
            event.preventDefault();
            showPrevious();
        }
    };

    const handleBlur = (event: FocusEvent<HTMLDivElement>) => {
        const nextFocusedElement = event.relatedTarget as Node | null;

        if (!nextFocusedElement || !event.currentTarget.contains(nextFocusedElement)) {
            setHasFocusWithin(false);
        }
    };

    const handleTouchStart = (event: TouchEvent<HTMLDivElement>) => {
        const touch = event.touches[0];

        touchStart.current = touch ? { x: touch.clientX, y: touch.clientY } : null;
    };

    const handleTouchEnd = (event: TouchEvent<HTMLDivElement>) => {
        const start = touchStart.current;
        const touch = event.changedTouches[0];
        touchStart.current = null;

        if (!start || !touch) {
            return;
        }

        const horizontalDistance = touch.clientX - start.x;
        const verticalDistance = touch.clientY - start.y;

        if (
            Math.abs(horizontalDistance) < SWIPE_THRESHOLD_PX
            || Math.abs(horizontalDistance) <= Math.abs(verticalDistance)
        ) {
            return;
        }

        if (horizontalDistance < 0) {
            showNext();
        } else {
            showPrevious();
        }
    };

    const markImageAsFailed = (imageUrl: string) => {
        setFailedImageUrls((current) => {
            if (current.has(imageUrl)) {
                return current;
            }

            const next = new Set(current);
            next.add(imageUrl);

            return next;
        });
    };

    if (advertisementCount === 0) {
        return <HomepageHeroIllustration />;
    }

    return (
        <div
            role="region"
            aria-roledescription={t('عارض صور', 'carousel')}
            aria-label={t('الصور الإعلانية', 'Advertisement images')}
            aria-live="off"
            tabIndex={advertisementCount > 1 ? 0 : undefined}
            onKeyDown={handleKeyDown}
            onMouseEnter={() => setIsHovered(true)}
            onMouseLeave={() => setIsHovered(false)}
            onFocus={() => setHasFocusWithin(true)}
            onBlur={handleBlur}
            onTouchStart={handleTouchStart}
            onTouchEnd={handleTouchEnd}
            onTouchCancel={() => { touchStart.current = null; }}
            className="absolute inset-0 overflow-hidden rounded-lg border border-cyan-100 bg-white shadow-2xl shadow-madarat-blue/15 focus:outline-none focus:ring-4 focus:ring-madarat-cyan/30 focus:ring-offset-2"
        >
            {usableAdvertisements.map((advertisement, index) => (
                <div
                    key={advertisement.id}
                    role="group"
                    aria-roledescription={t('شريحة', 'slide')}
                    aria-label={t(
                        `الإعلان رقم ${index + 1} من ${advertisementCount}`,
                        `Advertisement ${index + 1} of ${advertisementCount}`,
                    )}
                    aria-hidden={index !== safeActiveIndex}
                    className={`absolute inset-0 grid place-items-center bg-white p-2 transition-opacity duration-700 ease-in-out motion-reduce:transition-none sm:p-3 ${
                        index === safeActiveIndex ? 'z-10 opacity-100' : 'pointer-events-none opacity-0'
                    }`}
                >
                    <img
                        src={advertisement.image_url}
                        alt={advertisement.alt_text?.trim() || ''}
                        loading={index === 0 ? 'eager' : 'lazy'}
                        decoding="async"
                        onError={() => markImageAsFailed(advertisement.image_url)}
                        className="h-full w-full rounded-md object-contain"
                    />
                </div>
            ))}

            {advertisementCount > 1 && (
                <>
                    <button
                        type="button"
                        aria-label={t('الصورة السابقة', 'Previous image')}
                        onClick={showPrevious}
                        className="absolute left-3 top-1/2 z-20 grid h-11 w-11 -translate-y-1/2 place-items-center rounded-full bg-white/95 text-madarat-blue shadow-lg ring-1 ring-cyan-100 transition hover:bg-madarat-sky focus:outline-none focus:ring-4 focus:ring-madarat-cyan/35"
                    >
                        <ChevronLeft className="h-6 w-6" aria-hidden="true" />
                    </button>
                    <button
                        type="button"
                        aria-label={t('الصورة التالية', 'Next image')}
                        onClick={showNext}
                        className="absolute right-3 top-1/2 z-20 grid h-11 w-11 -translate-y-1/2 place-items-center rounded-full bg-white/95 text-madarat-blue shadow-lg ring-1 ring-cyan-100 transition hover:bg-madarat-sky focus:outline-none focus:ring-4 focus:ring-madarat-cyan/35"
                    >
                        <ChevronRight className="h-6 w-6" aria-hidden="true" />
                    </button>
                    <div className="absolute bottom-4 left-1/2 z-20 flex -translate-x-1/2 items-center gap-2 rounded-full bg-white/90 px-3 py-2 shadow-md ring-1 ring-cyan-100">
                        {usableAdvertisements.map((advertisement, index) => (
                            <button
                                key={advertisement.id}
                                type="button"
                                aria-label={t(
                                    `الانتقال إلى الإعلان رقم ${index + 1}`,
                                    `Go to advertisement number ${index + 1}`,
                                )}
                                aria-current={index === safeActiveIndex ? 'true' : undefined}
                                onClick={() => showAdvertisement(index)}
                                className={`h-3 rounded-full transition-all focus:outline-none focus:ring-2 focus:ring-madarat-cyan focus:ring-offset-2 ${
                                    index === safeActiveIndex
                                        ? 'w-8 bg-madarat-blue'
                                        : 'w-3 bg-slate-300 hover:bg-madarat-cyan'
                                }`}
                            />
                        ))}
                    </div>
                </>
            )}
        </div>
    );
}
