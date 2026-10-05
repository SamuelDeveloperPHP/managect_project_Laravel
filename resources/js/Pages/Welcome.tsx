import { Head, Link } from '@inertiajs/react';
import ApplicationLogo from '@/Components/ApplicationLogo';

type WelcomeProps = {
    canRegister: boolean;
    auth?: { user: { id: number; name: string; role?: string } | null };
};

export default function Welcome({ canRegister, auth }: WelcomeProps) {
    const loggedIn = Boolean(auth?.user);
    const mainHref = loggedIn ? route('projects.index') : canRegister ? route('register') : route('login');
    const mainLabel = loggedIn ? 'Abrir painel' : canRegister ? 'Criar empresa' : 'Acessar Trilha+';

    return (
        <>
            <Head title="Trilha+ · Gestão de projetos multiempresa">
                <meta name="description" content="Organize projetos, equipes, cronogramas e permissões em uma plataforma multiempresa com trilha de auditoria." />
            </Head>
            <div className="min-h-screen bg-slate-50 text-slate-900">
                <header className="sticky top-0 z-20 border-b border-slate-800 bg-slate-950 text-white shadow-sm">
                    <div className="mx-auto flex min-h-16 max-w-7xl items-center justify-between gap-5 px-5 sm:px-8">
                        <a href="#inicio" className="flex items-center gap-2.5 font-black tracking-tight" aria-label="Trilha+ — início">
                            <ApplicationLogo className="h-9 w-9" />
                            <span>Trilha+</span>
                        </a>
                        <nav className="hidden items-center gap-7 text-sm font-semibold text-slate-300 md:flex" aria-label="Seções da página">
                            <a className="hover:text-white focus-visible:outline-blue-400" href="#recursos">Recursos</a>
                            <a className="hover:text-white focus-visible:outline-blue-400" href="#fluxo">Fluxo</a>
                            <a className="hover:text-white focus-visible:outline-blue-400" href="#acesso">Acesso</a>
                        </nav>
                        <div className="flex items-center gap-2">
                            <Link href={route('company.versions.index')} className="hidden rounded-lg px-3 py-2 text-sm font-bold text-slate-200 hover:bg-white/5 hover:text-white sm:inline-flex">Versões</Link>
                            {!loggedIn && <Link href={route('login')} className="hidden rounded-lg border border-slate-600 px-4 py-2 text-sm font-bold hover:border-slate-400 hover:bg-white/5 sm:inline-flex">Entrar</Link>}
                            <Link href={mainHref} className="inline-flex min-h-10 items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-extrabold text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-blue-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-400">
                                {mainLabel}<ArrowIcon />
                            </Link>
                        </div>
                    </div>
                </header>

                <main id="inicio">
                    <section className="relative overflow-hidden border-b border-blue-100 bg-[linear-gradient(120deg,#fff_0%,#eff6ff_100%)]">
                        <div aria-hidden="true" className="pointer-events-none absolute inset-0 opacity-50 [background-image:linear-gradient(rgba(37,99,235,.055)_1px,transparent_1px),linear-gradient(90deg,rgba(37,99,235,.055)_1px,transparent_1px)] [background-size:42px_42px]" />
                        <div className="relative mx-auto grid max-w-7xl items-center gap-12 px-5 py-14 sm:px-8 sm:py-20 lg:grid-cols-[.9fr_1.1fr] lg:gap-9 lg:py-24">
                            <div>
                                <p className="mb-3 text-xs font-black uppercase tracking-[.18em] text-blue-700">Sistema multiempresa</p>
                                <h1 className="text-5xl font-black tracking-[-.055em] text-slate-950 sm:text-6xl">Trilha+</h1>
                                <p className="mt-2 text-sm font-semibold tracking-wide text-blue-700">Do plano à entrega.</p>
                                <p className="mt-4 max-w-xl text-xl font-extrabold leading-snug tracking-tight text-slate-800 sm:text-2xl">Gestão de projetos, tarefas e permissões — por empresa.</p>
                                <p className="mt-4 max-w-xl text-base leading-7 text-slate-600">Centralize equipes, responsabilidades, prazos e execução em uma rotina visual. Cada organização administra seus próprios usuários e dados, com papéis e registros de atividade.</p>
                                <div className="mt-7 flex flex-wrap gap-3">
                                    <Link href={mainHref} className="inline-flex min-h-11 items-center gap-2 rounded-lg bg-blue-600 px-5 py-3 text-sm font-extrabold text-white shadow transition hover:-translate-y-0.5 hover:bg-blue-700">{mainLabel}<ArrowIcon /></Link>
                                    <a href="#recursos" className="inline-flex min-h-11 items-center rounded-lg border border-slate-300 bg-white px-5 py-3 text-sm font-extrabold text-slate-800 transition hover:border-blue-300 hover:text-blue-700">Conhecer recursos</a>
                                </div>
                                <dl className="mt-9 grid gap-3 border-t border-blue-100 pt-5 sm:grid-cols-3">
                                    <Metric title="Multiempresa" detail="Dados isolados por organização" />
                                    <Metric title="Projetos" detail="Backlog e acompanhamento" />
                                    <Metric title="Auditoria" detail="Ações registradas no sistema" />
                                </dl>
                            </div>

                            <div className="rounded-2xl border border-slate-200 bg-white p-3 shadow-[0_24px_70px_rgba(15,23,42,.16)] sm:p-4" aria-label="Prévia ilustrativa do painel Trilha+">
                                <div className="flex items-center justify-between gap-3 border-b border-slate-100 px-2 pb-4 pt-1 sm:px-3">
                                    <div className="flex min-w-0 items-center gap-3"><span className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-blue-50 font-black text-blue-700">E</span><div className="min-w-0"><strong className="block truncate text-sm text-slate-900">Empresa da equipe</strong><span className="text-xs text-slate-500">Visão administrativa</span></div></div>
                                    <span className="shrink-0 rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700">● Ativo</span>
                                </div>
                                <div className="grid gap-4 pt-4 sm:grid-cols-[148px_1fr]">
                                    <aside className="hidden rounded-xl bg-slate-950 p-3 text-xs text-slate-300 sm:block" aria-hidden="true">
                                        <p className="mb-3 px-2 text-[10px] font-black uppercase tracking-widest text-slate-500">Menu</p>
                                        <div className="space-y-1"><div className="rounded-lg bg-blue-600 px-3 py-2 font-bold text-white">Projetos</div><div className="px-3 py-2">Backlog</div><div className="px-3 py-2">Equipe</div><div className="px-3 py-2">Auditoria</div></div>
                                    </aside>
                                    <div className="min-w-0 space-y-4 px-1 sm:px-0">
                                        <div className="flex items-end justify-between gap-3"><div><p className="text-[10px] font-black uppercase tracking-widest text-blue-700">Visão geral</p><h2 className="mt-1 text-lg font-extrabold tracking-tight text-slate-900">Rotina de projetos</h2></div><span className="rounded-md border border-slate-200 px-2 py-1 text-[10px] font-bold text-slate-500">DEMONSTRAÇÃO</span></div>
                                        <div className="grid grid-cols-3 gap-2">
                                            <PreviewStat label="Projetos" value="12" tone="blue" />
                                            <PreviewStat label="Em andamento" value="08" tone="green" />
                                            <PreviewStat label="Equipe" value="24" tone="amber" />
                                        </div>
                                        <div className="overflow-hidden rounded-xl border border-slate-200">
                                            <div className="grid grid-cols-[1.1fr_repeat(3,.7fr)] gap-1 bg-slate-50 px-3 py-2 text-[10px] font-bold text-slate-500"><span>Projeto</span><span>Semana 1</span><span>Semana 2</span><span>Semana 3</span></div>
                                            <TimelineRow title="Planejamento" color="bg-blue-500" span="col-span-2" />
                                            <TimelineRow title="Entrega inicial" color="bg-emerald-500" span="col-span-2" offset />
                                            <TimelineRow title="Revisão" color="bg-amber-500" span="col-span-1" offset />
                                        </div>
                                        <div className="flex items-start gap-3 rounded-lg border border-blue-100 bg-blue-50 p-3 text-xs leading-5 text-blue-900"><span className="mt-0.5">◆</span><p><strong>Atividade registrada.</strong> Alterações nos dados e nos perfis ficam disponíveis para consulta administrativa.</p></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section id="recursos" className="scroll-mt-20 py-16 sm:py-20">
                        <div className="mx-auto max-w-7xl px-5 sm:px-8">
                            <SectionIntro eyebrow="Recursos" title="Organização clara, do primeiro acesso ao acompanhamento." description="A identidade visual do painel continua na página inicial: navegação escura, azul primário, superfícies claras e indicadores simples por estado." />
                            <div className="mt-9 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                <Feature number="01" title="Projetos e backlog" text="Organize iniciativas, prioridades, tarefas e entregas em uma visão objetiva para a equipe." />
                                <Feature number="02" title="Permissões por empresa" text="Administre papéis e capacidades da equipe sem abrir os dados de outra organização." />
                                <Feature number="03" title="CNPJ e CPF" text="Identifique a empresa pelo CNPJ e o administrador principal pelo CPF, com validação dos dois documentos." />
                                <Feature number="04" title="Trilha de auditoria" text="Consulte acessos e alterações registrados para apoiar acompanhamento e investigação." />
                            </div>
                        </div>
                    </section>

                    <section id="fluxo" className="scroll-mt-20 border-y border-slate-200 bg-white py-16 sm:py-20">
                        <div className="mx-auto max-w-7xl px-5 sm:px-8">
                            <SectionIntro eyebrow="Fluxo" title="Da empresa à entrega, com responsabilidades definidas." />
                            <div className="mt-9 grid gap-4 md:grid-cols-3">
                                <Step number="01" title="Vincule a empresa" text="Identifique o ambiente pelo CNPJ ou CPF e designe quem pode administrar o acesso." />
                                <Step number="02" title="Prepare a equipe" text="Atribua o perfil adequado e conceda apenas as permissões necessárias ao trabalho." />
                                <Step number="03" title="Acompanhe a execução" text="Mantenha projetos e backlog organizados e consulte a trilha de atividade da empresa." />
                            </div>
                        </div>
                    </section>

                    <section id="acesso" className="scroll-mt-20 py-16 sm:py-20">
                        <div className="mx-auto max-w-7xl px-5 sm:px-8">
                            <div className="overflow-hidden rounded-2xl bg-slate-950 p-7 text-white shadow-lg sm:p-10 lg:flex lg:items-center lg:justify-between lg:gap-12">
                                <div className="max-w-2xl"><p className="text-xs font-black uppercase tracking-[.18em] text-blue-300">Acesso sob controle</p><h2 className="mt-3 text-3xl font-black tracking-tight sm:text-4xl">Um ambiente por empresa. Permissões para cada papel.</h2><p className="mt-4 leading-7 text-slate-300">O administrador da organização cuida da própria equipe; a plataforma registra as atividades realizadas no painel.</p></div>
                                <div className="mt-7 grid shrink-0 grid-cols-3 gap-2 lg:mt-0">
                                    <AccessChip title="Master" detail="Global" />
                                    <AccessChip title="Admin" detail="Da empresa" />
                                    <AccessChip title="Usuário" detail="Permissões" />
                                </div>
                            </div>
                        </div>
                    </section>

                    <section className="border-t border-blue-100 bg-blue-50/60 py-14 sm:py-16">
                        <div className="mx-auto flex max-w-7xl flex-col items-start gap-6 px-5 sm:px-8 md:flex-row md:items-center md:justify-between">
                            <div><p className="text-xs font-black uppercase tracking-[.18em] text-blue-700">Comece agora</p><h2 className="mt-2 text-2xl font-black tracking-tight text-slate-950 sm:text-3xl">Abra o painel e continue sua rotina.</h2><p className="mt-2 text-sm text-slate-600">Entre com seu usuário ou cadastre sua empresa para começar.</p></div>
                            <Link href={mainHref} className="inline-flex min-h-11 shrink-0 items-center gap-2 rounded-lg bg-blue-600 px-5 py-3 text-sm font-extrabold text-white shadow transition hover:-translate-y-0.5 hover:bg-blue-700">{mainLabel}<ArrowIcon /></Link>
                        </div>
                    </section>
                </main>

                <footer className="bg-slate-950 text-slate-300">
                    <div className="mx-auto flex max-w-7xl flex-col gap-3 px-5 py-7 text-sm sm:px-8 md:flex-row md:items-center md:justify-between">
                        <span className="flex items-center gap-2 font-black text-white"><ApplicationLogo className="h-7 w-7" />Trilha+</span>
                        <span>Gestão de projetos, equipes, empresas e atividade.</span>
                        <Link href={route('company.versions.index')} className="font-semibold text-blue-300 hover:text-white">Histórico de versões</Link>
                    </div>
                </footer>
            </div>
        </>
    );
}

function ArrowIcon() {
    return <svg aria-hidden="true" className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M5 12h14" /><path d="m13 6 6 6-6 6" /></svg>;
}

function Metric({ title, detail }: { title: string; detail: string }) {
    return <div className="rounded-lg bg-white/80 px-3 py-2.5"><dt className="text-sm font-black text-slate-900">{title}</dt><dd className="mt-1 text-xs leading-5 text-slate-500">{detail}</dd></div>;
}

function PreviewStat({ label, value, tone }: { label: string; value: string; tone: 'blue' | 'green' | 'amber' }) {
    const tones = { blue: 'border-blue-100 bg-blue-50 text-blue-700', green: 'border-emerald-100 bg-emerald-50 text-emerald-700', amber: 'border-amber-100 bg-amber-50 text-amber-700' };
    return <div className={`rounded-lg border px-3 py-2 ${tones[tone]}`}><span className="block text-[10px] font-bold opacity-80">{label}</span><strong className="mt-1 block text-xl font-black">{value}</strong></div>;
}

function TimelineRow({ title, color, span, offset = false }: { title: string; color: string; span: string; offset?: boolean }) {
    return <div className="grid grid-cols-[1.1fr_repeat(3,.7fr)] items-center gap-1 border-t border-slate-100 px-3 py-3"><strong className="truncate text-[10px] text-slate-700">{title}</strong><span className={`${offset ? 'col-start-3' : 'col-start-2'} ${span} h-2.5 rounded-full ${color}`} /></div>;
}

function SectionIntro({ eyebrow, title, description }: { eyebrow: string; title: string; description?: string }) {
    return <div className="max-w-2xl"><p className="text-xs font-black uppercase tracking-[.18em] text-blue-700">{eyebrow}</p><h2 className="mt-2 text-3xl font-black tracking-[-.04em] text-slate-950 sm:text-4xl">{title}</h2>{description && <p className="mt-3 leading-7 text-slate-600">{description}</p>}</div>;
}

function Feature({ number, title, text }: { number: string; title: string; text: string }) {
    return <article className="group rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-1 hover:border-blue-200 hover:shadow-md"><span className="grid h-9 w-9 place-items-center rounded-lg bg-blue-50 text-xs font-black text-blue-700 transition group-hover:bg-blue-600 group-hover:text-white">{number}</span><h3 className="mt-4 text-base font-extrabold text-slate-900">{title}</h3><p className="mt-2 text-sm leading-6 text-slate-600">{text}</p></article>;
}

function Step({ number, title, text }: { number: string; title: string; text: string }) {
    return <article className="relative rounded-xl border border-slate-200 bg-slate-50 p-5"><span className="text-xs font-black tracking-widest text-blue-700">{number}</span><h3 className="mt-3 text-lg font-extrabold text-slate-900">{title}</h3><p className="mt-2 text-sm leading-6 text-slate-600">{text}</p></article>;
}

function AccessChip({ title, detail }: { title: string; detail: string }) {
    return <div className="min-w-20 rounded-lg border border-slate-700 bg-slate-900 px-3 py-3 text-center"><strong className="block text-sm font-extrabold">{title}</strong><span className="mt-1 block text-[10px] text-slate-400">{detail}</span></div>;
}
