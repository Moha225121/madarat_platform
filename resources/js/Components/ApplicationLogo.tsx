import { SVGAttributes } from 'react';

export default function ApplicationLogo(props: SVGAttributes<SVGElement>) {
    return (
        <svg {...props} viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <circle cx="30" cy="34" r="19" fill="#18B7C8" opacity="0.18" />
            <path d="M16 36a20 20 0 0 1 32-16" fill="none" stroke="#18B7C8" strokeWidth="8" strokeLinecap="round" />
            <path d="M19 42a20 20 0 0 0 29-24" fill="none" stroke="#0B4F7A" strokeWidth="8" strokeLinecap="round" />
            <path d="M42 12h12v12" fill="none" stroke="#0B4F7A" strokeWidth="6" strokeLinecap="round" strokeLinejoin="round" />
            <path d="M48 18 34 32" fill="none" stroke="#0B4F7A" strokeWidth="6" strokeLinecap="round" />
            <circle cx="16" cy="21" r="5" fill="#073B5F" />
            <circle cx="31" cy="35" r="5" fill="#073B5F" />
        </svg>
    );
}
