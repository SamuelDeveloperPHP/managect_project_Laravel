import BacklogCard from '@/Components/BacklogCard';
import { btnPrimary, EmptyState, PageHeader, panelClass, Stat, StatusBadge } from '@/Components/ui';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

type Project = { id: number; name: string; code: string; status: string; client: string | null; start_date: string | null; deadline: string | null };
type Backlog = { id: number; code: string; name: string; description: string | null; status: string; items_count: number; tasks_count: number };

const statuses: Record<string, string> = { planning: 'Planejamento', active: 'Em andamento', in_progress: 'Em andamento', on_hold: 'Pausado', completed: 'Concluído', cancelled: 'Cancelado' };
const formatDate = (value: string) => new Date(value.length === 10 ? `${value}T00:00:00` : value).toLocaleDateString('pt-BR');

export default function Overview({ project, backlogs }: { project: Project; backlogs: Backlog[] }) {
    return (
        <AuthenticatedLayout header={<PageHeader back={{ href: route('projects.index'), label: 'Projetos' }} title={project.name} meta={<span className="font-mono">{project.code}</span>} actions={<Link href={route('projects.backlog.index', project.id)} className={btnPrimary}>Backlogs do projeto</Link>} />}>
            <Head title={`${project.code} — Projeto`} />
            <div className="mx-auto max-w-7xl space-y-8 px-4 py-8 sm:px-6 lg:px-8">
                <section className={`${panelClass} p-6 sm:p-7`} aria-label="Dados do projeto">
                    <dl className="grid gap-x-8 gap-y-5 sm:grid-cols-2 lg:grid-cols-4">
                        <Stat label="Status" value={<StatusBadge status={project.status} label={statuses[project.status] ?? project.status} />} />
                        <Stat label="Cliente" value={project.client || 'Não informado'} />
                        <Stat label="Início" value={project.start_date ? formatDate(project.start_date) : 'Não informado'} />
                        <Stat label="Prazo" value={project.deadline ? formatDate(project.deadline) : 'Sem prazo'} />
                    </dl>
                </section>

                <section>
                    <div className="mb-5 flex items-end justify-between gap-3">
                        <div>
                            <h2 className="font-display text-xl font-extrabold tracking-tight text-neutral-950">Backlogs</h2>
                            <p className="mt-1 text-sm text-neutral-500">Cada backlog agrupa itens e tem seu próprio Gantt.</p>
                        </div>
                        <Link href={route('projects.backlog.index', project.id)} className="rounded text-sm font-semibold text-brand-700 transition hover:text-brand-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500">Ver todos</Link>
                    </div>
                    {backlogs.length
                        ? <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-3">{backlogs.map((backlog) => <BacklogCard key={backlog.id} projectId={project.id} backlog={backlog} />)}</div>
                        : <EmptyState title="Este projeto ainda não tem backlogs" text="Crie um backlog para organizar os itens e as tarefas do cronograma." action={<Link href={route('projects.backlog.index', project.id)} className={btnPrimary}>Criar backlog</Link>} />}
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
