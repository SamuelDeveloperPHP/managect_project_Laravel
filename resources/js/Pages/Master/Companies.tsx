import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

type CompanyRow = {
    id: number; name: string; document_type: string | null; document_masked: string | null; domain: string | null;
    is_active: boolean; total_users: number; active_users: number; inactive_users: number; online_users: number | null;
    offline_users: number | null; total_projects: number; active_projects: number; events_30d: number;
    last_activity_at: string | null; selected: boolean;
};

export default function Companies({ companies, filters, selectedCompanyId, sessionTrackingAvailable }: {
    companies: CompanyRow[]; filters: { q: string }; selectedCompanyId: number | null; sessionTrackingAvailable: boolean;
}) {
    const [search, setSearch] = useState(filters.q ?? '');
    const findCompanies = (event: FormEvent) => {
        event.preventDefault();
        router.get(route('master.companies.index'), { q: search }, { preserveState: true, preserveScroll: true });
    };
    const openCompany = (companyId: number | null) => router.post(route('master.companies.select'), { company_id: companyId });

    return <AuthenticatedLayout header={<div><p className="text-sm font-medium text-indigo-600">ManageCT · Administração Master</p><h2 className="text-2xl font-semibold text-slate-900">Empresas</h2></div>}>
        <Head title="Empresas · Master" />
        <div className="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
            <div className="flex flex-wrap items-end justify-between gap-4"><div><h1 className="text-lg font-semibold text-slate-900">Empresas cadastradas</h1><p className="mt-1 text-sm text-slate-500">Abra uma empresa para navegar pelos projetos, equipe, configuração e auditoria no escopo dela.</p></div><button onClick={() => openCompany(null)} className={`rounded-lg border px-4 py-2 text-sm font-semibold ${selectedCompanyId === null ? 'border-indigo-600 bg-indigo-50 text-indigo-700' : 'border-slate-300 text-slate-700 hover:bg-slate-50'}`}>{selectedCompanyId === null ? 'Visão global ativa' : 'Voltar à visão global'}</button></div>

            <form onSubmit={findCompanies} className="flex flex-wrap gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm"><input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Buscar por empresa, domínio ou documento" className="min-w-64 flex-1 rounded-lg border-slate-300 text-sm" /><button className="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Buscar</button></form>

            <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"><div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4"><div><h3 className="font-semibold text-slate-900">Lista de empresas</h3><p className="mt-1 text-sm text-slate-500">{companies.length} empresa(s) · documentos pessoais/empresariais aparecem mascarados.</p></div>{!sessionTrackingAvailable && <span className="rounded-full bg-amber-50 px-3 py-1.5 text-xs font-medium text-amber-800">Presença online indisponível com o armazenamento de sessão atual</span>}</div>
                <div className="overflow-x-auto"><table className="min-w-full divide-y divide-slate-100 text-sm"><thead className="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500"><tr><th className="px-5 py-3">Empresa</th><th className="px-4 py-3">Usuários</th><th className="px-4 py-3">Presença</th><th className="px-4 py-3">Projetos</th><th className="px-4 py-3">Atividade · 30 dias</th><th className="px-5 py-3 text-right">Ação</th></tr></thead><tbody className="divide-y divide-slate-100">{companies.map((company) => <tr key={company.id} className={company.selected ? 'bg-indigo-50/50' : 'hover:bg-slate-50'}><td className="px-5 py-4"><div className="flex items-start gap-3"><span className={`mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full ${company.is_active ? 'bg-emerald-500' : 'bg-slate-400'}`} /><div><p className="font-semibold text-slate-900">{company.name}</p><p className="mt-1 text-xs text-slate-500">{company.document_type ? `${company.document_type} · ` : ''}{company.document_masked ?? 'Documento pendente'}{company.domain ? ` · ${company.domain}` : ''}</p><span className={`mt-2 inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold ${company.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600'}`}>{company.is_active ? 'Ativa' : 'Inativa'}</span>{company.selected && <span className="ml-2 mt-2 inline-flex rounded-full bg-indigo-100 px-2 py-0.5 text-[11px] font-semibold text-indigo-800">Empresa selecionada</span>}</div></div></td><td className="px-4 py-4"><p className="font-medium text-slate-800">{company.total_users} no total</p><p className="mt-1 text-xs text-slate-500">{company.active_users} ativos · {company.inactive_users} inativos</p></td><td className="px-4 py-4"><p className="font-medium text-emerald-700">{company.online_users ?? '—'} online</p><p className="mt-1 text-xs text-slate-500">{company.offline_users ?? '—'} offline</p></td><td className="px-4 py-4"><p className="font-medium text-slate-800">{company.total_projects} no total</p><p className="mt-1 text-xs text-slate-500">{company.active_projects} ativos</p></td><td className="px-4 py-4"><p className="font-medium text-slate-800">{company.events_30d} eventos</p><p className="mt-1 text-xs text-slate-500">{company.last_activity_at ? `Último: ${new Date(company.last_activity_at).toLocaleString('pt-BR')}` : 'Sem atividade registrada'}</p></td><td className="px-5 py-4 text-right"><button onClick={() => openCompany(company.id)} className="whitespace-nowrap rounded-lg bg-indigo-600 px-3.5 py-2 text-xs font-semibold text-white hover:bg-indigo-700">{company.selected ? 'Abrir painel' : 'Acessar dados'}</button></td></tr>)}{companies.length === 0 && <tr><td colSpan={6} className="px-5 py-12 text-center text-sm text-slate-500">Nenhuma empresa encontrada com esse filtro.</td></tr>}</tbody></table></div>
            </section>
            <p className="text-xs text-slate-400">Ao acessar uma empresa, o escopo permanece ativo durante a navegação. Use “Voltar à visão global” ou a lista Empresas para trocar de empresa.</p>
        </div>
    </AuthenticatedLayout>;
}
