import { Languages } from 'lucide-react';
import {
    createContext,
    PropsWithChildren,
    useContext,
    useEffect,
    useRef,
    useState,
} from 'react';
import { englishTranslations } from './englishTranslations';

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
    const originalTextRef = useRef(new WeakMap<Text, string>());
    const originalAttributesRef = useRef(new WeakMap<Element, Map<string, string>>());

    useEffect(() => {
        const direction = locale === 'ar' ? 'rtl' : 'ltr';

        document.documentElement.lang = locale;
        document.documentElement.dir = direction;
        document.body.dir = direction;
        window.localStorage.setItem('madarat-locale', locale);

        const originalText = originalTextRef.current;
        const originalAttributes = originalAttributesRef.current;
        const translatableAttributes = ['placeholder', 'title', 'aria-label', 'alt'];
        let applyingTranslation = false;

        const translate = (value: string) => {
            const normalized = value.replace(/\s+/g, ' ').trim();
            const direct = englishTranslations[normalized];

            if (direct) {
                const leading = value.match(/^\s*/)?.[0] ?? '';
                const trailing = value.match(/\s*$/)?.[0] ?? '';
                return `${leading}${direct}${trailing}`;
            }

            return value;
        };

        const processElement = (root: ParentNode) => {
            applyingTranslation = true;

            const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
            let node = walker.nextNode() as Text | null;

            while (node) {
                const parent = node.parentElement;
                if (parent && !['SCRIPT', 'STYLE'].includes(parent.tagName)) {
                    if (/[\u0600-\u06ff]/.test(node.data)) {
                        originalText.set(node, node.data);
                    }

                    const original = originalText.get(node);
                    if (original) {
                        const nextValue = locale === 'en' ? translate(original) : original;
                        if (node.data !== nextValue) node.data = nextValue;
                    }
                }
                node = walker.nextNode() as Text | null;
            }

            const elements = root instanceof Element
                ? [root, ...root.querySelectorAll('*')]
                : [...root.querySelectorAll('*')];

            for (const element of elements) {
                let originals = originalAttributes.get(element);
                if (!originals) {
                    originals = new Map();
                    originalAttributes.set(element, originals);
                }

                for (const attribute of translatableAttributes) {
                    const value = element.getAttribute(attribute);
                    if (value && /[\u0600-\u06ff]/.test(value)) originals.set(attribute, value);
                    const original = originals.get(attribute);
                    if (original) {
                        const nextValue = locale === 'en' ? translate(original) : original;
                        if (value !== nextValue) element.setAttribute(attribute, nextValue);
                    }
                }
            }

            applyingTranslation = false;
        };

        processElement(document.body);

        const observer = new MutationObserver((mutations) => {
            if (applyingTranslation) return;

            for (const mutation of mutations) {
                if (mutation.type === 'characterData' && mutation.target.parentNode) {
                    processElement(mutation.target.parentNode);
                }

                for (const addedNode of mutation.addedNodes) {
                    if (addedNode instanceof Element) processElement(addedNode);
                    if (addedNode instanceof Text && addedNode.parentNode) processElement(addedNode.parentNode);
                }

                if (mutation.type === 'attributes' && mutation.target instanceof Element) {
                    processElement(mutation.target);
                }
            }
        });

        observer.observe(document.body, {
            subtree: true,
            childList: true,
            characterData: true,
            attributes: true,
            attributeFilter: translatableAttributes,
        });

        return () => observer.disconnect();
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
