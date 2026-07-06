import { ButtonHTMLAttributes } from 'react';

export default function SecondaryButton({
    type = 'button',
    className = '',
    disabled,
    children,
    ...props
}: ButtonHTMLAttributes<HTMLButtonElement>) {
    return (
        <button
            {...props}
            type={type}
            className={
                `inline-flex items-center rounded-lg border border-cyan-100 bg-white px-4 py-2 text-xs font-bold uppercase tracking-widest text-madarat-blue shadow-sm transition duration-150 ease-in-out hover:bg-madarat-sky focus:outline-none focus:ring-2 focus:ring-madarat-cyan focus:ring-offset-2 disabled:opacity-25 ${
                    disabled && 'opacity-25'
                } ` + className
            }
            disabled={disabled}
        >
            {children}
        </button>
    );
}
