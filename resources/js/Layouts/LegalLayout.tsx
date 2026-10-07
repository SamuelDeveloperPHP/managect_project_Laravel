import ApplicationLogo from '@/Components/ApplicationLogo';
import ProductFooter from '@/Components/ProductFooter';
import { Link, usePage } from '@inertiajs/react';
import { PropsWithChildren } from 'react';

/** Moldura dos textos legais: legível, sem navegação do sistema, abre com ou sem login. */
export default function LegalLayout({ title, version, children }: PropsWithChildren<{ title: string; version: string }>) {
    const { auth } = usePage().props as unknown as { auth?: { user: unknown } };

    return (
        <div className="min-h-screen bg-neutral-50 text-neutral-800">
            <header className="border-b border-neutral-200 bg-white">
                <div className="mx-auto flex max-w-3xl items-center justify-between gap-4 px-5 py-4">
                    <Link href="/" className="inline-flex items-center gap-2.5 rounded-lg font-display text-lg font-extrabold tracking-tight text-neutral-950 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-brand-600">
                        <ApplicationLogo className="h-8 w-8" /> Trilha+
                    </Link>
                    <nav aria-label="Documentos legais" className="flex items-center gap-4 text-sm font-semibold">
                        <Link href={route('legal.privacy')} className="text-neutral-600 transition hover:text-brand-700">Privacidade</Link>
                        <Link href={route('legal.terms')} className="text-neutral-600 transition hover:text-brand-700">Termos de Uso</Link>
                        <Link href={auth?.user ? route('dashboard') : route('login')} className="text-brand-700 transition hover:text-brand-900">{auth?.user ? 'Voltar ao sistema' : 'Entrar'}</Link>
                    </nav>
                </div>
            </header>

            <main className="mx-auto max-w-3xl px-5 py-10">
                <p className="text-xs font-semibold uppercase tracking-[.14em] text-brand-700">Versão {version}</p>
                <h1 className="mt-2 font-display text-3xl font-extrabold tracking-tight text-neutral-950 sm:text-4xl">{title}</h1>
                <article className="mt-8 space-y-8 text-[15px] leading-7 text-neutral-700">{children}</article>
            </main>

            <footer className="mx-auto max-w-3xl border-t border-neutral-200 px-5 py-6"><ProductFooter /></footer>
        </div>
    );
}

export function Section({ title, children }: PropsWithChildren<{ title: string }>) {
    return (
        <section>
            <h2 className="font-display text-xl font-bold tracking-tight text-neutral-950">{title}</h2>
            <div className="mt-3 space-y-3 [&_a]:font-semibold [&_a]:text-brand-700 [&_a]:underline [&_li]:ms-5 [&_li]:list-disc [&_li]:pl-1 [&_strong]:text-neutral-900">{children}</div>
        </section>
    );
}

export type LegalProps = {
    legal: { controller: string; contact_email: string | null; dpo_name: string | null; version: string; ip_retention_days: number; audit_retention_days: number; backup_keep_days: number };
};

export function ContactLine({ legal }: LegalProps) {
    return legal.contact_email
        ? <a href={`mailto:${legal.contact_email}`}>{legal.contact_email}</a>
        : <span>o contato informado na contratação do serviço</span>;
}
