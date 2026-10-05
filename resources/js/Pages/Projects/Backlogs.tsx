import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

type Project = { id: number; name: string; code: string; description: string | null; status: string };
type Backlog = { id: number; code: string; name: string; description: string | null; status: string; items_count: number; tasks_count: number };

export default function Backlogs({ project, backlogs, canManage }: { project: Project; backlogs: Backlog[]; canManage: boolean }) {
    const [showForm, setShowForm] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({ code: '', name: '', description: '' });
    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        post(route('projects.backlog.store', project.id), { onSuccess: () => { reset(); setShowForm(false); } });
    };

    return <AuthenticatedLayout header={<div className="flex flex-wrap items-center justify-between gap-3"><div><Link href={route('projects.overview', project.id)} className="text-sm font-medium text-indigo-600 hover:text-indigo-700">← Projeto</Link><h2 className="mt-1 text-2xl font-semibold text-slate-900">Backlogs · {project.name}</h2></div>{canManage && <button onClick={() => setShowForm(!showForm)} className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700">Novo backlog</button>}</div>}>
        <Head title={`${project.code} — Backlogs`} />
        <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <p className="mb-6 text-sm text-slate-500">O projeto reúne seus backlogs. Cada backlog contém itens e seu próprio cronograma Gantt.</p>
            {showForm && <form onSubmit={submit} className="mb-6 grid gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm md:grid-cols-3"><label className="text-sm font-medium text-slate-700">Código<input value={data.code} onChange={(e) => setData('code', e.target.value.toUpperCase())} placeholder="Gerado automaticamente" className="mt-1 block w-full rounded-lg border-slate-300 text-sm" /><span className="text-xs text-red-600">{errors.code}</span></label><label className="text-sm font-medium text-slate-700">Nome do backlog<input value={data.name} onChange={(e) => setData('name', e.target.value)} className="mt-1 block w-full rounded-lg border-slate-300 text-sm" required /><span className="text-xs text-red-600">{errors.name}</span></label><label className="text-sm font-medium text-slate-700">Descrição<input value={data.description} onChange={(e) => setData('description', e.target.value)} className="mt-1 block w-full rounded-lg border-slate-300 text-sm" /><span className="text-xs text-red-600">{errors.description}</span></label><div className="md:col-span-3"><button disabled={processing} className="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">Criar backlog</button></div></form>}
            {backlogs.length ? <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">{backlogs.map((backlog) => <article key={backlog.id} className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"><div className="flex items-start justify-between gap-3"><span className="rounded bg-indigo-50 px-2 py-1 text-xs font-semibold text-indigo-700">{backlog.code}</span><span className="text-xs font-medium text-emerald-700">{backlog.status === 'active' ? 'Ativo' : backlog.status}</span></div><Link href={route('projects.backlog.show', [project.id, backlog.id])} className="mt-4 block font-semibold text-slate-900 hover:text-indigo-700">{backlog.name}</Link><p className="mt-2 line-clamp-2 min-h-10 text-sm text-slate-500">{backlog.description || 'Sem descrição.'}</p><div className="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-3"><span className="text-xs text-slate-500">{backlog.items_count} itens · {backlog.tasks_count} tarefas</span><Link href={route('projects.backlog.show', [project.id, backlog.id])} className="text-sm font-semibold text-indigo-700 hover:text-indigo-900">Abrir →</Link></div></article>)}</div> : <div className="rounded-xl border border-dashed border-slate-300 bg-white p-12 text-center"><h3 className="font-semibold text-slate-900">Nenhum backlog cadastrado</h3><p className="mt-2 text-sm text-slate-500">Crie o primeiro backlog deste projeto para começar a organizar o trabalho.</p>{canManage && <button onClick={() => setShowForm(true)} className="mt-4 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Criar primeiro backlog</button>}</div>}
        </div>
    </AuthenticatedLayout>;
}
