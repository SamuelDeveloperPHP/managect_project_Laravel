import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

type Project = {
    id: number;
    name: string;
    code: string;
    description: string | null;
    status: string;
    backlog_items_count: number;
    tasks_count: number;
    client: string | null;
    priority: string;
    start_date: string | null;
    deadline: string | null;
};

export default function Index({ projects }: { projects: Project[] }) {
    const [showForm, setShowForm] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({ name: '', code: '', description: '' });
    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        post(route('projects.store'), { onSuccess: () => { reset(); setShowForm(false); } });
    };

    return (
        <AuthenticatedLayout header={<div><p className="text-sm font-medium text-indigo-600">ManageCT</p><h2 className="text-2xl font-semibold text-slate-900">Projetos e backlog</h2></div>}>
            <Head title="Projetos" />
            <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <div className="mb-7 flex flex-wrap items-center justify-between gap-4">
                    <div><h1 className="text-lg font-semibold text-slate-900">Sua carteira de projetos</h1><p className="mt-1 text-sm text-slate-500">Cada projeto e seu backlog ficam restritos à empresa da conta ativa.</p></div>
                    <button onClick={() => setShowForm(!showForm)} className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700">Novo projeto</button>
                </div>
                {showForm && <form onSubmit={submit} className="mb-6 grid gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm md:grid-cols-3">
                    <label className="text-sm font-medium text-slate-700">Nome<input value={data.name} onChange={(e) => setData('name', e.target.value)} className="mt-1 block w-full rounded-lg border-slate-300 text-sm" required /><span className="text-xs text-red-600">{errors.name}</span></label>
                    <label className="text-sm font-medium text-slate-700">Código<input value={data.code} onChange={(e) => setData('code', e.target.value.toUpperCase())} placeholder="BLING-PARIDADE" className="mt-1 block w-full rounded-lg border-slate-300 text-sm" required /><span className="text-xs text-red-600">{errors.code}</span></label>
                    <label className="text-sm font-medium text-slate-700">Descrição<input value={data.description} onChange={(e) => setData('description', e.target.value)} className="mt-1 block w-full rounded-lg border-slate-300 text-sm" /></label>
                    <div className="md:col-span-3"><button disabled={processing} className="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">Criar e abrir backlog</button></div>
                </form>}
                {projects.length === 0 ? <div className="rounded-xl border border-dashed border-slate-300 bg-white p-12 text-center"><h3 className="font-semibold text-slate-900">Comece pelo seu primeiro projeto</h3><p className="mt-2 text-sm text-slate-500">Crie o projeto que receberá os itens do backlog do Bling.</p></div> :
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">{projects.map((project) => <article key={project.id} className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-indigo-300 hover:shadow-md"><div className="flex items-start justify-between gap-3"><span className="rounded bg-indigo-50 px-2 py-1 text-xs font-semibold text-indigo-700">{project.code}</span><span className="text-xs font-medium text-emerald-700">{project.status === 'active' ? 'Ativo' : project.status}</span></div><Link href={route('projects.backlog.index', project.id)} className="mt-4 block font-semibold text-slate-900 hover:text-indigo-700">{project.name}</Link><p className="mt-2 line-clamp-2 text-sm text-slate-500">{project.description || 'Sem descrição.'}</p><p className="mt-4 text-xs text-slate-500">{project.client ? `Cliente: ${project.client} · ` : ''}{project.deadline ? `Prazo: ${new Date(`${project.deadline}T00:00:00`).toLocaleDateString('pt-BR')}` : 'Sem prazo definido'}</p><div className="mt-4 flex flex-wrap gap-4 border-t border-slate-100 pt-3 text-sm"><Link className="font-medium text-indigo-700 hover:text-indigo-900" href={route('projects.timeline.index', project.id)}>{project.tasks_count} tarefas · Cronograma</Link><Link className="font-medium text-slate-600 hover:text-indigo-700" href={route('projects.backlog.index', project.id)}>{project.backlog_items_count} itens no backlog</Link></div></article>)}</div>}
            </div>
        </AuthenticatedLayout>
    );
}
