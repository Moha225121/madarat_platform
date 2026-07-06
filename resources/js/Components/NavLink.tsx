import { InertiaLinkProps, Link } from '@inertiajs/react';

export default function NavLink({
    active = false,
    className = '',
    children,
    ...props
}: InertiaLinkProps & { active: boolean }) {
    return (
        <Link
            {...props}
            className={
                'inline-flex items-center border-b-2 px-1 pt-1 text-sm font-medium leading-5 transition duration-150 ease-in-out focus:outline-none ' +
                (active
                    ? 'border-madarat-cyan text-madarat-navy focus:border-madarat-blue'
                    : 'border-transparent text-slate-500 hover:border-cyan-200 hover:text-madarat-blue focus:border-cyan-200 focus:text-madarat-blue') +
                className
            }
        >
            {children}
        </Link>
    );
}
