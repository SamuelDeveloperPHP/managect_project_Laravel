import BacklogCard from '@/Components/BacklogCard';
import { btnPrimary, btnSecondary, EmptyState, fieldClass, PageHeader, panelClass } from '@/Components/ui';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
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
    const toggle = <button type="button" onClick={() => setShowForm(!showForm)} aria-expanded={showForm} className={btnPrimary}>{showForm ? 'Fechar' : 'Novo backlog'}</button>;

    return (
        <AuthenticatedLayout header={<PageHeader back={{ href: route('projects.overview', project.id), label: project.name }} title="Backlogs" meta={<span className="font-mono">{project.code}</span>} actions={canManage ? toggle : undefined} />}>
            <Head title={`${project.code} — Backlogs`} />
            <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <p className="mb-6 max-w-2xl text-sm text-neutral-600">O projeto reúne seus backlogs. Cada backlog contém itens e seu próprio cronograma Gantt.</p>

                {showForm && (
                    <form onSubmit={submit} className={`${panelClass} mb-8 grid gap-5 p-6 md:grid-cols-3`}>
                        <label className="text-sm font-semibold text-neutral-700">Código
                            <input value={data.code} onChange={(e) => setData('code', e.target.value.toUpperCase())} placeholder="Gerado automaticamente" className={fieldClass} />
                            <span className="mt-1 block text-xs font-normal text-rose-600">{errors.code}</span>
                        </label>
                        <label className="text-sm font-semibold text-neutral-700">Nome do backlog
                            <input value={data.name} onChange={(e) => setData('name', e.target.value)} className={fieldClass} required />
                            <span className="mt-1 block text-xs font-normal text-rose-600">{errors.name}</span>
                        </label>
                        <label className="text-sm font-semibold text-neutral-700">Descrição
                            <input value={data.description} onChange={(e) => setData('description', e.target.value)} className={fieldClass} />
                            <span className="mt-1 block text-xs font-normal text-rose-600">{errors.description}</span>
                        </label>
                        <div className="flex gap-3 md:col-span-3">
                            <button disabled={processing} className={btnPrimary}>{processing ? 'Criando…' : 'Criar backlog'}</button>
                            <button type="button" onClick={() => setShowForm(false)} className={btnSecondary}>Cancelar</button>
                        </div>
                    </form>
                )}

                {backlogs.length
                    ? <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-3">{backlogs.map((backlog) => <BacklogCard key={backlog.id} projectId={project.id} backlog={backlog} />)}</div>
                    : <EmptyState title="Nenhum backlog cadastrado" text="Crie o primeiro backlog deste projeto para começar a organizar o trabalho." action={canManage ? <button type="button" onClick={() => setShowForm(true)} className={btnPrimary}>Criar primeiro backlog</button> : undefined} />}
            </div>
        </AuthenticatedLayout>
    );
}
