import { Link } from '@inertiajs/react';
import { ReactNode } from 'react';

/** Classes de botão e campo compartilhadas pelas telas internas (direção visual v2). */
export const btnPrimary = 'inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-brand-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-800 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/30 active:translate-y-px disabled:cursor-not-allowed disabled:opacity-60';
export const btnSecondary = 'inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-neutral-300 bg-white px-5 py-2.5 text-sm font-semibold text-neutral-800 transition hover:bg-neutral-50 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/20 active:translate-y-px disabled:opacity-60';
export const fieldClass = 'mt-1.5 block min-h-11 w-full rounded-xl border-neutral-300 bg-white px-3.5 text-sm text-neutral-900 shadow-sm transition placeholder:text-neutral-400 hover:border-neutral-400 focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15';
export const panelClass = 'rounded-3xl border border-neutral-200 bg-white shadow-sm';

const statusTone: Record<string, string> = {
    planning: 'bg-sky-50 text-sky-700 ring-sky-200',
    active: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    in_progress: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    on_hold: 'bg-amber-50 text-amber-800 ring-amber-200',
    completed: 'bg-brand-50 text-brand-700 ring-brand-200',
    done: 'bg-brand-50 text-brand-700 ring-brand-200',
    pending: 'bg-neutral-100 text-neutral-700 ring-neutral-200',
    validation: 'bg-violet-50 text-violet-700 ring-violet-200',
    cancelled: 'bg-neutral-100 text-neutral-500 ring-neutral-200',
};

export function StatusBadge({ status, label }: { status: string; label: string }) {
    return (
        <span className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset ${statusTone[status] ?? 'bg-neutral-100 text-neutral-700 ring-neutral-200'}`}>
            <span aria-hidden="true" className="h-1.5 w-1.5 rounded-full bg-current" />
            {label}
        </span>
    );
}

/** Cabeçalho de página: o título aparece apenas aqui (a barra de navegação), nunca repetido no corpo. */
export function PageHeader({ back, title, meta, actions }: { back?: { href: string; label: string }; title: string; meta?: ReactNode; actions?: ReactNode }) {
    return (
        <div className="flex flex-wrap items-end justify-between gap-4">
            <div className="min-w-0">
                {back && (
                    <Link href={back.href} className="inline-flex items-center gap-1.5 rounded text-sm font-semibold text-brand-600 transition hover:text-brand-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500">
                        <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M19 12H5m6-6-6 6 6 6" /></svg>
                        {back.label}
                    </Link>
                )}
                <h1 className="mt-1 truncate font-display text-[28px] font-extrabold leading-tight tracking-[-.03em] text-neutral-950">{title}</h1>
                {meta && <div className="mt-1 text-sm text-neutral-500">{meta}</div>}
            </div>
            {actions && <div className="flex flex-wrap items-center gap-2">{actions}</div>}
        </div>
    );
}

export function EmptyState({ title, text, action }: { title: string; text: string; action?: ReactNode }) {
    return (
        <div className="rounded-3xl border border-dashed border-neutral-300 bg-white px-6 py-16 text-center">
            <span aria-hidden="true" className="mx-auto grid h-12 w-12 place-items-center rounded-2xl bg-brand-50 text-brand-600">
                <svg className="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"><path d="M4 7.5h16v12H4z" /><path d="M8 7.5V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2.5" /></svg>
            </span>
            <h3 className="mt-4 font-display text-lg font-bold text-neutral-900">{title}</h3>
            <p className="mx-auto mt-1.5 max-w-md text-sm text-neutral-500">{text}</p>
            {action && <div className="mt-6">{action}</div>}
        </div>
    );
}

export function Stat({ label, value }: { label: string; value: ReactNode }) {
    return (
        <div>
            <dt className="text-xs font-semibold text-neutral-500">{label}</dt>
            <dd className="mt-1 text-[15px] font-semibold text-neutral-900">{value}</dd>
        </div>
    );
}
