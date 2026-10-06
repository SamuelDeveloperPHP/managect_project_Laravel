import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { btnPrimary, fieldClass, PageHeader, panelClass } from '@/Components/ui';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

type Event = {
    id: number;
    user_id: number | null;
    user: { name: string; email: string } | null;
    action: string;
    route_name: string | null;
    method: string;
    path: string;
    status_code: number | null;
    outcome: string;
    ip_address: string | null;
    user_agent: string | null;
    description: string | null;
    metadata: { query_keys?: string[]; actor_role?: string; changed_fields?: string[]; request_id?: string } | null;
    created_at: string;
};

type AuditPage = {
    data: Event[];
    links: { url: string | null; label: string; active: boolean }[];
    current_page: number;
    last_page: number;
    total: number;
};

function pageLabel(label: string): string {
    return label.replace(/&laquo;/g, '‹').replace(/&raquo;/g, '›').replace(/&amp;/g, '&').replace(/<[^>]*>/g, '').trim();
}

export default function Audit({ events, filters, users, companies, selectedCompanyId, auth }: { events: AuditPage; filters: Record<string, string | null>; users: { id: number; name: string }[]; companies: { id: number; name: string }[]; selectedCompanyId: number | null; auth: { user: { role?: string } } }) {
    const form = useForm({ company_id: selectedCompanyId?.toString() ?? '', user_id: filters.user_id ?? '', action: filters.action ?? '', from: filters.from ?? '', to: filters.to ?? '' });
    const submit: FormEventHandler = (event) => { event.preventDefault(); form.get(route('company.audit.index'), { preserveState: true, preserveScroll: true }); };

    return (
        <AuthenticatedLayout header={<PageHeader title="Auditoria" meta={`${events.total} ${events.total === 1 ? 'evento registrado' : 'eventos registrados'}`} />}>
            <Head title="Auditoria" />
            <div className="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
                <div className="flex gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm leading-6 text-emerald-900"><svg className="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z" /><path d="m9 12 2 2 4-4" /></svg><p>A trilha inclui páginas acessadas, consultas, alterações e tentativas negadas. Valores de formulários, senhas e termos de pesquisa não são gravados.</p></div>
                <section className={`${panelClass} p-6`}>
                    <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5 lg:items-end">
                        {auth.user.role === 'master' && <label className="text-sm font-semibold text-neutral-700">Empresa<select value={form.data.company_id} onChange={(e) => form.setData('company_id', e.target.value)} className={fieldClass}><option value="">Todas</option>{companies.map((company) => <option key={company.id} value={company.id}>{company.name}</option>)}</select></label>}
                        <label className="text-sm font-semibold text-neutral-700">Usuário<select value={form.data.user_id} onChange={(e) => form.setData('user_id', e.target.value)} className={fieldClass}><option value="">Todos</option>{users.map((user) => <option key={user.id} value={user.id}>{user.name}</option>)}</select></label>
                        <label className="text-sm font-semibold text-neutral-700">Ação exata<input value={form.data.action} onChange={(e) => form.setData('action', e.target.value)} className={fieldClass} placeholder="ex.: projects.index" /></label>
                        <label className="text-sm font-semibold text-neutral-700">De<input type="date" value={form.data.from} onChange={(e) => form.setData('from', e.target.value)} className={fieldClass} /></label>
                        <label className="text-sm font-semibold text-neutral-700">Até<input type="date" value={form.data.to} onChange={(e) => form.setData('to', e.target.value)} className={fieldClass} /></label>
                        <button disabled={form.processing} className={btnPrimary}>Filtrar</button>
                    </form>
                </section>
                <section className={`${panelClass} overflow-hidden`}>
                    <div className="border-b border-neutral-100 px-6 py-4 text-sm text-neutral-500">{events.total} evento(s) · página {events.current_page} de {events.last_page}</div>
                    <div className="divide-y divide-slate-100">{events.data.map((event) => <article key={event.id} className="grid gap-2 px-6 py-4 transition hover:bg-neutral-50 md:grid-cols-[190px_1fr_140px]">
                        <div><p className="text-sm font-medium text-slate-800">{new Date(event.created_at).toLocaleString('pt-BR')}</p><p className="text-xs text-slate-500">{event.user?.name ?? 'Visitante / sessão encerrada'}{event.metadata?.actor_role ? ` · ${event.metadata.actor_role}` : ''}</p>{event.metadata?.request_id && <p className="break-all text-[11px] text-slate-400">Req: {event.metadata.request_id}</p>}</div>
                        <div><p className="text-sm font-semibold text-slate-800">{event.action} · {event.description}</p><p className="break-all text-xs text-slate-500">{event.method} {event.path} · {event.route_name ?? 'rota sem nome'}</p>{(event.metadata?.changed_fields?.length ?? 0) > 0 && <p className="mt-1 text-xs text-slate-500">Campos alterados: {event.metadata?.changed_fields?.join(', ')}</p>}{(event.metadata?.query_keys?.length ?? 0) > 0 && <p className="mt-1 text-xs text-slate-400">Filtros usados: {event.metadata?.query_keys?.join(', ')}</p>}<p className="mt-1 text-xs text-slate-400">IP: {event.ip_address ?? 'indisponível'} · {event.user_agent ?? 'navegador não informado'}</p></div>
                        <div className="md:text-right"><span className={`inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset ${event.outcome === 'success' ? 'bg-emerald-50 text-emerald-700 ring-emerald-200' : 'bg-amber-50 text-amber-800 ring-amber-200'}`}>{event.status_code ?? '—'} · {event.outcome}</span></div>
                    </article>)}{events.data.length === 0 && <p className="p-8 text-center text-sm text-slate-500">Nenhum evento encontrado nesse período.</p>}</div>
                    {events.last_page > 1 && <nav className="flex flex-wrap gap-2 border-t border-slate-100 p-4" aria-label="Paginação">{events.links.map((link, i) => link.url ? <Link key={i} href={link.url} preserveScroll className={`rounded-lg border px-3 py-1.5 text-sm font-medium ${link.active ? 'border-brand-700 bg-brand-700 text-white' : 'border-neutral-200 text-neutral-600 hover:bg-neutral-50'}`}>{pageLabel(link.label)}</Link> : <span key={i} className="rounded-md border border-slate-100 px-3 py-1.5 text-sm text-slate-300">{pageLabel(link.label)}</span>)}</nav>}
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
