import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';

type Project = {
    id: number; name: string; code: string; description: string | null; status: string;
    client: string | null; priority: string; start_date: string | null; deadline: string | null;
    backlogs_count: number; tasks_count: number; image_url: string | null;
};

const statuses: Record<string, string> = { planning: 'Planejamento', active: 'Em andamento', in_progress: 'Em andamento', on_hold: 'Pausado', completed: 'Concluído', cancelled: 'Cancelado' };

const formatDate = (value: string) => {
    const date = new Date(value.length === 10 ? `${value}T00:00:00` : value);

    return Number.isNaN(date.getTime()) ? 'Não informado' : date.toLocaleDateString('pt-BR');
};

export default function Index({ projects, canManageProjects }: { projects: Project[]; canManageProjects: boolean }) {
    return (
        <AuthenticatedLayout header={
            <div>
                <p className="text-sm font-medium text-indigo-600">Trilha</p>
                <h2 className="text-2xl font-semibold text-slate-900">Projetos</h2>
            </div>
        }>
            <Head title="Projetos" />
            <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <div className="mb-7 flex justify-end">
                    {canManageProjects && <Link href={route('projects.create')} className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700">Novo projeto</Link>}
                </div>
                {projects.length === 0 ? <div className="rounded-xl border border-dashed border-slate-300 bg-white p-12 text-center"><h3 className="font-semibold text-slate-900">Comece pelo seu primeiro projeto</h3><p className="mt-2 text-sm text-slate-500">Depois do cadastro, você poderá organizar os backlogs e as tarefas do projeto.</p></div> :
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">{projects.map((project) => <article key={project.id} className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm transition hover:border-indigo-300 hover:shadow-md">
                        {project.image_url && <img src={project.image_url} alt={`Imagem do projeto ${project.name}`} className="h-36 w-full object-cover" />}
                        <div className="p-5"><div className="flex items-start justify-between gap-3"><span className="rounded bg-indigo-50 px-2 py-1 text-xs font-semibold text-indigo-700">{project.code}</span><span className="text-xs font-medium text-emerald-700">{statuses[project.status] ?? project.status}</span></div>
                            <Link href={route('projects.overview', project.id)} className="mt-4 block font-semibold text-slate-900 hover:text-indigo-700">{project.name}</Link><p className="mt-2 line-clamp-2 text-sm text-slate-500">{project.description || 'Sem descrição.'}</p>
                            <p className="mt-4 text-xs text-slate-500">{project.client ? `Cliente: ${project.client} · ` : ''}{project.deadline ? `Prazo: ${formatDate(project.deadline)}` : 'Sem prazo definido'}</p>
                            <div className="mt-4 flex flex-wrap items-center gap-x-4 gap-y-2 border-t border-slate-100 pt-3 text-sm"><Link className="font-medium text-indigo-700 hover:text-indigo-900" href={route('projects.overview', project.id)}>Visão geral</Link><Link className="font-medium text-slate-600 hover:text-indigo-700" href={route('projects.backlog.index', project.id)}>{project.backlogs_count} backlogs</Link><span className="font-medium text-slate-500">{project.tasks_count} tarefas</span>
                                {canManageProjects && <Link className="ml-auto font-medium text-slate-600 hover:text-indigo-700" href={route('projects.edit', project.id)}>Editar</Link>}
                            </div>
                        </div>
                    </article>)}</div>}
            </div>
        </AuthenticatedLayout>
    );
}
