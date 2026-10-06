import { ReactNode } from 'react';

export default function AuthHeading({ eyebrow, title, children }: { eyebrow?: string; title: string; children?: ReactNode }) {
    return (
        <div className="mb-8">
            {eyebrow && <p className="text-sm font-semibold text-brand-600">{eyebrow}</p>}
            <h1 className="mt-1.5 font-display text-[32px] font-extrabold leading-tight tracking-[-.03em] text-neutral-950">{title}</h1>
            {children && <p className="mt-3 text-[15px] leading-6 text-neutral-600">{children}</p>}
        </div>
    );
}

export const submitButtonClass = 'inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-brand-700 px-5 py-3 text-[15px] font-semibold text-white shadow-sm transition hover:bg-brand-800 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/30 active:translate-y-px disabled:cursor-wait disabled:opacity-70';
export const linkClass = 'rounded font-semibold text-brand-700 underline decoration-brand-200 underline-offset-4 transition hover:text-brand-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500';
