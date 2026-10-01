import { Link } from '@inertiajs/react';
import { PropsWithChildren } from 'react';

export default function Guest({ children }: PropsWithChildren) {
    return (
        <div className="relative flex min-h-screen flex-col overflow-hidden bg-slate-50 text-slate-900 lg:flex-row">
            <div aria-hidden="true" className="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top_left,_rgba(37,99,235,.10),_transparent_42%)]" />

            <aside className="relative flex min-h-56 flex-col justify-between overflow-hidden bg-slate-950 px-6 py-6 text-white sm:px-10 sm:py-8 lg:min-h-screen lg:w-[48%] lg:px-14 lg:py-12 xl:px-20">
                <div aria-hidden="true" className="pointer-events-none absolute inset-0 opacity-40 [background-image:linear-gradient(rgba(148,163,184,.10)_1px,transparent_1px),linear-gradient(90deg,rgba(148,163,184,.10)_1px,transparent_1px)] [background-size:40px_40px]" />
                <div aria-hidden="true" className="pointer-events-none absolute -right-24 -top-32 h-96 w-96 rounded-full border border-blue-400/20" />
                <div aria-hidden="true" className="pointer-events-none absolute -right-12 -top-20 h-72 w-72 rounded-full border border-blue-400/20" />

                <Link href="/" className="relative z-10 inline-flex w-fit items-center gap-3 rounded-lg focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-blue-300" aria-label="ManageCT — página inicial">
                    <span className="grid h-11 w-11 place-items-center rounded-xl bg-blue-600 text-xl font-black shadow-lg shadow-blue-950/50">M</span>
                    <span className="text-lg font-extrabold tracking-tight">ManageCT</span>
                </Link>

                <div className="relative z-10 hidden max-w-xl py-12 lg:block">
                    <span className="inline-flex items-center gap-2 rounded-full border border-blue-400/25 bg-blue-400/10 px-3 py-1.5 text-xs font-bold uppercase tracking-[.14em] text-blue-200">
                        <span className="h-1.5 w-1.5 rounded-full bg-emerald-400" /> Gestão multiempresa
                    </span>
                    <h1 className="mt-6 max-w-lg text-4xl font-black leading-[1.12] tracking-[-.04em] xl:text-5xl">Seu trabalho, organizado em um só lugar.</h1>
                    <p className="mt-5 max-w-lg text-base leading-7 text-slate-300">Acesse projetos, tarefas e cronogramas da sua empresa. Cada equipe trabalha em seu próprio ambiente, com permissões definidas e atividade auditável.</p>

                    <div className="mt-10 rounded-2xl border border-white/10 bg-white/[.06] p-5 shadow-2xl shadow-black/10 backdrop-blur-sm">
                        <div className="flex items-center justify-between gap-4 border-b border-white/10 pb-4">
                            <div className="flex items-center gap-3"><span className="grid h-10 w-10 place-items-center rounded-xl bg-blue-500/15 text-blue-200"><ProjectIcon /></span><div><p className="text-sm font-bold text-white">Visão de trabalho</p><p className="mt-0.5 text-xs text-slate-400">Projetos e equipe conectados</p></div></div>
                            <span className="rounded-full bg-emerald-400/10 px-2.5 py-1 text-xs font-semibold text-emerald-300">Organizado</span>
                        </div>
                        <div className="mt-4 space-y-3">
                            <div className="flex items-center gap-3 rounded-lg bg-slate-900/70 px-3 py-3"><span className="h-2 w-2 rounded-full bg-blue-400" /><span className="flex-1 text-sm text-slate-200">Projetos e cronogramas</span><span className="text-xs text-slate-500">Acompanhar</span></div>
                            <div className="flex items-center gap-3 rounded-lg bg-slate-900/70 px-3 py-3"><span className="h-2 w-2 rounded-full bg-violet-400" /><span className="flex-1 text-sm text-slate-200">Perfis e permissões</span><span className="text-xs text-slate-500">Administrar</span></div>
                            <div className="flex items-center gap-3 rounded-lg bg-slate-900/70 px-3 py-3"><span className="h-2 w-2 rounded-full bg-emerald-400" /><span className="flex-1 text-sm text-slate-200">Trilha de atividade</span><span className="text-xs text-slate-500">Consultar</span></div>
                        </div>
                    </div>
                </div>

                <div className="relative z-10 mt-8 hidden items-center gap-2 text-xs text-slate-400 lg:flex"><ShieldIcon /><span>Acesso protegido e isolado por empresa</span></div>
                <p className="relative z-10 mt-8 text-xs text-slate-400 lg:hidden">Projetos, equipes e permissões em um único ambiente.</p>
            </aside>

            <main className="relative z-10 flex flex-1 items-center justify-center px-5 py-9 sm:px-8 lg:px-10 xl:px-16">
                <div className="w-full max-w-md">
                    <div className="mb-6 flex items-center gap-2 text-xs font-medium text-slate-500 lg:hidden"><ShieldIcon /> Acesso protegido e isolado por empresa</div>
                    <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-[0_24px_70px_rgba(15,23,42,.10)] sm:p-9">
                        {children}
                    </section>
                    <footer className="mt-6 flex flex-wrap items-center justify-between gap-3 px-1 text-xs text-slate-500">
                        <span>© {new Date().getFullYear()} ManageCT</span>
                        <Link href="/" className="font-semibold text-slate-600 transition hover:text-blue-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600">Página inicial</Link>
                    </footer>
                </div>
            </main>
        </div>
    );
}

function ProjectIcon() {
    return <svg aria-hidden="true" className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"><rect x="3" y="4" width="18" height="16" rx="3" /><path d="M8 8h8M8 12h5M8 16h3" /></svg>;
}

function ShieldIcon() {
    return <svg aria-hidden="true" className="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"><path d="M12 3 20 6v5c0 5-3.4 8.2-8 10-4.6-1.8-8-5-8-10V6l8-3Z" /><path d="m9 12 2 2 4-4" /></svg>;
}
