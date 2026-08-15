import { useLanguage } from '@/lib/language';

export default function Footer() {
    const { t } = useLanguage();

    return (
        <footer className="mt-auto border-t border-cyan-100/80 bg-white/90">
            <div className="mx-auto flex max-w-7xl items-center justify-center px-4 py-5 text-center text-sm font-semibold text-slate-500 sm:px-6 lg:px-8">
                <p>
                    {t('طُوّر من قبل شركة', 'Developed by')}{' '}
                    <span dir="ltr" className="font-black text-madarat-blue">
                        HEXA.Tech
                    </span>{' '}
                    <span className="font-bold text-madarat-navy">
                        {t('التقنية السداسية', 'Hexa Technology')}
                    </span>
                </p>
            </div>
        </footer>
    );
}
