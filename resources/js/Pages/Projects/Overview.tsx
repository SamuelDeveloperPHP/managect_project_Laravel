import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { Fragment, useMemo, useState } from 'react';
import type { FormEvent } from 'react';

type Project = { id: number; name: string; code: string; status: string; client: string | null; start_date: string | null; deadline: string | null };
type ScheduleTask = { id: number; code: string | null; name: string; description: string | null; status: string; progress: number; end_at: string };
type LinkedTask = { id: number; code: string | null; name: string; status: string; progress: number };
type BacklogItem = { id: number; code: string; epic: string; title: string; description: string | null; priority: string; status: string; release: string | null; points: number | null; gantt_tasks: LinkedTask[] };
type GanttTaskOption = { id: number; code: string | null; name: string; backlog_item_id: number | null };
type Phase = { code: string; name: string; total: number; done: number; progress: number };
type Summary = { total: number; completed: number; progress: number; late: number; due_soon: number; status_counts: Record<string, number>; status_labels: Record<string, string>; phases: Phase[]; upcoming: ScheduleTask[]; starts_at: string | null; ends_at: string | null };

const date = (value: string | null) => value ? new Date(value.length === 10 ? `${value}T00:00:00` : value).toLocaleDateString('pt-BR') : 'Não definido';
const backlogStatusLabels: Record<string, string> = {
    pending: 'Pendente', in_progress: 'Em andamento', validation: 'Em validação', done: 'Concluído',
};
const backlogStatusTone: Record<string, string> = {
    pending: 'bg-slate-100 text-slate-600', in_progress: 'bg-blue-50 text-blue-700',
    validation: 'bg-amber-50 text-amber-700', done: 'bg-emerald-50 text-emerald-700',
};

function Metric({ label, value, detail, tone = 'text-slate-950' }: { label: string; value: string | number; detail: string; tone?: string }) {
    return <article className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p className="text-sm font-medium text-slate-500">{label}</p><p className={`mt-2 text-3xl font-semibold tracking-tight ${tone}`}>{value}</p><p className="mt-1 text-xs text-slate-400">{detail}</p></article>;
}

export default function Overview({ project, summary, backlogItems, ganttTasks, canManage }: { project: Project; summary: Summary; backlogItems: BacklogItem[]; ganttTasks: GanttTaskOption[]; canManage: boolean }) {
    const [search, setSearch] = useState('');
    const [status, setStatus] = useState('in_progress');
    const [linkingItemId, setLinkingItemId] = useState<number | null>(null);
    const { data, setData, put, processing, errors, reset } = useForm<{ task_ids: number[] }>({ task_ids: [] });
    const visibleBacklogItems = useMemo(() => backlogItems.filter((item) => {
        const matchesSearch = `${item.code} ${item.epic} ${item.title} ${item.description ?? ''}`.toLocaleLowerCase('pt-BR').includes(search.toLocaleLowerCase('pt-BR'));
        return matchesSearch && (!status || item.status === status);
    }), [backlogItems, search, status]);
    const openTaskLinks = (item: BacklogItem) => {
        setData('task_ids', item.gantt_tasks.map((task) => task.id));
        setLinkingItemId(item.id);
    };
    const saveTaskLinks = (event: FormEvent, itemId: number) => {
        event.preventDefault();
        put(route('projects.backlog.gantt-tasks.sync', [project.id, itemId]), {
            preserveScroll: true,
            onSuccess: () => { setLinkingItemId(null); reset(); },
        });
    };

    return <AuthenticatedLayout>
        <Head title={`${project.code} — Visão geral`} />
        <div className="mx-auto max-w-[1440px] space-y-6 px-4 py-7 sm:px-6 lg:px-8">
            <header className="flex flex-wrap items-end justify-between gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div className="min-w-0"><Link href={route('projects.index')} className="text-xs font-semibold text-indigo-600 hover:text-indigo-800">← Projetos</Link><p className="mt-3 text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">{project.code}{project.client ? ` · ${project.client}` : ''}</p><h1 className="mt-1 truncate text-2xl font-semibold tracking-tight text-slate-900">{project.name}</h1><p className="mt-2 text-sm text-slate-500">{date(summary.starts_at)} até {date(summary.ends_at)}</p></div>
                <Link href={route('projects.timeline.index', project.id)} className="rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700">Abrir Gantt</Link>
            </header>

            <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Indicadores do projeto">
                <Metric label="Progresso geral" value={`${summary.progress}%`} detail="Média das entregas finais" />
                <Metric label="Entregas concluídas" value={`${summary.completed}/${summary.total}`} detail="Tarefas finais do cronograma" />
                <Metric label="Prazos nesta semana" value={summary.due_soon} detail="Ainda não concluídos" tone="text-amber-600" />
                <Metric label="Em atraso" value={summary.late} detail={summary.late ? 'Exigem atenção' : 'Sem pendências vencidas'} tone={summary.late ? 'text-rose-600' : 'text-emerald-600'} />
            </section>

            <section className="grid gap-5 xl:grid-cols-2">
                <article className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                    <div><p className="text-xs font-semibold uppercase tracking-wide text-indigo-600">Andamento</p><h2 className="mt-1 font-semibold text-slate-900">Distribuição das tarefas</h2></div>
                    <div className="mt-5 space-y-4">{Object.entries(summary.status_labels).map(([key, label]) => {
                        const count = summary.status_counts[key] ?? 0;
                        const percent = summary.total ? Math.round((count * 100) / summary.total) : 0;
                        return <div key={key}><div className="flex justify-between gap-3 text-sm"><span className="text-slate-600">{label}</span><span className="font-medium text-slate-800">{count} <span className="text-xs font-normal text-slate-400">({percent}%)</span></span></div><div className="mt-2 h-2 overflow-hidden rounded-full bg-slate-100"><div className={`h-full rounded-full ${key === 'STATUS_DONE' ? 'bg-emerald-500' : key === 'STATUS_FAILED' ? 'bg-rose-500' : key === 'STATUS_SUSPENDED' ? 'bg-amber-400' : 'bg-indigo-500'}`} style={{ width: `${percent}%` }} /></div></div>;
                    })}</div>
                </article>

                <article className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="border-b border-slate-100 px-5 py-5 sm:px-6"><p className="text-xs font-semibold uppercase tracking-wide text-indigo-600">Próximos prazos</p><h2 className="mt-1 font-semibold text-slate-900">Entregas pendentes</h2></div>
                    <div className="divide-y divide-slate-100">{summary.upcoming.length ? summary.upcoming.map((task) => <div key={task.id} className="flex items-center justify-between gap-3 px-5 py-3.5 sm:px-6"><div className="min-w-0"><p className="truncate text-sm font-medium text-slate-800">{task.name}</p><p className="mt-1 text-xs text-slate-400">{task.code || 'Sem código'}</p></div><time className="shrink-0 text-xs font-medium text-slate-500">{date(task.end_at)}</time></div>) : <p className="px-6 py-10 text-center text-sm text-slate-500">Sem entregas pendentes com prazo futuro.</p>}</div>
                </article>
            </section>

            <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div className="flex flex-wrap items-end justify-between gap-3 border-b border-slate-100 px-5 py-5 sm:px-6"><div><p className="text-xs font-semibold uppercase tracking-wide text-indigo-600">Fases</p><h2 className="mt-1 font-semibold text-slate-900">Avanço por frente de trabalho</h2></div><Link href={route('projects.timeline.index', project.id)} className="text-xs font-semibold text-indigo-600 hover:text-indigo-800">Ver Gantt →</Link></div>
                {summary.phases.length ? <div className="overflow-x-auto"><table className="min-w-full text-sm"><thead><tr className="bg-slate-50 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-400"><th className="px-5 py-3 sm:px-6">Fase</th><th className="px-4 py-3">Concluídas</th><th className="w-2/5 px-5 py-3 sm:px-6">Progresso</th></tr></thead><tbody className="divide-y divide-slate-100">{summary.phases.map((phase, index) => <tr key={`${phase.code}-${index}`}><td className="px-5 py-4 sm:px-6"><p className="font-medium text-slate-800">{phase.name}</p><p className="mt-1 text-xs text-slate-400">{phase.code}</p></td><td className="px-4 py-4 text-slate-600">{phase.done}/{phase.total}</td><td className="px-5 py-4 sm:px-6"><div className="flex items-center gap-3"><span className="w-10 text-xs font-semibold text-slate-600">{phase.progress}%</span><div className="h-1.5 flex-1 overflow-hidden rounded-full bg-slate-100"><div className="h-full rounded-full bg-indigo-500" style={{ width: `${phase.progress}%` }} /></div></div></td></tr>)}</tbody></table></div> : <p className="px-6 py-10 text-center text-sm text-slate-500">Adicione tarefas ao Gantt para visualizar as fases.</p>}
            </section>

            <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div className="flex flex-wrap items-end justify-between gap-3 border-b border-slate-100 px-5 py-5 sm:px-6"><div><p className="text-xs font-semibold uppercase tracking-wide text-indigo-600">Backlog · {backlogItems.length} itens · {backlogItems.filter((item) => item.gantt_tasks.length > 0).length} com vínculo ao Gantt</p><h2 className="mt-1 font-semibold text-slate-900">Itens cadastrados</h2></div><div className="flex flex-wrap gap-2"><input type="search" value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Buscar item, épico ou código" className="w-full rounded-lg border-slate-200 text-sm sm:w-60" aria-label="Buscar item, épico ou código" /><select value={status} onChange={(event) => setStatus(event.target.value)} className="rounded-lg border-slate-200 text-sm" aria-label="Filtrar por status do backlog"><option value="">Todos os status</option>{Object.entries(backlogStatusLabels).map(([key, label]) => <option key={key} value={key}>{label}</option>)}</select><Link href={route('projects.backlog.index', project.id)} className="rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Abrir backlog</Link></div></div>
                <div className="overflow-x-auto">
                    <table className="min-w-full text-sm">
                        <thead>
                            <tr className="bg-slate-50 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-400">
                                <th className="px-5 py-3 sm:px-6">Código</th>
                                <th className="px-4 py-3">Épico / item</th>
                                <th className="px-4 py-3">Prioridade</th>
                                <th className="px-4 py-3">Release</th>
                                <th className="px-4 py-3">Pontos</th>
                                <th className="px-5 py-3 sm:px-6">Status</th>
                                <th className="px-5 py-3 sm:px-6">Tarefas do Gantt</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {visibleBacklogItems.map((item) => (
                                <Fragment key={item.id}>
                                    <tr>
                                        <td className="whitespace-nowrap px-5 py-2 text-xs text-slate-500 sm:px-6">{item.code}</td>
                                        <td className="min-w-64 px-4 py-2">
                                            <p className="text-xs font-medium text-indigo-600">{item.epic}</p>
                                            <p className="mt-0.5 font-medium text-slate-800">{item.title}</p>
                                            {item.description && <p className="mt-0.5 line-clamp-1 text-xs text-slate-400">{item.description}</p>}
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-2">
                                            <span className="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">{item.priority}</span>
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-2 text-slate-600">{item.release || '—'}</td>
                                        <td className="whitespace-nowrap px-4 py-2 text-slate-600">{item.points ?? '—'}</td>
                                        <td className="whitespace-nowrap px-5 py-2 sm:px-6">
                                            <span className={`rounded-full px-2.5 py-1 text-xs font-medium ${backlogStatusTone[item.status] ?? 'bg-slate-100 text-slate-600'}`}>
                                                {backlogStatusLabels[item.status] ?? item.status}
                                            </span>
                                        </td>
                                        <td className="min-w-56 px-5 py-2 sm:px-6">
                                            <div className="flex items-start justify-between gap-3">
                                                <div className="min-w-0">
                                                    {item.gantt_tasks.length ? <>
                                                        <p className="text-xs font-medium text-slate-700">
                                                            {item.gantt_tasks.length} tarefa(s) · {item.gantt_tasks.filter((task) => task.status === 'STATUS_DONE').length} concluída(s) · {Math.round(item.gantt_tasks.reduce((sum, task) => sum + task.progress, 0) / item.gantt_tasks.length)}% médio
                                                        </p>
                                                        <p className="mt-1 line-clamp-2 text-xs text-slate-500">{item.gantt_tasks.map((task) => task.name).join(', ')}</p>
                                                    </> : <span className="text-xs text-slate-400">Sem vínculo</span>}
                                                </div>
                                                {canManage && <button type="button" onClick={() => openTaskLinks(item)} className="shrink-0 text-xs font-semibold text-indigo-600 hover:text-indigo-800">Vincular</button>}
                                            </div>
                                        </td>
                                    </tr>
                                    {linkingItemId === item.id && <tr>
                                        <td colSpan={7} className="bg-indigo-50/50 px-5 py-4 sm:px-6">
                                            <form onSubmit={(event) => saveTaskLinks(event, item.id)} className="flex flex-wrap items-end gap-3">
                                                <label className="min-w-64 flex-1 text-xs font-semibold text-slate-700">
                                                    Tarefas finais do Gantt
                                                    <select
                                                        multiple
                                                        value={data.task_ids.map(String)}
                                                        onChange={(event) => setData('task_ids', Array.from(event.currentTarget.selectedOptions, (option) => Number(option.value)))}
                                                        className="mt-1 block min-h-24 w-full rounded-lg border-slate-300 bg-white text-sm"
                                                        aria-describedby={`task-link-help-${item.id}`}
                                                    >
                                                        {ganttTasks.map((task) => <option key={task.id} value={task.id} disabled={task.backlog_item_id !== null && task.backlog_item_id !== item.id}>
                                                            {task.code ? `${task.code} · ` : ''}{task.name}{task.backlog_item_id !== null && task.backlog_item_id !== item.id ? ' (já vinculada)' : ''}
                                                        </option>)}
                                                    </select>
                                                </label>
                                                <div className="flex gap-2">
                                                    <button type="button" onClick={() => setLinkingItemId(null)} className="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-600">Cancelar</button>
                                                    <button type="submit" disabled={processing || ganttTasks.length === 0} className="rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white disabled:opacity-50">Salvar vínculos</button>
                                                </div>
                                                <p id={`task-link-help-${item.id}`} className="w-full text-xs text-slate-500">Um item do backlog pode ser dividido em várias tarefas. Cada tarefa pode pertencer a um único item. Segure Ctrl para selecionar várias tarefas.</p>
                                                {errors.task_ids && <p className="w-full text-xs text-rose-600">{errors.task_ids}</p>}
                                            </form>
                                        </td>
                                    </tr>}
                                </Fragment>
                            ))}
                            {visibleBacklogItems.length === 0 && <tr>
                                <td colSpan={7} className="px-6 py-10 text-center text-sm text-slate-500">
                                    {backlogItems.length ? 'Nenhum item do backlog corresponde aos filtros.' : 'Nenhum item de backlog cadastrado para este projeto.'}
                                </td>
                            </tr>}
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>;
}
