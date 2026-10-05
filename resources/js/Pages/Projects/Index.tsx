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
                
                <h2 className="text-2xl font-semibold text-slate-900">Projetos</h2>
            </div>
        }>
            <Head title="Projetos" />
            <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <div className="mb-5 flex justify-end">
                    {canManageProjects && <Link href={route('projects.create')} className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700">Novo projeto</Link>}
                </div>
                {projects.length === 0 ? <div className="rounded-xl border border-dashed border-slate-300 bg-white p-12 text-center"><h3 className="font-semibold text-slate-900">Comece pelo seu primeiro projeto</h3><p className="mt-2 text-sm text-slate-500">Depois do cadastro, você poderá organizar os backlogs e as tarefas do projeto.</p></div> :
                    <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white">
                        <table className="min-w-full text-sm">
                            <thead className="border-b border-slate-200 bg-slate-50 text-left text-xs font-semibold text-slate-600">
                                <tr><th className="px-4 py-3">Projeto</th><th className="px-4 py-3">Cliente</th><th className="px-4 py-3">Status</th><th className="px-4 py-3">Prazo</th><th className="px-4 py-3 text-right">Backlogs</th><th className="px-4 py-3 text-right">Tarefas</th><th className="px-4 py-3"><span className="sr-only">Ações</span></th></tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {projects.map((project) => <tr key={project.id} className="hover:bg-slate-50">
                                    <td className="px-4 py-3">
                                        <div className="flex items-center gap-3">
                                            {project.image_url && <img src={project.image_url} alt="" className="h-9 w-9 rounded object-cover" />}
                                            <div className="min-w-0"><Link href={route('projects.overview', project.id)} className="font-semibold text-slate-900 hover:text-indigo-700">{project.name}</Link><p className="text-xs text-slate-500">{project.code}</p></div>
                                        </div>
                                    </td>
                                    <td className="px-4 py-3 text-slate-600">{project.client || 'Não informado'}</td>
                                    <td className="px-4 py-3 text-slate-700">{statuses[project.status] ?? project.status}</td>
                                    <td className="px-4 py-3 text-slate-600">{project.deadline ? formatDate(project.deadline) : 'Sem prazo'}</td>
                                    <td className="px-4 py-3 text-right"><Link className="font-medium text-indigo-700 hover:underline" href={route('projects.backlog.index', project.id)}>{project.backlogs_count}</Link></td>
                                    <td className="px-4 py-3 text-right text-slate-700">{project.tasks_count}</td>
                                    <td className="whitespace-nowrap px-4 py-3 text-right">
                                        <Link className="font-medium text-indigo-700 hover:underline" href={route('projects.overview', project.id)}>Visão geral</Link>
                                        {canManageProjects && <Link className="ml-4 font-medium text-slate-600 hover:underline" href={route('projects.edit', project.id)}>Editar</Link>}
                                    </td>
                                </tr>)}
                            </tbody>
                        </table>
                    </div>}
            </div>
        </AuthenticatedLayout>
    );
}
