import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

type Project = { id: number; name: string; code: string; status: string; client: string | null; start_date: string | null; deadline: string | null };
type Backlog = { id: number; code: string; name: string; description: string | null; status: string; items_count: number; tasks_count: number };

export default function Overview({ project, backlogs }: { project: Project; backlogs: Backlog[] }) {
    return <AuthenticatedLayout header={<div className="flex flex-wrap items-center justify-between gap-3"><div><Link href={route('projects.index')} className="text-sm font-medium text-indigo-600 hover:text-indigo-700">← Projetos</Link><h2 className="mt-1 text-2xl font-semibold text-slate-900">{project.name}</h2></div><Link href={route('projects.backlog.index', project.id)} className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700">Backlogs do projeto</Link></div>}>
        <Head title={`${project.code} — Projeto`} />
        <div className="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
            <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <p className="text-xs font-semibold uppercase tracking-wider text-indigo-600">Projeto · {project.code}</p>
                <div className="mt-4 flex flex-wrap gap-x-6 gap-y-2 text-sm text-slate-500">
                    <span>Status: {project.status}</span>
                    {project.client && <span>Cliente: {project.client}</span>}
                    {project.start_date && <span>Início: {project.start_date}</span>}
                    {project.deadline && <span>Prazo: {project.deadline}</span>}
                </div>
            </section>
            <section>
                <div className="mb-4 flex items-end justify-between gap-3"><div><p className="text-xs font-semibold uppercase tracking-wider text-indigo-600">Organização do trabalho</p><h2 className="mt-1 text-lg font-semibold text-slate-900">Backlogs</h2><p className="mt-1 text-sm text-slate-500">Cada backlog agrupa itens e tem seu próprio Gantt.</p></div><Link href={route('projects.backlog.index', project.id)} className="text-sm font-semibold text-indigo-700 hover:text-indigo-900">Ver todos</Link></div>
                {backlogs.length ? <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">{backlogs.map((backlog) => <article key={backlog.id} className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"><div className="flex items-start justify-between gap-3"><span className="rounded bg-indigo-50 px-2 py-1 text-xs font-semibold text-indigo-700">{backlog.code}</span><span className="text-xs font-medium text-emerald-700">{backlog.status === 'active' ? 'Ativo' : backlog.status}</span></div><Link href={route('projects.backlog.show', [project.id, backlog.id])} className="mt-4 block font-semibold text-slate-900 hover:text-indigo-700">{backlog.name}</Link><p className="mt-2 line-clamp-2 min-h-10 text-sm text-slate-500">{backlog.description || 'Sem descrição.'}</p><div className="mt-4 flex flex-wrap gap-x-4 gap-y-2 border-t border-slate-100 pt-3 text-sm"><Link href={route('projects.backlog.show', [project.id, backlog.id])} className="font-medium text-indigo-700 hover:text-indigo-900">Abrir backlog · {backlog.items_count} itens</Link><span className="font-medium text-slate-500">{backlog.tasks_count} tarefas no Gantt</span></div></article>)}</div> : <div className="rounded-xl border border-dashed border-slate-300 bg-white p-10 text-center"><h3 className="font-semibold text-slate-900">Este projeto ainda não tem backlogs</h3><p className="mt-2 text-sm text-slate-500">Crie um backlog para organizar os itens e as tarefas do cronograma.</p><Link href={route('projects.backlog.index', project.id)} className="mt-4 inline-flex rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Criar primeiro backlog</Link></div>}
            </section>
        </div>
    </AuthenticatedLayout>;
}
