import ApplicationLogo from '@/Components/ApplicationLogo';
import { Link } from '@inertiajs/react';
import { PropsWithChildren } from 'react';
import { LanguageSwitcher } from '@/lib/language';

export default function Guest({ children }: PropsWithChildren) {
    return (
        <div className="madarat-shell flex min-h-screen flex-col items-center px-4 pt-6 sm:justify-center sm:pt-0">
            <LanguageSwitcher className="fixed end-4 top-4 z-20" />
            <div className="pointer-events-none fixed -left-24 top-12 h-80 w-80 rounded-full border-[42px] border-madarat-cyan/10" />
            <div className="pointer-events-none fixed bottom-10 right-8 h-48 w-48 rounded-full border-[26px] border-madarat-blue/10" />

            <div className="relative">
                <Link href="/" className="flex flex-col items-center gap-2">
                    <span className="grid h-20 w-20 place-items-center rounded-full bg-white shadow-sm ring-1 ring-cyan-100">
                        <ApplicationLogo className="h-14 w-14" />
                    </span>
                    <span className="text-center">
                        <strong className="block text-xl font-black text-madarat-navy">مدارات</strong>
                        <span className="text-xs font-bold tracking-[0.18em] text-madarat-cyan">MADARAT</span>
                    </span>
                </Link>
            </div>

            <div className="relative mt-6 w-full overflow-hidden rounded-lg border border-cyan-100 bg-white/95 px-6 py-5 shadow-xl shadow-madarat-blue/10 sm:max-w-md">
                {children}
            </div>
        </div>
    );
}
