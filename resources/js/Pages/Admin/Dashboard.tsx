import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { ReactNode } from 'react';

type Project = { id: number; name: string; code: string; status: string; deadline: string | null; tasks_count: number; company?: { id: number; name: string } };
type Task = { id: number; project_id: number | null; project: { id: number; name: string; code: string } | null; name: string; status: string; progress: number; start_at: string; end_at: string };
type Stats = { projects: number; active_projects: number; tasks: number; completed_tasks: number; overdue_tasks: number; team_members: number };
type CompanyMetric = { id: number; name: string; is_active: boolean; total_users: number; active_users: number; inactive_users: number; online_users: number | null; offline_users: number | null };
type AccessEvent = { id: number; user_id: number | null; company_id: number | null; user: { id: number; name: string } | null; company: { id: number; name: string } | null; action: string; route_name: string | null; method: string; path: string; status_code: number | null; outcome: string; created_at: string };
type UsageDay = { date: string; events: number; users: number };

const date = (value: string) => new Date(value).toLocaleDateString('pt-BR');
const dateTime = (value: string) => new Date(value).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' });

function Icon({ name, className = 'h-5 w-5' }: { name: 'projects' | 'tasks' | 'warning' | 'users' | 'activity' | 'arrow'; className?: string }) {
    const shared = { className, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 1.8, strokeLinecap: 'round' as const, strokeLinejoin: 'round' as const, 'aria-hidden': true as const };
    const paths: Record<typeof name, ReactNode> = {
        projects: <><rect x="3.5" y="5" width="17" height="15" rx="2" /><path d="M8 5V3.5h8V5M3.5 10h17M9 14h2m2 0h2" /></>,
        tasks: <><path d="m5 12 4 4L19 6" /><path d="M20 12v6a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h9" /></>,
        warning: <><path d="m10.3 4.2-7.6 13A2 2 0 0 0 4.4 20h15.2a2 2 0 0 0 1.7-2.9l-7.6-13a2 2 0 0 0-3.4.1Z" /><path d="M12 9v4m0 3h.01" /></>,
        users: <><path d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" /><circle cx="10" cy="7" r="4" /><path d="M20 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8" /></>,
        activity: <><path d="M3 12h4l3-8 4 16 3-8h4" /></>,
        arrow: <><path d="M5 12h14m-6-6 6 6-6 6" /></>,
    };
    return <svg {...shared}>{paths[name]}</svg>;
}

function UsageChart({ rows }: { rows: UsageDay[] }) {
    if (!rows.length) return <div className="flex h-48 items-center justify-center rounded-xl bg-slate-50 text-sm text-slate-500">Ainda não há atividade nesse período.</div>;

    const width = 640;
    const height = 190;
    const insetX = 16;
    const insetY = 18;
    const maxValue = Math.max(...rows.map((row) => row.events), 1);
    const points = rows.map((row, index) => ({
        x: rows.length === 1 ? width / 2 : insetX + index * (width - insetX * 2) / (rows.length - 1),
        y: height - insetY - (row.events / maxValue) * (height - insetY * 2),
    }));
    const line = points.map((point) => `${point.x},${point.y}`).join(' ');
    const area = `${points[0].x},${height - insetY} ${line} ${points[points.length - 1].x},${height - insetY}`;

    return <div>
        <div className="rounded-xl bg-slate-50 px-3 pt-4">
            <svg viewBox={`0 0 ${width} ${height}`} className="h-48 w-full overflow-visible" role="img" aria-label="Gráfico de eventos registrados por dia">
                {[0, 1, 2, 3].map((lineIndex) => {
                    const y = insetY + lineIndex * (height - insetY * 2) / 3;
                    return <line key={lineIndex} x1="0" x2={width} y1={y} y2={y} stroke="#e2e8f0" strokeDasharray="4 5" />;
                })}
                <polygon points={area} fill="url(#usageFill)" />
                <defs><linearGradient id="usageFill" x1="0" x2="0" y1="0" y2="1"><stop offset="0%" stopColor="#6366f1" stopOpacity=".24" /><stop offset="100%" stopColor="#6366f1" stopOpacity="0" /></linearGradient></defs>
                <polyline points={line} fill="none" stroke="#4f46e5" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round" />
                {points.map((point, index) => <circle key={rows[index].date} cx={point.x} cy={point.y} r="4" fill="white" stroke="#4f46e5" strokeWidth="2"><title>{`${date(rows[index].date)}: ${rows[index].events} eventos · ${rows[index].users} usuários`}</title></circle>)}
            </svg>
        </div>
        <div className="mt-3 flex items-center justify-between text-xs text-slate-400"><span>{date(rows[0].date)}</span><span>{rows.length} dias com atividade</span><span>{date(rows[rows.length - 1].date)}</span></div>
    </div>;
}

function MetricCard({ icon, title, value, note, color }: { icon: 'projects' | 'tasks' | 'warning' | 'users'; title: string; value: number; note: string; color: string }) {
    return <article className="group rounded-2xl border border-slate-200/80 bg-white p-5 shadow-[0_2px_10px_rgba(15,23,42,.035)] transition duration-200 hover:-translate-y-0.5 hover:shadow-lg">
        <div className="flex items-start justify-between gap-3"><div className={`flex h-11 w-11 items-center justify-center rounded-xl ${color}`}><Icon name={icon} /></div><span className="rounded-full bg-slate-50 px-2.5 py-1 text-[11px] font-semibold text-slate-500">{note}</span></div>
        <p className="mt-5 text-sm font-medium text-slate-500">{title}</p><p className="mt-1 text-3xl font-semibold tracking-tight text-slate-950">{value.toLocaleString('pt-BR')}</p>
    </article>;
}

export default function Dashboard({ stats, selectedCompanyId, companies, companyMetrics, accessMetrics, dailyUsage, recentAccess, recentProjects, upcomingTasks, canManageCompany, period }: {
    stats: Stats;
    selectedCompanyId: number | null;
    companies: { id: number; name: string }[];
    companyMetrics: CompanyMetric[];
    accessMetrics: { events: number; users: number; period_days: number; online_tracking: boolean };
    dailyUsage: UsageDay[];
    recentAccess: AccessEvent[];
    recentProjects: Project[];
    upcomingTasks: Task[];
    canManageCompany: boolean;
    period: number;
}) {
    const setFilter = (key: 'company_id' | 'period', value: string) => {
        if (key === 'company_id' && companies.length > 0) {
            router.post(route('master.companies.select'), { company_id: value ? Number(value) : null });
            return;
        }
        const data: Record<string, string | number> = { period };
        if (selectedCompanyId !== null) data.company_id = selectedCompanyId;
        if (key === 'period') data.period = value;
        router.get(route('dashboard'), data, { preserveState: false, preserveScroll: true });
    };
    const allCompanies = companies.length > 0 && selectedCompanyId === null;

    return <AuthenticatedLayout>
        <Head title="Painel administrativo" />
        <div className="min-h-[calc(100vh-4rem)] bg-[#f5f6fa]">
            <div className="mx-auto max-w-[1440px] space-y-7 px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
                <section className="relative isolate overflow-hidden rounded-[1.75rem] bg-gradient-to-br from-[#19172f] via-[#29245a] to-[#4038a8] px-6 py-7 text-white shadow-xl shadow-indigo-950/10 sm:px-9 sm:py-8">
                    <div className="absolute -right-16 -top-28 -z-10 h-80 w-80 rounded-full bg-indigo-400/20 blur-3xl" />
                    <div className="absolute bottom-[-8rem] right-[23%] -z-10 h-64 w-64 rounded-full bg-fuchsia-400/10 blur-3xl" />
                    <div className="flex flex-col justify-between gap-7 xl:flex-row xl:items-end">
                        <div className="max-w-2xl"><div className="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-3 py-1.5 text-xs font-semibold tracking-wide text-indigo-100"><span className="h-1.5 w-1.5 rounded-full bg-emerald-400" />CENTRAL DE GESTÃO</div><h1 className="mt-4 text-3xl font-semibold tracking-tight sm:text-4xl">Painel administrativo</h1><p className="mt-2 max-w-xl text-sm leading-6 text-indigo-100/80 sm:text-base">Acompanhe a operação, a presença das equipes e o uso do ManageCT em um só lugar.</p></div>
                        <div className="flex flex-wrap items-end gap-3">{companies.length > 0 && <label className="text-xs font-semibold uppercase tracking-wide text-indigo-100">Escopo da empresa<select value={selectedCompanyId ?? ''} onChange={(event) => setFilter('company_id', event.target.value)} className="mt-2 block min-w-60 rounded-xl border border-white/20 bg-white/10 px-3 py-2.5 text-sm font-medium normal-case tracking-normal text-white shadow-sm outline-none ring-0 focus:border-white/50 focus:ring-2 focus:ring-white/20 [&>option]:text-slate-900"><option value="">Todas as empresas</option>{companies.map((company) => <option key={company.id} value={company.id}>{company.name}</option>)}</select></label>}<label className="text-xs font-semibold uppercase tracking-wide text-indigo-100">Período<select value={period} onChange={(event) => setFilter('period', event.target.value)} className="mt-2 block rounded-xl border border-white/20 bg-white/10 px-3 py-2.5 text-sm font-medium normal-case tracking-normal text-white shadow-sm outline-none focus:border-white/50 focus:ring-2 focus:ring-white/20 [&>option]:text-slate-900"><option value={7}>7 dias</option><option value={30}>30 dias</option><option value={90}>90 dias</option></select></label></div>
                    </div>
                    <div className="mt-7 flex flex-wrap items-center gap-x-6 gap-y-2 border-t border-white/10 pt-5 text-sm text-indigo-100/80"><span className="inline-flex items-center gap-2"><span className="h-2 w-2 rounded-full bg-emerald-400" />{allCompanies ? 'Visão consolidada de todas as empresas' : selectedCompanyId ? 'Visualizando empresa selecionada' : 'Visão operacional da empresa'}</span>{companies.length > 0 && <Link href={route('master.companies.index')} className="inline-flex items-center gap-1.5 font-semibold text-white transition hover:text-indigo-200">Acessar empresas <Icon name="arrow" className="h-4 w-4" /></Link>}</div>
                </section>

                <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Indicadores principais">
                    <MetricCard icon="projects" title="Projetos" value={stats.projects} note={`${stats.active_projects} ativos`} color="bg-indigo-50 text-indigo-600" />
                    <MetricCard icon="tasks" title="Tarefas" value={stats.tasks} note={`${stats.completed_tasks} concluídas`} color="bg-sky-50 text-sky-600" />
                    <MetricCard icon="warning" title="Tarefas atrasadas" value={stats.overdue_tasks} note="Precisam de atenção" color="bg-amber-50 text-amber-600" />
                    <MetricCard icon="users" title="Usuários ativos" value={stats.team_members} note={allCompanies ? 'Em todas as empresas' : 'Nesta empresa'} color="bg-emerald-50 text-emerald-600" />
                </section>

                <section className="grid gap-6 xl:grid-cols-[minmax(0,1.45fr)_minmax(320px,.85fr)]">
                    <article className="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-[0_2px_10px_rgba(15,23,42,.035)] sm:p-6">
                        <div className="flex flex-wrap items-start justify-between gap-4"><div><div className="flex items-center gap-2"><span className="flex h-9 w-9 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600"><Icon name="activity" /></span><h2 className="font-semibold text-slate-900">Uso do sistema</h2></div><p className="mt-2 text-sm text-slate-500">Atividade registrada nos últimos {period} dias</p></div><div className="flex gap-6"><div><p className="text-2xl font-semibold tracking-tight text-slate-900">{accessMetrics.events.toLocaleString('pt-BR')}</p><p className="text-xs text-slate-500">eventos</p></div><div><p className="text-2xl font-semibold tracking-tight text-slate-900">{accessMetrics.users.toLocaleString('pt-BR')}</p><p className="text-xs text-slate-500">usuários</p></div></div></div>
                        <div className="mt-5"><UsageChart rows={dailyUsage} /></div><p className="mt-4 text-xs text-slate-400">Eventos de auditoria por dia; não representam monitoramento contínuo da tela do usuário.</p>
                    </article>

                    <article className="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-[0_2px_10px_rgba(15,23,42,.035)]">
                        <div className="flex items-center justify-between border-b border-slate-100 px-5 py-5"><div><h2 className="font-semibold text-slate-900">Atividade recente</h2><p className="mt-1 text-xs text-slate-500">Últimos eventos registrados</p></div><Link href={route('company.audit.index', selectedCompanyId ? { company_id: selectedCompanyId } : {})} className="text-xs font-semibold text-indigo-600 hover:text-indigo-800">Ver auditoria</Link></div>
                        <div className="divide-y divide-slate-100">{recentAccess.slice(0, 6).map((event) => <div key={event.id} className="flex gap-3 px-5 py-4"><span className={`mt-1 flex h-8 w-8 shrink-0 items-center justify-center rounded-full ${event.outcome === 'success' ? 'bg-emerald-50 text-emerald-600' : 'bg-amber-50 text-amber-600'}`}><span className="h-2 w-2 rounded-full bg-current" /></span><div className="min-w-0 flex-1"><div className="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1"><p className="truncate text-sm font-semibold text-slate-800">{event.user?.name ?? 'Usuário removido'}</p><time className="text-[11px] text-slate-400">{dateTime(event.created_at)}</time></div><p className="mt-1 truncate text-xs text-slate-500">{event.action} · {event.company?.name ?? 'Empresa indisponível'}</p></div></div>)}{recentAccess.length === 0 && <p className="px-5 py-12 text-center text-sm text-slate-500">Nenhuma atividade registrada.</p>}</div>
                    </article>
                </section>

                {companies.length > 0 && <section className="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-[0_2px_10px_rgba(15,23,42,.035)]">
                    <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-5 sm:px-6"><div><div className="flex items-center gap-2"><span className="h-2 w-2 rounded-full bg-indigo-500" /><h2 className="font-semibold text-slate-900">Empresas e presença</h2></div><p className="mt-1 text-sm text-slate-500">Usuários com sessão autenticada válida, contados uma vez por empresa.</p></div><Link href={route('master.companies.index')} className="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600 transition hover:border-indigo-200 hover:bg-indigo-50 hover:text-indigo-700">Ver lista completa <Icon name="arrow" className="h-3.5 w-3.5" /></Link></div>
                    <div className="overflow-x-auto"><table className="min-w-full text-sm"><thead><tr className="bg-slate-50/80 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-400"><th className="px-5 py-3 sm:px-6">Empresa</th><th className="px-4 py-3">Status</th><th className="px-4 py-3 text-right">Usuários</th><th className="px-4 py-3 text-right">Ativos</th><th className="px-4 py-3 text-right">Inativos</th><th className="px-4 py-3 text-right">Online</th><th className="px-5 py-3 text-right sm:px-6">Offline</th></tr></thead><tbody className="divide-y divide-slate-100">{companyMetrics.map((company) => <tr key={company.id} className="transition hover:bg-indigo-50/40"><td className="px-5 py-3.5 sm:px-6"><button className="text-left font-semibold text-slate-800 hover:text-indigo-700" onClick={() => setFilter('company_id', String(company.id))}>{company.name}</button></td><td className="px-4 py-3.5"><span className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold ${company.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'}`}><span className={`h-1.5 w-1.5 rounded-full ${company.is_active ? 'bg-emerald-500' : 'bg-slate-400'}`} />{company.is_active ? 'Ativa' : 'Inativa'}</span></td><td className="px-4 py-3.5 text-right font-medium text-slate-700">{company.total_users}</td><td className="px-4 py-3.5 text-right text-slate-600">{company.active_users}</td><td className="px-4 py-3.5 text-right text-slate-500">{company.inactive_users}</td><td className="px-4 py-3.5 text-right"><span className="inline-flex items-center justify-end gap-1.5 font-semibold text-emerald-700"><span className="h-1.5 w-1.5 rounded-full bg-emerald-500" />{company.online_users ?? '—'}</span></td><td className="px-5 py-3.5 text-right text-slate-600 sm:px-6">{company.offline_users ?? '—'}</td></tr>)}</tbody></table></div>
                </section>}

                <section className="grid gap-6 xl:grid-cols-2">
                    <article className="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-[0_2px_10px_rgba(15,23,42,.035)]"><div className="flex items-center justify-between border-b border-slate-100 px-5 py-5 sm:px-6"><div><h2 className="font-semibold text-slate-900">Projetos recentes</h2><p className="mt-1 text-sm text-slate-500">{allCompanies ? 'Visão consolidada da carteira' : 'Projetos da empresa selecionada'}</p></div><Link href={route('projects.index')} className="text-xs font-semibold text-indigo-600 hover:text-indigo-800">Todos os projetos</Link></div><div className="divide-y divide-slate-100">{recentProjects.map((project) => <Link key={project.id} href={route('projects.backlog.index', project.id)} className="group flex items-center justify-between gap-4 px-5 py-4 transition hover:bg-slate-50 sm:px-6"><div className="flex min-w-0 items-center gap-3"><span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-xs font-bold text-indigo-700">{project.code.slice(0, 2).toUpperCase()}</span><div className="min-w-0"><p className="truncate text-sm font-semibold text-slate-800 group-hover:text-indigo-700">{project.name}</p><p className="mt-1 truncate text-xs text-slate-500">{project.code} · {project.tasks_count} tarefas{project.company ? ` · ${project.company.name}` : ''}</p></div></div><div className="flex shrink-0 items-center gap-3"><span className="hidden text-xs text-slate-400 sm:inline">{project.deadline ? `Prazo ${date(project.deadline)}` : ''}</span><span className="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-600">{project.status}</span><Icon name="arrow" className="h-4 w-4 text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-indigo-500" /></div></Link>)}{recentProjects.length === 0 && <p className="px-6 py-12 text-center text-sm text-slate-500">Ainda não há projetos cadastrados.</p>}</div></article>

                    <article className="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-[0_2px_10px_rgba(15,23,42,.035)]"><div className="border-b border-slate-100 px-5 py-5 sm:px-6"><h2 className="font-semibold text-slate-900">Próximas tarefas</h2><p className="mt-1 text-sm text-slate-500">Acompanhe os itens abertos com prazo mais próximo.</p></div><div className="divide-y divide-slate-100">{upcomingTasks.map((task) => <div key={task.id} className="px-5 py-4 sm:px-6"><div className="flex items-start justify-between gap-4"><div className="min-w-0"><p className="truncate text-sm font-semibold text-slate-800">{task.name}</p><p className="mt-1 truncate text-xs text-slate-500">{task.project?.code ?? 'Sem projeto'}{task.project ? ` · ${task.project.name}` : ''} · vence {date(task.end_at)}</p></div><span className="shrink-0 rounded-lg bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-600">{task.progress}%</span></div><div className="mt-3 h-1.5 overflow-hidden rounded-full bg-slate-100"><div className="h-full rounded-full bg-indigo-500 transition-all" style={{ width: `${Math.min(100, task.progress)}%` }} /></div>{task.project_id && <Link href={route('projects.timeline.index', task.project_id)} className="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-indigo-600 hover:text-indigo-800">Abrir cronograma <Icon name="arrow" className="h-3 w-3" /></Link>}</div>)}{upcomingTasks.length === 0 && <p className="px-6 py-12 text-center text-sm text-slate-500">Nenhuma tarefa pendente.</p>}</div></article>
                </section>

                {canManageCompany && <section className="flex flex-col justify-between gap-4 rounded-2xl border border-indigo-100 bg-gradient-to-r from-white to-indigo-50/70 p-5 shadow-sm sm:flex-row sm:items-center sm:p-6"><div><p className="text-xs font-semibold uppercase tracking-wider text-indigo-600">Administração</p><h2 className="mt-1 font-semibold text-slate-900">Atalhos de gestão</h2><p className="mt-1 text-sm text-slate-500">Acesse rapidamente a configuração e os controles da empresa.</p></div><div className="flex flex-wrap gap-2"><Link href={route('company.settings.edit', selectedCompanyId ? { company_id: selectedCompanyId } : {})} className="rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm ring-1 ring-slate-200 transition hover:bg-indigo-600 hover:text-white hover:ring-indigo-600">Configuração</Link><Link href={route('company.users.index', selectedCompanyId ? { company_id: selectedCompanyId } : {})} className="rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm ring-1 ring-slate-200 transition hover:bg-indigo-600 hover:text-white hover:ring-indigo-600">Equipe</Link><Link href={route('company.audit.index', selectedCompanyId ? { company_id: selectedCompanyId } : {})} className="rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm ring-1 ring-slate-200 transition hover:bg-indigo-600 hover:text-white hover:ring-indigo-600">Auditoria</Link></div></section>}
            </div>
        </div>
    </AuthenticatedLayout>;
}
