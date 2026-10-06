import { Link } from '@inertiajs/react';
import { PropsWithChildren } from 'react';
import ApplicationLogo from '@/Components/ApplicationLogo';
import ProductFooter from '@/Components/ProductFooter';

const facts = [
    { title: 'Dados isolados por empresa', text: 'Usuários, projetos e arquivos nunca se misturam entre organizações.' },
    { title: 'Permissões por perfil', text: 'Cada pessoa vê e faz apenas o que lhe cabe.' },
    { title: 'Histórico de atividade', text: 'Quem mudou o quê e quando, disponível para consulta.' },
];

export default function Guest({ children, wide = false }: PropsWithChildren<{ wide?: boolean }>) {
    return (
        <div className="flex min-h-screen flex-col bg-white text-neutral-900 lg:flex-row">
            <aside className="relative isolate flex flex-col justify-between overflow-hidden bg-brand-950 px-6 py-6 text-white sm:px-10 sm:py-8 lg:w-[42%] lg:max-w-[640px] lg:px-14 lg:py-12">
                <div aria-hidden="true" className="absolute inset-0 -z-10 bg-[radial-gradient(60%_50%_at_85%_0%,rgba(42,102,112,.6),transparent_70%),radial-gradient(45%_40%_at_0%_100%,rgba(201,138,18,.16),transparent_70%)]" />
                <div aria-hidden="true" className="absolute inset-0 -z-10 bg-[linear-gradient(rgba(255,255,255,.04)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,.04)_1px,transparent_1px)] bg-[size:56px_56px] [mask-image:radial-gradient(70%_60%_at_40%_30%,#000,transparent_80%)]" />

                <Link href="/" className="inline-flex w-fit items-center gap-3 rounded-lg focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-signal-300" aria-label="Trilha+, página inicial">
                    <ApplicationLogo className="h-9 w-9" />
                    <span className="font-display text-xl font-extrabold tracking-tight">Trilha+</span>
                </Link>

                <div className="hidden max-w-md py-12 lg:block">
                    <h1 className="font-display text-[44px] font-extrabold leading-[1.05] tracking-[-.035em]">
                        Do plano <span className="bg-gradient-to-r from-white to-signal-300 bg-clip-text text-transparent">à entrega.</span>
                    </h1>
                    <p className="mt-5 text-base leading-7 text-brand-100/80">Projetos, backlogs e cronograma no mesmo lugar, com cada empresa em seu próprio ambiente.</p>

                    <ul className="mt-10 space-y-5">
                        {facts.map((fact) => (
                            <li key={fact.title} className="flex gap-4">
                                <span aria-hidden="true" className="mt-1 grid h-6 w-6 shrink-0 place-items-center rounded-full bg-emerald-400/15 text-emerald-300">
                                    <svg className="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round"><path d="m5 12 4 4L19 6" /></svg>
                                </span>
                                <div>
                                    <p className="font-semibold text-white">{fact.title}</p>
                                    <p className="mt-0.5 text-sm text-brand-100/70">{fact.text}</p>
                                </div>
                            </li>
                        ))}
                    </ul>
                </div>

                <p className="mt-6 hidden text-xs text-brand-100/60 lg:block">Um produto NexoCore Tecnologia</p>
            </aside>

            <main className="flex flex-1 flex-col items-center justify-center px-5 py-10 sm:px-8 lg:px-12">
                <div className={`w-full ${wide ? 'max-w-xl' : 'max-w-md'}`}>
                    {children}
                    <footer className="mt-10 flex flex-wrap items-center justify-between gap-3 border-t border-neutral-100 pt-5">
                        <ProductFooter />
                        <Link href="/" className="text-xs font-semibold text-neutral-600 transition hover:text-brand-700">Página inicial</Link>
                    </footer>
                </div>
            </main>
        </div>
    );
}
