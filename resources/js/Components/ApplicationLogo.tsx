import { SVGAttributes } from 'react';

export default function ApplicationLogo(props: SVGAttributes<SVGElement>) {
    return (
        <svg {...props} viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg" fill="none" aria-hidden="true">
            <rect x="2" y="2" width="60" height="60" rx="18" fill="#292747" />
            <path d="M17 45 28 34l8 6 13-19" stroke="#E8E8FF" strokeWidth="4" strokeLinecap="round" strokeLinejoin="round" />
            <circle cx="17" cy="45" r="4.5" fill="#A89BFF" />
            <circle cx="28" cy="34" r="4.5" fill="#A89BFF" />
            <circle cx="36" cy="40" r="4.5" fill="#A89BFF" />
            <path d="m43 21 6-1-1 6" stroke="#53D6C3" strokeWidth="3.5" strokeLinecap="round" strokeLinejoin="round" />
        </svg>
    );
}
