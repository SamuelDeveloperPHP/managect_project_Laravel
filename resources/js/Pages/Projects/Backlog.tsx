import { btnPrimary, btnSecondary, fieldClass, PageHeader, panelClass } from '@/Components/ui';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useMemo, useState } from 'react';

type Item = { id: number; code: string; epic: string; title: string; description: string | null; priority: string; status: string; release: string | null; points: number | null };
type Project = { id: number; name: string; code: string; description: string | null; status: string };
type BacklogData = { id: number; code: string; name: string; description: string | null; status: string; items_count: number; tasks_count: number };
const statuses: Record<string, string> = { pending: 'Pendente', in_progress: 'Em andamento', validation: 'Em validação', done: 'Concluído' };
const priorityTone: Record<string, string> = { P0: 'bg-rose-50 text-rose-700 ring-rose-200', P1: 'bg-amber-50 text-amber-800 ring-amber-200', P2: 'bg-sky-50 text-sky-700 ring-sky-200', P3: 'bg-neutral-100 text-neutral-600 ring-neutral-200' };

export default function Backlog({ project, backlog, items, canManage = false }: { project: Project; backlog: BacklogData; items: Item[]; canManage?: boolean }) {
    const [showForm, setShowForm] = useState(false);
    const [filter, setFilter] = useState('all');
    const { data, setData, post, processing, errors, reset } = useForm({ code: '', epic: '', title: '', description: '', priority: 'P1', release: 'R1', points: '' });
    const visible = useMemo(() => filter === 'all' ? items : items.filter((item) => item.status === filter), [filter, items]);
    const done = items.filter((item) => item.status === 'done').length;
    const percent = items.length ? Math.round((done / items.length) * 100) : 0;
    const submit: FormEventHandler = (event) => { event.preventDefault(); post(route('projects.backlog.items.store', [project.id, backlog.id]), { onSuccess: () => { reset(); setShowForm(false); } }); };
    const changeStatus = (item: Item, status: string) => router.patch(route('projects.backlog.items.status', [project.id, backlog.id, item.id]), { status }, { preserveScroll: true });

    const actions = (
        <>
            {canManage && <button type="button" onClick={() => setShowForm(!showForm)} aria-expanded={showForm} className={btnSecondary}>{showForm ? 'Fechar' : 'Adicionar item'}</button>}
            <Link href={route('projects.timeline.index', [project.id, backlog.id])} className={btnPrimary}>Abrir Gantt</Link>
        </>
    );

    return (
        <AuthenticatedLayout header={<PageHeader back={{ href: route('projects.backlog.index', project.id), label: `Backlogs de ${project.name}` }} title={backlog.name} meta={<span className="font-mono">{project.code} · {backlog.code}</span>} actions={actions} />}>
            <Head title={`${backlog.code} — ${backlog.name}`} />
            <div className="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
                <section className="relative isolate overflow-hidden rounded-3xl bg-brand-950 px-6 py-7 text-white sm:px-9" aria-label="Resumo do backlog">
                    <div aria-hidden="true" className="absolute inset-0 -z-10 bg-[radial-gradient(60%_90%_at_95%_0%,rgba(42,102,112,.6),transparent_70%),radial-gradient(40%_70%_at_0%_100%,rgba(201,138,18,.14),transparent_70%)]" />
                    <p className="max-w-3xl text-[15px] leading-relaxed text-brand-200">{backlog.description || 'Itens e entregas organizados neste backlog.'}</p>
                    <div className="mt-6 flex flex-wrap items-end gap-x-10 gap-y-4">
                        <div><p className="font-display text-4xl font-extrabold tracking-tight">{items.length}</p><p className="text-sm text-brand-300">itens</p></div>
                        <div><p className="font-display text-4xl font-extrabold tracking-tight">{done}</p><p className="text-sm text-brand-300">concluídos</p></div>
                        <div><p className="font-display text-4xl font-extrabold tracking-tight">{backlog.tasks_count}</p><p className="text-sm text-brand-300">tarefas no Gantt</p></div>
                        <div className="min-w-[200px] flex-1 sm:max-w-xs sm:flex-none">
                            <div className="flex justify-between text-sm text-brand-300"><span>Progresso</span><span className="font-semibold text-white">{percent}%</span></div>
                            <div className="mt-2 h-2 overflow-hidden rounded-full bg-white/10" role="progressbar" aria-valuenow={percent} aria-valuemin={0} aria-valuemax={100} aria-label="Itens concluídos"><div className="h-full rounded-full bg-gradient-to-r from-emerald-400 to-signal-300 transition-all" style={{ width: `${percent}%` }} /></div>
                        </div>
                    </div>
                </section>

                {canManage && showForm && (
                    <form onSubmit={submit} className={`${panelClass} grid gap-5 p-6 md:grid-cols-3`}>
                        <label className="text-sm font-semibold text-neutral-700">Código
                            <input value={data.code} onChange={(e) => setData('code', e.target.value.toUpperCase())} placeholder="BL-01-01" className={fieldClass} required />
                            <span className="mt-1 block text-xs font-normal text-rose-600">{errors.code}</span>
                        </label>
                        <label className="text-sm font-semibold text-neutral-700">Épico
                            <input value={data.epic} onChange={(e) => setData('epic', e.target.value)} placeholder="01 Plataforma" className={fieldClass} required />
                            <span className="mt-1 block text-xs font-normal text-rose-600">{errors.epic}</span>
                        </label>
                        <label className="text-sm font-semibold text-neutral-700">Prioridade
                            <select value={data.priority} onChange={(e) => setData('priority', e.target.value)} className={fieldClass}><option>P0</option><option>P1</option><option>P2</option><option>P3</option></select>
                        </label>
                        <label className="text-sm font-semibold text-neutral-700 md:col-span-2">Funcionalidade
                            <input value={data.title} onChange={(e) => setData('title', e.target.value)} className={fieldClass} required />
                            <span className="mt-1 block text-xs font-normal text-rose-600">{errors.title}</span>
                        </label>
                        <label className="text-sm font-semibold text-neutral-700">Release
                            <input value={data.release} onChange={(e) => setData('release', e.target.value)} className={fieldClass} />
                        </label>
                        <label className="text-sm font-semibold text-neutral-700 md:col-span-3">Descrição
                            <textarea value={data.description} onChange={(e) => setData('description', e.target.value)} className={fieldClass} rows={2} />
                        </label>
                        <div className="flex gap-3 md:col-span-3">
                            <button disabled={processing} className={btnPrimary}>{processing ? 'Salvando…' : 'Salvar item'}</button>
                            <button type="button" onClick={() => setShowForm(false)} className={btnSecondary}>Cancelar</button>
                        </div>
                    </form>
                )}

                <section className={`${panelClass} overflow-hidden`}>
                    <div className="flex flex-wrap items-center justify-between gap-3 border-b border-neutral-100 px-6 py-5">
                        <div>
                            <h2 className="font-display text-lg font-extrabold tracking-tight text-neutral-950">Itens do backlog</h2>
                            <p className="mt-1 text-sm text-neutral-500">As tarefas detalhadas e dependências ficam no Gantt deste backlog.</p>
                        </div>
                        <select value={filter} onChange={(event) => setFilter(event.target.value)} className="min-h-11 rounded-xl border-neutral-300 pr-9 text-sm font-medium focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15" aria-label="Filtrar itens por status">
                            <option value="all">Todos os status</option>
                            {Object.entries(statuses).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                        </select>
                    </div>

                    <div className="divide-y divide-neutral-100">
                        {visible.map((item) => (
                            <article key={item.id} className="flex flex-wrap items-center justify-between gap-4 px-6 py-4 transition hover:bg-neutral-50">
                                <div className="flex min-w-0 flex-1 items-start gap-4">
                                    <span className={`mt-0.5 rounded-lg px-2 py-1 text-xs font-bold ring-1 ring-inset ${priorityTone[item.priority] ?? priorityTone.P3}`}>{item.priority}</span>
                                    <div className="min-w-0">
                                        <p className="font-mono text-xs text-neutral-500">{item.code} · {item.epic}</p>
                                        <h3 className="mt-0.5 font-semibold text-neutral-900">{item.title}</h3>
                                        {item.description && <p className="mt-1 text-sm text-neutral-500">{item.description}</p>}
                                    </div>
                                </div>
                                <div className="flex items-center gap-3">
                                    <span className="rounded-lg bg-neutral-100 px-2 py-1 text-xs font-medium text-neutral-600">{item.release || 'Sem release'}</span>
                                    <select value={item.status} disabled={!canManage} onChange={(event) => changeStatus(item, event.target.value)} className="min-h-10 rounded-xl border-neutral-300 pr-9 text-sm focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15" aria-label={`Status de ${item.title}`}>
                                        {Object.entries(statuses).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                                    </select>
                                </div>
                            </article>
                        ))}
                        {visible.length === 0 && <p className="px-6 py-14 text-center text-sm text-neutral-500">{items.length ? 'Nenhum item corresponde ao filtro.' : 'Este backlog ainda não tem itens cadastrados.'}</p>}
                    </div>
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
