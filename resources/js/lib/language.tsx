import { Languages } from 'lucide-react';
import {
    createContext,
    PropsWithChildren,
    useContext,
    useEffect,
    useState,
} from 'react';

export type Locale = 'ar' | 'en';

type LanguageContextValue = {
    locale: Locale;
    setLocale: (locale: Locale) => void;
    toggleLocale: () => void;
    t: (arabic: string, english: string) => string;
};

const LanguageContext = createContext<LanguageContextValue | null>(null);

function savedLocale(): Locale {
    if (typeof window === 'undefined') return 'ar';

    return window.localStorage.getItem('madarat-locale') === 'en' ? 'en' : 'ar';
}

export function LanguageProvider({ children }: PropsWithChildren) {
    const [locale, setLocale] = useState<Locale>(savedLocale);

    useEffect(() => {
        const direction = locale === 'ar' ? 'rtl' : 'ltr';

        document.documentElement.lang = locale;
        document.documentElement.dir = direction;
        document.body.dir = direction;
        window.localStorage.setItem('madarat-locale', locale);
    }, [locale]);

    return (
        <LanguageContext.Provider
            value={{
                locale,
                setLocale,
                toggleLocale: () => setLocale((current) => current === 'ar' ? 'en' : 'ar'),
                t: (arabic, english) => locale === 'ar' ? arabic : english,
            }}
        >
            {children}
        </LanguageContext.Provider>
    );
}

export function useLanguage() {
    const context = useContext(LanguageContext);

    if (!context) {
        throw new Error('useLanguage must be used within LanguageProvider');
    }

    return context;
}

export function LanguageSwitcher({ className = '' }: { className?: string }) {
    const { locale, toggleLocale } = useLanguage();
    const nextLanguage = locale === 'ar' ? 'English' : 'العربية';

    return (
        <button
            type="button"
            onClick={toggleLocale}
            aria-label={locale === 'ar' ? 'Switch language to English' : 'تغيير اللغة إلى العربية'}
            title={locale === 'ar' ? 'Switch to English' : 'التبديل إلى العربية'}
            className={`inline-flex items-center gap-1 rounded-lg bg-slate-100 px-3 py-2 text-sm font-bold text-slate-600 transition hover:bg-madarat-sky hover:text-madarat-blue focus:outline-none focus:ring-2 focus:ring-madarat-cyan focus:ring-offset-2 ${className}`}
        >
            <Languages className="h-4 w-4" />
            {nextLanguage}
        </button>
    );
}
