import { btnPrimary, EmptyState, PageHeader, StatusBadge } from '@/Components/ui';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

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
    const newProject = canManageProjects ? <Link href={route('projects.create')} className={btnPrimary}>Novo projeto</Link> : null;

    return (
        <AuthenticatedLayout header={<PageHeader title="Projetos" meta={projects.length ? `${projects.length} ${projects.length === 1 ? 'projeto' : 'projetos'} na empresa` : undefined} actions={newProject} />}>
            <Head title="Projetos" />
            <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                {projects.length === 0 ? (
                    <EmptyState title="Comece pelo seu primeiro projeto" text="Depois do cadastro, você poderá organizar os backlogs e as tarefas do projeto." action={newProject} />
                ) : (
                    <ul className="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                        {projects.map((project) => (
                            <li key={project.id} className="group flex flex-col rounded-3xl border border-neutral-200 bg-white p-6 shadow-sm transition hover:-translate-y-0.5 hover:border-brand-200 hover:shadow-[0_24px_48px_-28px_rgba(8,33,38,.35)]">
                                <div className="flex items-start justify-between gap-3">
                                    {project.image_url
                                        ? <img src={project.image_url} alt="" className="h-12 w-12 shrink-0 rounded-2xl object-cover" />
                                        : <span aria-hidden="true" className="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-brand-900 font-display text-sm font-extrabold text-white">{project.code.slice(0, 2).toUpperCase()}</span>}
                                    <StatusBadge status={project.status} label={statuses[project.status] ?? project.status} />
                                </div>
                                <div className="mt-4 min-w-0">
                                    <Link href={route('projects.overview', project.id)} className="block truncate font-display text-lg font-extrabold tracking-tight text-neutral-950 transition group-hover:text-brand-700 focus-visible:outline-none focus-visible:underline">{project.name}</Link>
                                    <p className="mt-0.5 truncate font-mono text-xs text-neutral-500">{project.code}</p>
                                </div>

                                <p className="mt-3 line-clamp-2 min-h-10 text-sm leading-5 text-neutral-600">{project.description || (project.client ? `Cliente: ${project.client}` : 'Sem descrição.')}</p>

                                <dl className="mt-5 grid grid-cols-3 gap-3 border-t border-neutral-100 pt-4 text-sm">
                                    <div><dt className="text-xs text-neutral-500">Backlogs</dt><dd className="mt-0.5 font-display text-xl font-bold text-neutral-900">{project.backlogs_count}</dd></div>
                                    <div><dt className="text-xs text-neutral-500">Tarefas</dt><dd className="mt-0.5 font-display text-xl font-bold text-neutral-900">{project.tasks_count}</dd></div>
                                    <div><dt className="text-xs text-neutral-500">Prazo</dt><dd className="mt-1 text-sm font-semibold text-neutral-800">{project.deadline ? formatDate(project.deadline) : 'Sem prazo'}</dd></div>
                                </dl>

                                <div className="mt-5 flex items-center justify-between gap-3 text-sm font-semibold">
                                    <Link href={route('projects.overview', project.id)} className="whitespace-nowrap rounded text-brand-700 transition hover:text-brand-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500">Visão geral →</Link>
                                    <span className="flex gap-4">
                                        <Link href={route('projects.backlog.index', project.id)} className="rounded text-neutral-600 transition hover:text-neutral-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500">Backlogs</Link>
                                        {canManageProjects && <Link href={route('projects.edit', project.id)} className="rounded text-neutral-600 transition hover:text-neutral-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500">Editar</Link>}
                                    </span>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
