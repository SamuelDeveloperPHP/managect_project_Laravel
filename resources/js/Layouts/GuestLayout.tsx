import { Link } from '@inertiajs/react';
import { PropsWithChildren } from 'react';
import ApplicationLogo from '@/Components/ApplicationLogo';
import ProductFooter from '@/Components/ProductFooter';

const levels = [
    { name: 'Projeto', text: 'Cliente, líder, equipe, prazo e orçamento.' },
    { name: 'Backlogs', text: 'Entregas e requisitos organizados dentro do projeto.' },
    { name: 'Tarefas no Gantt', text: 'Cronograma, dependências e progresso de cada backlog.' },
];

export default function Guest({ children }: PropsWithChildren) {
    return (
        <div className="flex min-h-screen flex-col bg-slate-50 text-slate-900 lg:flex-row">
            <aside className="flex flex-col justify-between bg-indigo-700 px-6 py-6 text-white sm:px-10 sm:py-8 lg:w-[44%] lg:px-14 lg:py-12 xl:px-20">
                <Link href="/" className="inline-flex w-fit items-center gap-3 rounded focus-visible:outline-offset-4" aria-label="Trilha+, página inicial">
                    <ApplicationLogo className="h-10 w-10" />
                    <span className="font-display text-xl font-bold tracking-tight">Trilha+</span>
                </Link>

                <div className="hidden max-w-lg py-12 lg:block">
                    <h1 className="text-4xl font-bold leading-tight xl:text-5xl">Do plano à entrega, no caminho certo.</h1>
                    <p className="mt-5 text-base leading-7 text-indigo-100">Cada empresa trabalha em seu próprio ambiente, com permissões definidas e histórico de atividade.</p>

                    <ol className="relative mt-10 space-y-6 border-l border-indigo-400/60 pl-6">
                        {levels.map((level) => (
                            <li key={level.name} className="relative">
                                <span aria-hidden="true" className="absolute -left-[31px] top-1.5 h-2.5 w-2.5 rounded-full bg-signal-300" />
                                <p className="font-semibold text-white">{level.name}</p>
                                <p className="mt-0.5 text-sm text-indigo-100">{level.text}</p>
                            </li>
                        ))}
                    </ol>
                </div>

                <p className="mt-6 max-w-sm text-sm text-indigo-100 lg:mt-0">Projetos, equipes e permissões em um único ambiente.</p>
            </aside>

            <main className="flex flex-1 items-center justify-center px-5 py-9 sm:px-8 lg:px-10 xl:px-16">
                <div className="w-full max-w-md">
                    <section className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm sm:p-9">
                        {children}
                    </section>
                    <footer className="mt-6 flex flex-wrap items-center justify-between gap-3 px-1 text-xs text-slate-500">
                        <ProductFooter />
                        <Link href="/" className="font-medium text-slate-600 hover:text-indigo-700">Página inicial</Link>
                    </footer>
                </div>
            </main>
        </div>
    );
}
