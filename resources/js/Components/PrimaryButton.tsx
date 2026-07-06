import { ButtonHTMLAttributes } from 'react';

export default function PrimaryButton({
    className = '',
    disabled,
    children,
    ...props
}: ButtonHTMLAttributes<HTMLButtonElement>) {
    return (
        <button
            {...props}
            className={
                `inline-flex items-center rounded-lg border border-transparent bg-madarat-blue px-4 py-2 text-xs font-bold uppercase tracking-widest text-white shadow-sm shadow-madarat-blue/20 transition duration-150 ease-in-out hover:bg-madarat-navy focus:bg-madarat-navy focus:outline-none focus:ring-2 focus:ring-madarat-cyan focus:ring-offset-2 active:bg-madarat-navy ${
                    disabled && 'opacity-25'
                } ` + className
            }
            disabled={disabled}
        >
            {children}
        </button>
    );
}
