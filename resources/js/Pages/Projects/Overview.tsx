import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

type Project = { id: number; name: string; code: string; status: string; client: string | null; start_date: string | null; deadline: string | null };
type Backlog = { id: number; code: string; name: string; description: string | null; status: string; items_count: number; tasks_count: number };

const statuses: Record<string, string> = { planning: 'Planejamento', active: 'Em andamento', in_progress: 'Em andamento', on_hold: 'Pausado', completed: 'Concluído', cancelled: 'Cancelado' };
const formatDate = (value: string) => new Date(value.length === 10 ? `${value}T00:00:00` : value).toLocaleDateString('pt-BR');

export default function Overview({ project, backlogs }: { project: Project; backlogs: Backlog[] }) {
    return <AuthenticatedLayout header={<div className="flex flex-wrap items-center justify-between gap-3"><div><Link href={route('projects.index')} className="text-sm font-medium text-indigo-600 hover:text-indigo-700">← Projetos</Link><h2 className="mt-1 text-2xl font-semibold text-slate-900">{project.name}</h2></div><Link href={route('projects.backlog.index', project.id)} className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700">Backlogs do projeto</Link></div>}>
        <Head title={`${project.code} — Projeto`} />
        <div className="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
            <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <dl className="grid gap-x-8 gap-y-3 text-sm sm:grid-cols-2 lg:grid-cols-5">
                    <div><dt className="text-slate-500">Código</dt><dd className="font-medium text-slate-900">{project.code}</dd></div>
                    <div><dt className="text-slate-500">Status</dt><dd className="font-medium text-slate-900">{statuses[project.status] ?? project.status}</dd></div>
                    <div><dt className="text-slate-500">Cliente</dt><dd className="font-medium text-slate-900">{project.client || 'Não informado'}</dd></div>
                    <div><dt className="text-slate-500">Início</dt><dd className="font-medium text-slate-900">{project.start_date ? formatDate(project.start_date) : 'Não informado'}</dd></div>
                    <div><dt className="text-slate-500">Prazo</dt><dd className="font-medium text-slate-900">{project.deadline ? formatDate(project.deadline) : 'Sem prazo'}</dd></div>
                </dl>
            </section>
            <section>
                <div className="mb-4 flex items-end justify-between gap-3"><div><h2 className="text-lg font-semibold text-slate-900">Backlogs</h2><p className="mt-1 text-sm text-slate-500">Cada backlog agrupa itens e tem seu próprio Gantt.</p></div><Link href={route('projects.backlog.index', project.id)} className="text-sm font-semibold text-indigo-700 hover:text-indigo-900">Ver todos</Link></div>
                {backlogs.length ? <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">{backlogs.map((backlog) => <article key={backlog.id} className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"><div className="flex items-start justify-between gap-3"><span className="rounded bg-indigo-50 px-2 py-1 text-xs font-semibold text-indigo-700">{backlog.code}</span><span className="text-xs font-medium text-emerald-700">{backlog.status === 'active' ? 'Ativo' : backlog.status}</span></div><Link href={route('projects.backlog.show', [project.id, backlog.id])} className="mt-4 block font-semibold text-slate-900 hover:text-indigo-700">{backlog.name}</Link><p className="mt-2 line-clamp-2 min-h-10 text-sm text-slate-500">{backlog.description || 'Sem descrição.'}</p><div className="mt-4 flex flex-wrap gap-x-4 gap-y-2 border-t border-slate-100 pt-3 text-sm"><Link href={route('projects.backlog.show', [project.id, backlog.id])} className="font-medium text-indigo-700 hover:text-indigo-900">Abrir backlog · {backlog.items_count} itens</Link><span className="font-medium text-slate-500">{backlog.tasks_count} tarefas no Gantt</span></div></article>)}</div> : <div className="rounded-xl border border-dashed border-slate-300 bg-white p-10 text-center"><h3 className="font-semibold text-slate-900">Este projeto ainda não tem backlogs</h3><p className="mt-2 text-sm text-slate-500">Crie um backlog para organizar os itens e as tarefas do cronograma.</p><Link href={route('projects.backlog.index', project.id)} className="mt-4 inline-flex rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Criar primeiro backlog</Link></div>}
            </section>
        </div>
    </AuthenticatedLayout>;
}
