import { InertiaLinkProps, Link } from '@inertiajs/react';

export default function ResponsiveNavLink({
    active = false,
    className = '',
    children,
    ...props
}: InertiaLinkProps & { active?: boolean }) {
    return (
        <Link
            {...props}
            className={`flex w-full items-start border-l-4 py-2 pe-4 ps-3 ${
                active
                    ? 'border-madarat-cyan bg-madarat-sky text-madarat-blue focus:border-madarat-blue focus:bg-cyan-50 focus:text-madarat-navy'
                    : 'border-transparent text-slate-600 hover:border-cyan-200 hover:bg-madarat-sky hover:text-madarat-blue focus:border-cyan-200 focus:bg-madarat-sky focus:text-madarat-blue'
            } text-base font-medium transition duration-150 ease-in-out focus:outline-none ${className}`}
        >
            {children}
        </Link>
    );
}
