import ApplicationLogo from '@/Components/ApplicationLogo';
import Dropdown from '@/Components/Dropdown';
import NavLink from '@/Components/NavLink';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink';
import { Link, usePage } from '@inertiajs/react';
import { PropsWithChildren, ReactNode, useState } from 'react';

export default function Authenticated({
    header,
    children,
}: PropsWithChildren<{ header?: ReactNode }>) {
    const user = usePage().props.auth.user;

    const [showingNavigationDropdown, setShowingNavigationDropdown] =
        useState(false);

    return (
        <div className="madarat-shell min-h-screen">
            <nav className="border-b border-cyan-100/70 bg-white/90 backdrop-blur-xl">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="flex h-16 justify-between">
                        <div className="flex">
                            <div className="flex shrink-0 items-center">
                                <Link href="/">
                                    <span className="flex items-center gap-2">
                                        <span className="grid h-10 w-10 place-items-center rounded-full bg-white shadow-sm ring-1 ring-cyan-100">
                                            <ApplicationLogo className="h-7 w-7" />
                                        </span>
                                        <span className="hidden leading-tight sm:grid">
                                            <strong className="text-base font-black text-madarat-navy">مدارات</strong>
                                            <span className="text-[10px] font-bold tracking-[0.18em] text-madarat-cyan">MADARAT</span>
                                        </span>
                                    </span>
                                </Link>
                            </div>

                            <div className="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                                <NavLink
                                    href={route('dashboard')}
                                    active={route().current('dashboard')}
                                >
                                    لوحة التحكم
                                </NavLink>
                                {user.role === 'training_provider' && <>
                                    <NavLink href="/training/profile" active={route().current('training.profile')}>الملف التعريفي</NavLink>
                                    <NavLink href="/training/courses" active={route().current('training.courses.*')}>دوراتي</NavLink>
                                    <NavLink href="/training/courses/create" active={route().current('training.courses.create')}>إضافة دورة</NavLink>
                                </>}
                                {user.role === 'job_seeker' && <>
                                    <NavLink href="/courses" active={route().current('courses.*')}>الدورات</NavLink>
                                    <NavLink href="/seeker/courses/recommended" active={route().current('seeker.courses.recommended')}>مقترحة لك</NavLink>
                                    <NavLink href="/seeker/courses/saved" active={route().current('seeker.courses.saved')}>المحفوظة</NavLink>
                                </>}
                                {user.role === 'admin' && <NavLink href="/admin/training" active={route().current('admin.training.*')}>إدارة التدريب</NavLink>}
                            </div>
                        </div>

                        <div className="hidden sm:ms-6 sm:flex sm:items-center">
                            <div className="relative ms-3">
                                <Dropdown>
                                    <Dropdown.Trigger>
                                        <span className="inline-flex rounded-md">
                                            <button
                                                type="button"
                                                className="inline-flex items-center rounded-lg border border-transparent bg-white px-3 py-2 text-sm font-bold leading-4 text-slate-500 transition duration-150 ease-in-out hover:bg-madarat-sky hover:text-madarat-blue focus:outline-none"
                                            >
                                                {user.name}

                                                <svg
                                                    className="-me-0.5 ms-2 h-4 w-4"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    viewBox="0 0 20 20"
                                                    fill="currentColor"
                                                >
                                                    <path
                                                        fillRule="evenodd"
                                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                        clipRule="evenodd"
                                                    />
                                                </svg>
                                            </button>
                                        </span>
                                    </Dropdown.Trigger>

                                    <Dropdown.Content>
                                        <Dropdown.Link
                                            href={route('profile.edit')}
                                        >
                                            الملف الشخصي
                                        </Dropdown.Link>
                                        <Dropdown.Link
                                            href={route('logout')}
                                            method="post"
                                            as="button"
                                        >
                                            خروج
                                        </Dropdown.Link>
                                    </Dropdown.Content>
                                </Dropdown>
                            </div>
                        </div>

                        <div className="-me-2 flex items-center sm:hidden">
                            <button
                                onClick={() =>
                                    setShowingNavigationDropdown(
                                        (previousState) => !previousState,
                                    )
                                }
                                className="inline-flex items-center justify-center rounded-lg p-2 text-slate-400 transition duration-150 ease-in-out hover:bg-madarat-sky hover:text-madarat-blue focus:bg-madarat-sky focus:text-madarat-blue focus:outline-none"
                            >
                                <svg
                                    className="h-6 w-6"
                                    stroke="currentColor"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        className={
                                            !showingNavigationDropdown
                                                ? 'inline-flex'
                                                : 'hidden'
                                        }
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth="2"
                                        d="M4 6h16M4 12h16M4 18h16"
                                    />
                                    <path
                                        className={
                                            showingNavigationDropdown
                                                ? 'inline-flex'
                                                : 'hidden'
                                        }
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <div
                    className={
                        (showingNavigationDropdown ? 'block' : 'hidden') +
                        ' sm:hidden'
                    }
                >
                    <div className="space-y-1 pb-3 pt-2">
                        <ResponsiveNavLink
                            href={route('dashboard')}
                            active={route().current('dashboard')}
                        >
                            لوحة التحكم
                        </ResponsiveNavLink>
                    </div>

                    <div className="border-t border-cyan-100 pb-1 pt-4">
                        <div className="px-4">
                            <div className="text-base font-bold text-madarat-navy">
                                {user.name}
                            </div>
                            <div className="text-sm font-medium text-slate-500">
                                {user.email}
                            </div>
                        </div>

                        <div className="mt-3 space-y-1">
                            <ResponsiveNavLink href={route('profile.edit')}>
                                الملف الشخصي
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                method="post"
                                href={route('logout')}
                                as="button"
                            >
                                خروج
                            </ResponsiveNavLink>
                        </div>
                    </div>
                </div>
            </nav>

            {header && (
                <header className="border-b border-cyan-100 bg-white/85 shadow-sm">
                    <div className="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                        {header}
                    </div>
                </header>
            )}

            <main>{children}</main>
        </div>
    );
}
