import ApplicationLogo from '@/Components/ApplicationLogo';
import ProductFooter from '@/Components/ProductFooter';
import { Link, usePage } from '@inertiajs/react';
import { PropsWithChildren, ReactNode, useState } from 'react';

type NavigationItem = {
    label: string;
    href: string;
    active: boolean;
    icon: 'dashboard' | 'companies' | 'projects' | 'team' | 'audit' | 'settings' | 'versions';
};

function NavigationIcon({ name }: { name: NavigationItem['icon'] }) {
    const paths: Record<NavigationItem['icon'], ReactNode> = {
        dashboard: <><rect x="3.5" y="3.5" width="7" height="7" rx="1.5" /><rect x="13.5" y="3.5" width="7" height="5" rx="1.5" /><rect x="13.5" y="11.5" width="7" height="9" rx="1.5" /><rect x="3.5" y="13.5" width="7" height="7" rx="1.5" /></>,
        companies: <><rect x="4" y="3" width="16" height="18" rx="2" /><path d="M8 7h2m4 0h2M8 11h2m4 0h2M9 21v-5h6v5" /></>,
        projects: <><path d="M4 7.5h16v12H4z" /><path d="M8 7.5V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2.5M4 12h16m-9 0v2h2v-2" /></>,
        team: <><circle cx="9" cy="8" r="3.5" /><path d="M3 20v-1a6 6 0 0 1 12 0v1m2-13a3.5 3.5 0 0 1 0 6.8M18 15a5 5 0 0 1 3 4.5v.5" /></>,
        audit: <><path d="M4 5h16M4 12h16M4 19h16" /><circle cx="8" cy="5" r="1.5" fill="currentColor" stroke="none" /><circle cx="15" cy="12" r="1.5" fill="currentColor" stroke="none" /><circle cx="10" cy="19" r="1.5" fill="currentColor" stroke="none" /></>,
        versions: <><path d="M6 5h14M6 12h14M6 19h14" /><circle cx="4" cy="5" r="1.5" fill="currentColor" stroke="none" /><circle cx="4" cy="12" r="1.5" fill="currentColor" stroke="none" /><circle cx="4" cy="19" r="1.5" fill="currentColor" stroke="none" /></>,
        settings: <><path d="M12 8.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7Z" /><path d="m19.4 15 .1.1a1.8 1.8 0 1 1-2.5 2.5l-.1-.1a1.8 1.8 0 0 0-3.1 1.3v.2a1.8 1.8 0 1 1-3.6 0v-.2a1.8 1.8 0 0 0-3.1-1.3l-.1.1a1.8 1.8 0 1 1-2.5-2.5l.1-.1a1.8 1.8 0 0 0-1.3-3.1h-.2a1.8 1.8 0 1 1 0-3.6h.2a1.8 1.8 0 0 0 1.3-3.1l-.1-.1a1.8 1.8 0 1 1 2.5-2.5l.1.1a1.8 1.8 0 0 0 3.1-1.3v-.2a1.8 1.8 0 1 1 3.6 0v.2a1.8 1.8 0 0 0 3.1 1.3l.1-.1a1.8 1.8 0 1 1 2.5 2.5l-.1.1a1.8 1.8 0 0 0 1.3 3.1h.2a1.8 1.8 0 1 1 0 3.6h-.2a1.8 1.8 0 0 0-1.3 3.1Z" /></>,
    };

    return <svg className="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">{paths[name]}</svg>;
}

export default function Authenticated({
    header,
    children,
}: PropsWithChildren<{ header?: ReactNode }>) {
    const { auth } = usePage().props;
    const user = auth.user;
    const company = auth.company;
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const isAdmin = user.role === 'admin' || user.role === 'master';

    const navigation: NavigationItem[] = [
        { label: 'Painel', href: route('dashboard'), active: route().current('dashboard'), icon: 'dashboard' },
        ...(user.role === 'master' ? [{ label: 'Empresas', href: route('master.companies.index'), active: route().current('master.companies.*'), icon: 'companies' as const }] : []),
        { label: 'Projetos', href: route('projects.index'), active: route().current('projects.*'), icon: 'projects' },
        { label: 'Versões', href: route('company.versions.index'), active: route().current('company.versions.*'), icon: 'versions' },
        ...(isAdmin ? [
            { label: 'Equipe', href: route('company.users.index'), active: route().current('company.users.*'), icon: 'team' as const },
            { label: 'Auditoria', href: route('company.audit.index'), active: route().current('company.audit.*'), icon: 'audit' as const },
            { label: 'Empresa', href: route('company.settings.edit'), active: route().current('company.settings.*') || route().current('company.documents.*'), icon: 'settings' as const },
        ] : []),
    ];

    return (
        <div className="min-h-screen bg-[#f5f6fa] md:flex">
            {sidebarOpen && <button type="button" aria-label="Fechar menu" onClick={() => setSidebarOpen(false)} className="fixed inset-0 z-30 bg-slate-950/35 md:hidden" />}

            <aside className={`fixed inset-y-0 left-0 z-40 flex w-[264px] flex-col border-r border-slate-200 bg-white transition-transform duration-200 md:sticky md:top-0 md:h-screen md:translate-x-0 ${sidebarOpen ? 'translate-x-0' : '-translate-x-full'}`}>
                <div className="flex h-[76px] shrink-0 items-center border-b border-slate-100 px-6">
                    <Link href={route('projects.index')} onClick={() => setSidebarOpen(false)} className="flex items-center gap-3 rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
                        <ApplicationLogo className="h-9 w-9 fill-current text-indigo-600" />
                        <span className="text-base font-bold tracking-tight text-slate-900">Trilha+</span>
                    </Link>
                    <button type="button" aria-label="Fechar menu" onClick={() => setSidebarOpen(false)} className="ml-auto rounded-lg p-2 text-slate-400 hover:bg-slate-100 md:hidden">
                        <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18" /></svg>
                    </button>
                </div>

                <div className="px-4 pt-6">
                    <p className="px-3 text-[10px] font-semibold text-slate-400">Workspace</p>
                    <nav aria-label="Navegação principal" className="mt-3 space-y-1">
                        {navigation.map((item) => (
                            <Link key={item.label} href={item.href} onClick={() => setSidebarOpen(false)} aria-current={item.active ? 'page' : undefined} className={`group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 ${item.active ? 'bg-indigo-50 text-indigo-700' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'}`}>
                                <NavigationIcon name={item.icon} />
                                <span>{item.label}</span>
                                {item.active && <span className="ml-auto h-1.5 w-1.5 rounded-full bg-indigo-600" />}
                            </Link>
                        ))}
                    </nav>
                </div>

                <div className="mt-auto border-t border-slate-100 p-4">
                    <div className="mb-3 flex items-center gap-3 rounded-xl bg-slate-50 p-3">
                        {user.profile_photo_url ? <img src={user.profile_photo_url} alt="" className="h-9 w-9 shrink-0 rounded-full border border-slate-200 object-cover" /> : <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-sm font-semibold text-indigo-700" aria-hidden="true">{user.name.trim().charAt(0).toUpperCase()}</div>}
                        <div className="min-w-0">
                            <p className="truncate text-sm font-semibold text-slate-800">{user.name}</p>
                            <p className="truncate text-xs text-slate-500">{company?.name ?? user.email}</p>
                        </div>
                    </div>
                    <div className="grid grid-cols-2 gap-2">
                        <Link href={route('profile.edit')} onClick={() => setSidebarOpen(false)} className="rounded-lg px-3 py-2 text-center text-xs font-semibold text-slate-600 transition hover:bg-slate-100 hover:text-slate-900">Perfil</Link>
                        <Link href={route('logout')} method="post" as="button" onClick={() => setSidebarOpen(false)} className="rounded-lg px-3 py-2 text-center text-xs font-semibold text-slate-600 transition hover:bg-slate-100 hover:text-slate-900">Sair</Link>
                    </div>
                </div>
            </aside>

            <div className="min-w-0 flex-1">
                <div className="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-slate-200 bg-white/95 px-4 backdrop-blur md:hidden">
                    <button type="button" aria-label="Abrir menu" aria-expanded={sidebarOpen} onClick={() => setSidebarOpen(true)} className="rounded-lg p-2 text-slate-600 transition hover:bg-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
                        <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16" /></svg>
                    </button>
                    <Link href={route('projects.index')} className="flex items-center gap-2">
                        <ApplicationLogo className="h-7 w-7 fill-current text-indigo-600" />
                        <span className="text-sm font-bold text-slate-900">Trilha+</span>
                    </Link>
                    {company?.name && <span className="ml-auto max-w-[42%] truncate text-xs font-medium text-slate-500">{company.name}</span>}
                </div>

                {header && <header className="border-b border-slate-200 bg-white"><div className="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">{header}</div></header>}
                <main>{children}</main>
                <footer className="border-t border-slate-200 px-4 py-4 sm:px-6 lg:px-8"><ProductFooter /></footer>
            </div>
        </div>
    );
}
