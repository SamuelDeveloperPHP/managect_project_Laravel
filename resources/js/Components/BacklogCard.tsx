import { StatusBadge } from '@/Components/ui';
import { Link } from '@inertiajs/react';

export type BacklogSummary = { id: number; code: string; name: string; description: string | null; status: string; items_count: number; tasks_count: number };

export default function BacklogCard({ projectId, backlog }: { projectId: number; backlog: BacklogSummary }) {
    return (
        <article className="group flex flex-col rounded-3xl border border-neutral-200 bg-white p-6 shadow-sm transition hover:-translate-y-0.5 hover:border-brand-200 hover:shadow-[0_24px_48px_-28px_rgba(8,33,38,.35)]">
            <div className="flex items-start justify-between gap-3">
                <span className="rounded-lg bg-brand-50 px-2.5 py-1 font-mono text-xs font-medium text-brand-700">{backlog.code}</span>
                <StatusBadge status={backlog.status} label={backlog.status === 'active' ? 'Ativo' : backlog.status} />
            </div>
            <Link href={route('projects.backlog.show', [projectId, backlog.id])} className="mt-4 block font-display text-lg font-extrabold tracking-tight text-neutral-950 transition group-hover:text-brand-700 focus-visible:outline-none focus-visible:underline">{backlog.name}</Link>
            <p className="mt-2 line-clamp-2 min-h-10 text-sm leading-5 text-neutral-600">{backlog.description || 'Sem descrição.'}</p>
            <div className="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-neutral-100 pt-4 text-sm">
                <span className="text-neutral-500"><b className="font-semibold text-neutral-800">{backlog.items_count}</b> itens · <b className="font-semibold text-neutral-800">{backlog.tasks_count}</b> tarefas</span>
                <Link href={route('projects.backlog.show', [projectId, backlog.id])} className="rounded font-semibold text-brand-700 transition hover:text-brand-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500">Abrir →</Link>
            </div>
        </article>
    );
}
