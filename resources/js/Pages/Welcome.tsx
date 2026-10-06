import { Head, Link } from '@inertiajs/react';
import { ReactNode } from 'react';
import ApplicationLogo from '@/Components/ApplicationLogo';

type WelcomeProps = {
    canRegister: boolean;
    auth?: { user: { id: number; name: string; role?: string } | null };
};

const steps = [
    { number: '01', title: 'Projeto', text: 'Cliente, líder, equipe, prazo e orçamento definidos na criação, com anexos e imagem do projeto.' },
    { number: '02', title: 'Backlogs', text: 'As entregas e os requisitos organizados dentro do projeto, com código gerado automaticamente.' },
    { number: '03', title: 'Tarefas no Gantt', text: 'Cronograma, dependências e progresso de cada backlog em uma linha do tempo.' },
];

const facts = [
    { title: 'Isolamento entre empresas', text: 'Toda consulta é filtrada pela empresa do usuário. Não há como abrir dados de outra organização.' },
    { title: 'Um administrador por empresa', text: 'Com transferência controlada e recuperação de acesso por dois e-mails.' },
    { title: 'Login só por e-mail e senha', text: 'Sem documentos como credencial. Tentativas e ações sensíveis têm limite de taxa.' },
    { title: 'Auditoria de ponta a ponta', text: 'Ações relevantes ficam registradas e disponíveis para consulta administrativa.' },
];

const ganttRows = [
    { name: 'Descoberta', start: 2, end: 5, progress: 100, tone: 'from-brand-400 to-brand-500' },
    { name: 'Design do portal', start: 3, end: 7, progress: 85, tone: 'from-blue-500 to-sky-400' },
    { name: 'Desenvolvimento', start: 5, end: 9, progress: 55, tone: 'from-emerald-500 to-emerald-400' },
    { name: 'Homologação', start: 7, end: 10, progress: 12, tone: 'from-amber-500 to-signal-300' },
];

export default function Welcome({ canRegister, auth }: WelcomeProps) {
    const loggedIn = Boolean(auth?.user);
    const mainHref = loggedIn ? route('projects.index') : canRegister ? route('register') : route('login');
    const mainLabel = loggedIn ? 'Abrir painel' : canRegister ? 'Fazer cadastro' : 'Acessar Trilha+';

    return (
        <>
            <Head title="Gestão de projetos multiempresa">
                <meta name="description" content="Organize projetos, backlogs e cronogramas em uma plataforma multiempresa, com permissões por perfil e histórico de atividade." />
            </Head>

            <div className="min-h-screen bg-white font-sans text-neutral-900 antialiased [scroll-behavior:smooth]">
                <header className="sticky top-0 z-30 border-b border-white/10 bg-brand-950/75 backdrop-blur-xl">
                    <div className="mx-auto flex h-[68px] max-w-[1180px] items-center gap-8 px-5 sm:px-7">
                        <a href="#inicio" className="flex items-center gap-2.5 font-display text-lg font-extrabold tracking-tight text-white" aria-label="Trilha+ — início">
                            <ApplicationLogo className="h-8 w-8" />
                            Trilha+
                        </a>
                        <nav className="ml-3 hidden items-center gap-7 text-sm font-medium text-brand-200 md:flex" aria-label="Seções da página">
                            <a className="transition hover:text-white" href="#fluxo">Como funciona</a>
                            <a className="transition hover:text-white" href="#recursos">Recursos</a>
                            <a className="transition hover:text-white" href="#seguranca">Segurança</a>
                        </nav>
                        <div className="ml-auto flex items-center gap-2">
                            <Link href={route('company.versions.index')} className="hidden rounded-xl px-3 py-2 text-sm font-medium text-brand-200 transition hover:text-white lg:inline-flex">Versões</Link>
                            {!loggedIn && <Link href={route('login')} className="hidden min-h-11 items-center rounded-xl border border-white/20 px-5 text-sm font-semibold text-brand-50 transition hover:bg-white/10 sm:inline-flex">Entrar</Link>}
                            <Link href={mainHref} className="inline-flex min-h-11 items-center rounded-xl bg-signal-300 px-5 text-sm font-semibold text-[#1b1305] transition hover:bg-[#efc36f] hover:shadow-[0_8px_24px_-8px_rgba(229,180,84,.6)] active:translate-y-px">{mainLabel}</Link>
                        </div>
                    </div>
                </header>

                <main id="inicio">
                    <section className="relative isolate overflow-hidden bg-brand-950 pt-20 text-white sm:pt-24">
                        <div aria-hidden="true" className="absolute inset-0 -z-10 bg-[radial-gradient(60%_55%_at_78%_8%,rgba(42,102,112,.55),transparent_70%),radial-gradient(40%_40%_at_8%_90%,rgba(201,138,18,.14),transparent_70%)]" />
                        <div aria-hidden="true" className="absolute inset-0 -z-10 bg-[linear-gradient(rgba(255,255,255,.035)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,.035)_1px,transparent_1px)] bg-[size:56px_56px] [mask-image:radial-gradient(70%_60%_at_50%_30%,#000,transparent_80%)]" />

                        <div className="mx-auto max-w-[1180px] px-5 sm:px-7">
                            <p className="inline-flex items-center gap-2.5 rounded-full border border-white/15 bg-white/[.04] py-1.5 pl-3 pr-4 text-[13px] text-brand-200">
                                <span aria-hidden="true" className="h-1.5 w-1.5 rounded-full bg-emerald-400" />
                                Cronograma Gantt integrado ao backlog
                            </p>
                            <h1 className="mt-6 max-w-[15ch] font-display text-[clamp(42px,6.2vw,76px)] font-extrabold leading-[1.03] tracking-[-.035em]">
                                Seus projetos, <span className="bg-gradient-to-r from-white to-signal-300 bg-clip-text text-transparent">do plano à entrega.</span>
                            </h1>
                            <p className="mt-6 max-w-[54ch] text-[clamp(17px,1.6vw,20px)] leading-relaxed text-brand-200">
                                Projetos, backlogs e cronograma no mesmo lugar. Cada empresa em seu próprio ambiente, com permissões claras e histórico de tudo o que muda.
                            </p>
                            <div className="mt-9 flex flex-wrap gap-3">
                                <Link href={mainHref} className="inline-flex min-h-[52px] items-center rounded-2xl bg-signal-300 px-7 text-base font-semibold text-[#1b1305] transition hover:bg-[#efc36f] hover:shadow-[0_8px_24px_-8px_rgba(229,180,84,.6)] active:translate-y-px">{mainLabel} →</Link>
                                <a href="#fluxo" className="inline-flex min-h-[52px] items-center rounded-2xl border border-white/20 px-7 text-base font-semibold text-brand-50 transition hover:bg-white/10">Ver como funciona</a>
                            </div>
                            <ul className="mt-8 flex flex-wrap gap-x-7 gap-y-2 text-sm text-brand-300">
                                {['Dados isolados por empresa', 'Histórico de atividade', 'Permissões por perfil'].map((item) => (
                                    <li key={item} className="flex items-center gap-2.5"><span aria-hidden="true" className="h-1.5 w-1.5 rounded-full bg-emerald-400" />{item}</li>
                                ))}
                            </ul>

                            <ProductPreview />
                        </div>
                    </section>

                    <section id="fluxo" className="scroll-mt-16 py-24 sm:py-28">
                        <div className="mx-auto max-w-[1180px] px-5 sm:px-7">
                            <Eyebrow>Como funciona</Eyebrow>
                            <H2>Três níveis. Nenhuma planilha no meio.</H2>
                            <p className="mt-5 max-w-[58ch] text-lg text-neutral-600">Do contrato ao cronograma, cada coisa tem seu lugar e todo mundo sabe onde olhar.</p>
                            <ol className="mt-14 grid gap-5 md:grid-cols-3">
                                {steps.map((step, index) => (
                                    <li key={step.number} className={`rounded-3xl border p-8 ${index === 1 ? 'border-brand-900 bg-brand-900 text-white' : 'border-neutral-100 bg-neutral-50'}`}>
                                        <span className={`font-mono text-[13px] ${index === 1 ? 'text-signal-300' : 'text-brand-500'}`}>{step.number}</span>
                                        <h3 className="mb-2.5 mt-12 font-display text-2xl font-extrabold tracking-tight">{step.title}</h3>
                                        <p className={`text-[15.5px] leading-relaxed ${index === 1 ? 'text-brand-200' : 'text-neutral-600'}`}>{step.text}</p>
                                    </li>
                                ))}
                            </ol>
                        </div>
                    </section>

                    <section id="recursos" className="scroll-mt-16 pb-24 sm:pb-28">
                        <div className="mx-auto max-w-[1180px] px-5 sm:px-7">
                            <Eyebrow>Recursos</Eyebrow>
                            <H2>O que você precisa para tocar o projeto.</H2>
                            <div className="mt-14 grid gap-5 md:grid-cols-6">
                                <Card className="md:col-span-3" icon={<path d="M4 6h10M8 12h12M6 18h9" />} title="Gantt de verdade">
                                    Arraste, redimensione e ligue tarefas em uma linha do tempo. Cada backlog tem o seu cronograma.
                                </Card>
                                <Card className="md:col-span-3" icon={<><path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z" /><path d="m9 12 2 2 4-4" /></>} title="Cada empresa no seu ambiente">
                                    Usuários, projetos e arquivos nunca se misturam entre organizações. O isolamento é regra do sistema, não configuração.
                                </Card>
                                <article className="rounded-3xl border border-neutral-200 p-8 transition hover:-translate-y-0.5 hover:shadow-[0_24px_48px_-28px_rgba(8,33,38,.35)] md:col-span-2">
                                    <h3 className="font-display text-[22px] font-extrabold tracking-tight">Histórico de atividade</h3>
                                    <p className="mt-2.5 text-[15.5px] text-neutral-600">Quem mudou o quê e quando.</p>
                                    <div className="mt-5 rounded-2xl bg-brand-950 px-4 py-1.5 font-mono text-xs text-brand-200" aria-hidden="true">
                                        {[['14:02', 'Ana', 'moveu “Homologação”'], ['13:47', 'Rui', 'anexou contrato.pdf'], ['11:20', 'Léo', 'criou BL-04']].map(([time, who, what]) => (
                                            <div key={time} className="flex gap-3 border-b border-white/10 py-2.5 last:border-0"><time className="text-brand-400">{time}</time><span><b className="font-medium text-white">{who}</b> {what}</span></div>
                                        ))}
                                    </div>
                                </article>
                                <article className="rounded-3xl border border-neutral-200 p-8 transition hover:-translate-y-0.5 hover:shadow-[0_24px_48px_-28px_rgba(8,33,38,.35)] md:col-span-2">
                                    <h3 className="font-display text-[22px] font-extrabold tracking-tight">Permissões por perfil</h3>
                                    <p className="mt-2.5 text-[15.5px] text-neutral-600">Administrador, gestor de projetos e colaborador veem e fazem só o que lhes cabe.</p>
                                    <div className="mt-5 flex flex-wrap gap-2">
                                        {['Administrador', 'Gestor', 'Colaborador'].map((role) => <span key={role} className="rounded-full bg-brand-50 px-3.5 py-1.5 text-[13px] font-medium text-brand-700">{role}</span>)}
                                    </div>
                                </article>
                                <Card className="md:col-span-2" icon={<><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z" /><path d="M14 3v5h5" /></>} title="Arquivos sob controle">
                                    Anexos privados, PDFs verificados antes de entrar e limite de 50 MB por projeto.
                                </Card>
                            </div>
                        </div>
                    </section>

                    <section id="seguranca" className="relative isolate scroll-mt-16 overflow-hidden bg-brand-950 py-24 text-white sm:py-28">
                        <div aria-hidden="true" className="absolute -right-[10%] -top-[30%] -z-10 h-[140%] w-3/5 bg-[radial-gradient(closest-side,rgba(42,102,112,.5),transparent)]" />
                        <div className="mx-auto max-w-[1180px] px-5 sm:px-7">
                            <p className="mb-3.5 text-[13px] font-semibold text-signal-300">Segurança</p>
                            <h2 className="max-w-[20ch] font-display text-[clamp(32px,4vw,50px)] font-extrabold leading-[1.05] tracking-[-.035em]">Confiança é o que o sistema faz, não o que ele promete.</h2>
                            <p className="mt-5 max-w-[58ch] text-lg text-brand-200">Estas regras já valem hoje, em todas as empresas.</p>
                            <dl className="mt-14 grid gap-x-14 md:grid-cols-2">
                                {facts.map((fact, index) => (
                                    <div key={fact.title} className="flex gap-5 border-t border-white/10 py-7">
                                        <span className="pt-1 font-mono text-[13px] text-signal-300">{String(index + 1).padStart(2, '0')}</span>
                                        <div><dt className="font-display text-xl font-bold tracking-tight">{fact.title}</dt><dd className="mt-1.5 text-[15px] leading-relaxed text-brand-300">{fact.text}</dd></div>
                                    </div>
                                ))}
                            </dl>
                        </div>
                    </section>

                    <section className="py-24 text-center sm:py-28">
                        <div className="mx-auto max-w-[1180px] px-5 sm:px-7">
                            <h2 className="mx-auto max-w-[20ch] font-display text-[clamp(32px,4vw,50px)] font-extrabold leading-[1.05] tracking-[-.035em]">Comece com a sua equipe hoje.</h2>
                            <p className="mx-auto mt-5 max-w-[58ch] text-lg text-neutral-600">Faça o cadastro, convide as pessoas e abra o primeiro projeto.</p>
                            <div className="mt-9 flex flex-wrap justify-center gap-3">
                                <Link href={mainHref} className="inline-flex min-h-[52px] items-center rounded-2xl bg-brand-900 px-7 text-base font-semibold text-white transition hover:bg-brand-700 active:translate-y-px">{mainLabel} →</Link>
                                {!loggedIn && <Link href={route('login')} className="inline-flex min-h-[52px] items-center rounded-2xl border border-neutral-200 px-7 text-base font-semibold text-neutral-900 transition hover:bg-neutral-50">Já tenho conta</Link>}
                            </div>
                        </div>
                    </section>
                </main>

                <footer className="border-t border-neutral-200 py-8 text-sm text-neutral-600">
                    <div className="mx-auto flex max-w-[1180px] flex-wrap items-center gap-x-7 gap-y-3 px-5 sm:px-7">
                        <span className="flex items-center gap-2.5 font-display text-base font-extrabold tracking-tight text-neutral-900"><ApplicationLogo className="h-6 w-6" />Trilha+</span>
                        <span>© {new Date().getFullYear()} Trilha+ · Um produto NexoCore Tecnologia</span>
                        <Link href={route('company.versions.index')} className="font-semibold text-brand-700 transition hover:text-brand-900 sm:ml-auto">Histórico de versões</Link>
                    </div>
                </footer>
            </div>
        </>
    );
}

function Eyebrow({ children }: { children: string }) {
    return <p className="mb-3.5 text-[13px] font-semibold text-brand-500">{children}</p>;
}

function H2({ children }: { children: string }) {
    return <h2 className="max-w-[20ch] font-display text-[clamp(32px,4vw,50px)] font-extrabold leading-[1.05] tracking-[-.035em] text-neutral-950">{children}</h2>;
}

function Card({ title, icon, className = '', children }: { title: string; icon: ReactNode; className?: string; children: string }) {
    return (
        <article className={`rounded-3xl border border-neutral-200 p-8 transition hover:-translate-y-0.5 hover:shadow-[0_24px_48px_-28px_rgba(8,33,38,.35)] ${className}`}>
            <span aria-hidden="true" className="mb-5 grid h-11 w-11 place-items-center rounded-2xl bg-brand-50 text-brand-600">
                <svg className="h-[22px] w-[22px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">{icon}</svg>
            </span>
            <h3 className="font-display text-[22px] font-extrabold tracking-tight">{title}</h3>
            <p className="mt-2.5 text-[15.5px] leading-relaxed text-neutral-600">{children}</p>
        </article>
    );
}

/** Demonstração ilustrativa: os nomes e números abaixo não vêm do sistema. */
function ProductPreview() {
    const weeks = ['Set 1', 'Set 2', 'Set 3', 'Out 1', 'Out 2', 'Out 3', 'Nov 1', 'Nov 2'];
    const columns = 'grid-cols-[96px_repeat(8,minmax(0,1fr))] sm:grid-cols-[150px_repeat(8,minmax(0,1fr))]';

    return (
        <div className="mt-14 [perspective:1800px] sm:mt-16" role="img" aria-label="Demonstração ilustrativa: cronograma Gantt do projeto Portal do Cliente">
            <div className="grid origin-bottom overflow-hidden rounded-t-[18px] border border-b-0 border-white/10 bg-[#0e262b] shadow-[0_-10px_80px_-10px_rgba(42,102,112,.55),0_40px_80px_-30px_#000] lg:min-h-[460px] lg:grid-cols-[200px_1fr] lg:[transform:rotateX(7deg)]">
                <aside className="hidden border-r border-white/[.07] bg-[#0a1d21] p-4 text-[13px] text-neutral-400 lg:block">
                    <div className="flex items-center gap-2.5 px-2 pb-5 pt-1.5 font-semibold text-neutral-100"><span className="grid h-[26px] w-[26px] place-items-center rounded-lg bg-brand-600 font-display text-xs font-bold text-white">A</span>Atlas Engenharia</div>
                    {['Projetos', 'Backlogs', 'Equipe', 'Relatórios', 'Auditoria'].map((item, index) => (
                        <div key={item} className={`mb-0.5 flex items-center gap-2.5 rounded-[9px] px-2.5 py-2 ${index === 0 ? 'bg-white/[.08] text-white' : ''}`}><span className="h-3.5 w-3.5 rounded border-[1.5px] border-current opacity-70" />{item}</div>
                    ))}
                </aside>
                <div className="p-4 text-neutral-300 sm:p-6">
                    <div className="mb-5 flex flex-wrap items-center gap-3.5">
                        <h3 className="font-display text-xl font-extrabold tracking-tight text-white">Portal do Cliente</h3>
                        <span className="rounded-full bg-emerald-400/15 px-2.5 py-0.5 text-xs font-semibold text-emerald-300">● No prazo</span>
                        <div className="ml-auto flex">
                            {[['LM', 'bg-brand-300'], ['RC', 'bg-signal-300'], ['TS', 'bg-brand-200'], ['+3', 'bg-emerald-400']].map(([label, tone]) => <span key={label} className={`-ml-2 grid h-7 w-7 place-items-center rounded-full border-2 border-[#0e262b] text-[10.5px] font-semibold text-[#06191d] ${tone}`}>{label}</span>)}
                        </div>
                    </div>
                    <div className="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                        {[['Progresso', '62%'], ['Tarefas', '48'], ['Em atraso', '2'], ['Entrega', '14 nov']].map(([label, value]) => (
                            <div key={label} className="rounded-xl border border-white/[.07] bg-white/[.04] px-3.5 py-3"><p className="text-xs text-neutral-400">{label}</p><p className="mt-0.5 font-display text-[22px] font-bold text-white">{value}</p></div>
                        ))}
                    </div>
                    <div className="relative rounded-xl border border-white/[.07] bg-white/[.03] px-4 pb-2 pt-3.5">
                        <div className={`grid ${columns} items-center border-b border-white/[.07] pb-2.5 text-[11.5px] text-neutral-500`}>
                            <span>Backlog</span>{weeks.map((week) => <span key={week} className="truncate">{week}</span>)}
                        </div>
                        {ganttRows.map((row) => (
                            <div key={row.name} className={`grid ${columns} h-[38px] items-center border-b border-white/[.04] text-[11.5px] text-neutral-300 last:border-0`}>
                                <span className="truncate pr-2">{row.name}</span>
                                <div className={`relative row-start-1 h-[18px] rounded-[7px] bg-gradient-to-r ${row.tone} shadow-[0_4px_14px_-4px_rgba(0,0,0,.5)]`} style={{ gridColumn: `${row.start} / ${row.end}` }}>
                                    <span className="absolute inset-y-0 left-0 rounded-[7px] bg-white/30" style={{ width: `${row.progress}%` }} />
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </div>
    );
}
