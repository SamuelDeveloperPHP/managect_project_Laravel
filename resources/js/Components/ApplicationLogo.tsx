import { SVGAttributes } from 'react';

export default function ApplicationLogo(props: SVGAttributes<SVGElement>) {
    return (
        <svg {...props} viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg" fill="none" aria-hidden="true">
            <rect x="2" y="2" width="60" height="60" rx="10" fill="#0F3D44" />
            <path d="M14 44h12l8-14h8" stroke="#D8E8E9" strokeWidth="4" strokeLinecap="round" strokeLinejoin="round" />
            <circle cx="14" cy="44" r="4" fill="#D8E8E9" />
            <path d="M48 22v16M40 30h16" stroke="#E5B454" strokeWidth="4.5" strokeLinecap="round" />
        </svg>
    );
}
