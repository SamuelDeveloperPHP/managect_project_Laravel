import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import { btnPrimary, btnSecondary, fieldClass, PageHeader, panelClass } from '@/Components/ui';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

type ManagedUser = {
    id: number;
    name: string;
    email: string;
    cpf: string | null;
    role: 'admin' | 'user' | 'master';
    permissions: Record<string, boolean> | null;
    is_active: boolean;
    last_login_at: string | null;
    two_factor_confirmed_at: string | null;
};

const permissionLabels: Record<string, string> = {
    can_manage_projects: 'Gerenciar projetos',
    can_view_reports: 'Visualizar relatórios',
};

const roleLabels: Record<ManagedUser['role'], string> = { admin: 'Administrador', user: 'Usuário', master: 'Master da plataforma' };
const roleTone: Record<ManagedUser['role'], string> = {
    admin: 'bg-brand-50 text-brand-700 ring-brand-200',
    user: 'bg-neutral-100 text-neutral-700 ring-neutral-200',
    master: 'bg-signal-300/25 text-signal-600 ring-signal-300/60',
};

const smallBtn = 'inline-flex min-h-9 items-center rounded-lg border px-3 text-sm font-semibold transition focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/20 disabled:opacity-50';

function Avatar({ name, active }: { name: string; active: boolean }) {
    return <span aria-hidden="true" className={`grid h-11 w-11 shrink-0 place-items-center rounded-2xl font-display text-base font-extrabold ${active ? 'bg-brand-900 text-white' : 'bg-neutral-200 text-neutral-500'}`}>{name.trim().charAt(0).toUpperCase()}</span>;
}

function UserRow({ user, currentUserId }: { user: ManagedUser; currentUserId: number }) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        name: user.name,
        email: user.email,
        cpf: user.cpf ?? '',
        role: user.role === 'master' ? 'user' : user.role,
        permissions: { ...(user.permissions ?? {}) },
    });
    const status = useForm({ is_active: !user.is_active });
    const ownAccount = user.id === currentUserId;
    const granted = Object.entries(permissionLabels).filter(([key]) => Boolean(user.permissions?.[key])).map(([, label]) => label);

    const save: FormEventHandler = (event) => {
        event.preventDefault();
        form.put(route('company.users.update', user.id), { preserveScroll: true, onSuccess: () => setOpen(false) });
    };

    return (
        <li className="border-t border-neutral-100 first:border-t-0">
            <div className="flex flex-wrap items-center gap-4 px-6 py-5">
                <Avatar name={user.name} active={user.is_active} />
                <div className="min-w-0 flex-1 basis-56">
                    <p className="flex flex-wrap items-center gap-2 font-semibold text-neutral-900">
                        <span className="truncate">{user.name}</span>
                        {ownAccount && <span className="rounded-full bg-neutral-100 px-2 py-0.5 text-xs font-medium text-neutral-600">Você</span>}
                        <span className={`rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset ${roleTone[user.role]}`}>{roleLabels[user.role]}</span>
                        {user.two_factor_confirmed_at && <span className="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-200">2FA</span>}
                        {!user.is_active && <span className="rounded-full bg-rose-50 px-2.5 py-0.5 text-xs font-semibold text-rose-700 ring-1 ring-inset ring-rose-200">Inativo</span>}
                    </p>
                    <p className="truncate text-sm text-neutral-500">{user.email}</p>
                    <p className="mt-0.5 text-xs text-neutral-400">{user.last_login_at ? `Último acesso: ${new Date(user.last_login_at).toLocaleString('pt-BR')}` : 'Ainda não acessou'}{granted.length > 0 && ` · ${granted.join(', ')}`}</p>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    {user.role !== 'master' && <button type="button" onClick={() => setOpen(!open)} aria-expanded={open} className={`${smallBtn} border-neutral-300 text-neutral-800 hover:bg-neutral-50`}>{open ? 'Fechar' : 'Editar'}</button>}
                    {user.role === 'user' && user.is_active && <button type="button" onClick={() => { if (confirm(`Transferir a administração da empresa para ${user.name}? O administrador atual passará a ser um usuário comum.`)) router.post(route('company.users.transfer-admin', user.id), {}, { preserveScroll: true }); }} className={`${smallBtn} border-brand-300 text-brand-700 hover:bg-brand-50`}>Transferir administração</button>}
                    {user.two_factor_confirmed_at && !ownAccount && user.role !== 'master' && <button type="button" onClick={() => { if (confirm(`Redefinir a verificação em duas etapas de ${user.name}? A pessoa precisará ativar de novo no próximo acesso.`)) router.post(route('company.users.two-factor.reset', user.id), {}, { preserveScroll: true }); }} className={`${smallBtn} border-neutral-300 text-neutral-700 hover:bg-neutral-50`}>Redefinir 2FA</button>}
                    {!ownAccount && user.role !== 'master' && <button type="button" onClick={() => status.patch(route('company.users.status', user.id), { preserveScroll: true })} disabled={status.processing} className={`${smallBtn} ${user.is_active ? 'border-neutral-300 text-neutral-700 hover:bg-neutral-50' : 'border-emerald-300 text-emerald-700 hover:bg-emerald-50'}`}>{user.is_active ? 'Desativar' : 'Ativar'}</button>}
                </div>
            </div>

            {open && (
                <form onSubmit={save} className="grid gap-5 border-t border-neutral-100 bg-neutral-50 px-6 py-6 md:grid-cols-2 lg:grid-cols-4">
                    <div><InputLabel htmlFor={`email-${user.id}`} value="E-mail" /><TextInput id={`email-${user.id}`} type="email" value={form.data.email} className="mt-1.5 block w-full" onChange={(e) => form.setData('email', e.target.value)} required /><InputError message={form.errors.email} className="mt-1" /></div>
                    <div><InputLabel htmlFor={`cpf-${user.id}`} value="CPF do membro" /><TextInput id={`cpf-${user.id}`} value={form.data.cpf} maxLength={14} inputMode="numeric" autoComplete="off" className="mt-1.5 block w-full" onChange={(e) => form.setData('cpf', e.target.value)} /><InputError message={form.errors.cpf} className="mt-1" /></div>
                    <div>
                        <InputLabel htmlFor={`role-${user.id}`} value="Perfil" />
                        <select id={`role-${user.id}`} value={form.data.role} disabled={ownAccount} onChange={(e) => form.setData('role', e.target.value as 'admin' | 'user')} className={`${fieldClass} disabled:bg-neutral-100`}><option value="user">Usuário</option>{user.role === 'admin' && <option value="admin">Administrador</option>}</select>
                        <InputError message={form.errors.role} className="mt-1" />
                    </div>
                    <fieldset className="space-y-2">
                        <legend className="text-sm font-semibold text-neutral-700">Permissões</legend>
                        {Object.entries(permissionLabels).map(([key, label]) => <label key={key} className="flex items-center gap-2.5 text-sm text-neutral-700"><input type="checkbox" checked={Boolean(form.data.permissions[key])} onChange={(e) => form.setData('permissions', { ...form.data.permissions, [key]: e.target.checked })} className="h-4 w-4 rounded border-neutral-300 text-brand-700 focus:ring-brand-500" />{label}</label>)}
                    </fieldset>
                    <div className="flex gap-3 md:col-span-2 lg:col-span-4">
                        <button disabled={form.processing} className={btnPrimary}>{form.processing ? 'Salvando…' : 'Salvar alterações'}</button>
                        <button type="button" onClick={() => { form.reset(); setOpen(false); }} className={btnSecondary}>Cancelar</button>
                    </div>
                </form>
            )}
        </li>
    );
}

export default function Users({ users, auth, companies, selectedCompanyId }: { users: ManagedUser[]; auth: { user: { id: number; role?: string } }; companies: { id: number; name: string; document_type?: string | null; document_number?: string | null }[]; selectedCompanyId: number }) {
    const [adding, setAdding] = useState(false);
    const form = useForm({ company_id: selectedCompanyId, name: '', email: '', cpf: '', role: 'user', password: '', password_confirmation: '', permissions: { can_manage_projects: false, can_view_reports: false } });
    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        form.post(route('company.users.store'), { preserveScroll: true, onSuccess: () => { form.reset(); setAdding(false); } });
    };
    const activeCount = users.filter((user) => user.is_active).length;

    return (
        <AuthenticatedLayout header={<PageHeader title="Equipe" meta={`${activeCount} ${activeCount === 1 ? 'pessoa ativa' : 'pessoas ativas'} de ${users.length}`} actions={<button type="button" onClick={() => setAdding(!adding)} aria-expanded={adding} className={btnPrimary}>{adding ? 'Fechar' : 'Adicionar usuário'}</button>} />}>
            <Head title="Equipe da empresa" />
            <div className="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
                {auth.user.role === 'master' && (
                    <section className={`${panelClass} p-6`}>
                        <label htmlFor="company-scope" className="block text-sm font-semibold text-neutral-800">Empresa que será administrada</label>
                        <select id="company-scope" value={selectedCompanyId} onChange={(e) => router.get(route('company.users.index'), { company_id: e.target.value }, { preserveState: false })} className={`${fieldClass} max-w-xl`}>{companies.map((company) => <option key={company.id} value={company.id}>{company.name} · {company.document_type ?? 'Documento pendente'} {company.document_number ?? ''}</option>)}</select>
                    </section>
                )}

                <div className="flex gap-3 rounded-2xl border border-brand-100 bg-brand-50 px-5 py-4 text-sm leading-6 text-brand-900">
                    <svg className="mt-0.5 h-5 w-5 shrink-0 text-brand-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z" /><path d="m9 12 2 2 4-4" /></svg>
                    <p><strong>Acesso restrito à sua empresa.</strong> A empresa tem um único administrador, que gere a equipe e pode transferir a administração a outro membro ativo. Nenhum administrador da empresa pode criar ou alterar uma conta master.</p>
                </div>

                {adding && (
                    <section className={`${panelClass} p-6 sm:p-8`} aria-label="Adicionar usuário">
                        <h2 className="font-display text-lg font-extrabold tracking-tight text-neutral-950">Adicionar usuário</h2>
                        <p className="mt-1 text-sm text-neutral-500">Crie uma conta apenas para alguém autorizado a acessar esta empresa.</p>
                        <form onSubmit={submit} className="mt-6 grid gap-5 sm:grid-cols-2">
                            {auth.user.role === 'master' && <input type="hidden" name="company_id" value={form.data.company_id} />}
                            <div><InputLabel htmlFor="new-name" value="Nome" /><TextInput id="new-name" value={form.data.name} className="mt-1.5 block w-full" onChange={(e) => form.setData('name', e.target.value)} required /><InputError message={form.errors.name} className="mt-1" /></div>
                            <div><InputLabel htmlFor="new-email" value="E-mail" /><TextInput id="new-email" type="email" value={form.data.email} className="mt-1.5 block w-full" onChange={(e) => form.setData('email', e.target.value)} required /><InputError message={form.errors.email} className="mt-1" /></div>
                            <div><InputLabel htmlFor="new-cpf" value="CPF do membro (opcional)" /><TextInput id="new-cpf" value={form.data.cpf} maxLength={14} inputMode="numeric" autoComplete="off" className="mt-1.5 block w-full" onChange={(e) => form.setData('cpf', e.target.value)} /><InputError message={form.errors.cpf} className="mt-1" /></div>
                            <div><InputLabel htmlFor="new-role" value="Perfil" /><select id="new-role" value={form.data.role} onChange={(e) => form.setData('role', e.target.value)} className={fieldClass}><option value="user">Usuário</option></select></div>
                            <fieldset className="flex flex-wrap items-center gap-x-6 gap-y-2 sm:col-span-2">
                                <legend className="mb-2 text-sm font-semibold text-neutral-700">Permissões</legend>
                                {Object.entries(permissionLabels).map(([key, label]) => <label key={key} className="flex items-center gap-2.5 text-sm text-neutral-700"><input type="checkbox" checked={form.data.permissions[key as keyof typeof form.data.permissions]} onChange={(e) => form.setData('permissions', { ...form.data.permissions, [key]: e.target.checked })} className="h-4 w-4 rounded border-neutral-300 text-brand-700 focus:ring-brand-500" />{label}</label>)}
                            </fieldset>
                            <div><InputLabel htmlFor="new-password" value="Senha inicial (mínimo 12 caracteres)" /><TextInput id="new-password" type="password" value={form.data.password} className="mt-1.5 block w-full" onChange={(e) => form.setData('password', e.target.value)} required autoComplete="new-password" /><InputError message={form.errors.password} className="mt-1" /></div>
                            <div><InputLabel htmlFor="new-password-confirmation" value="Confirmar senha" /><TextInput id="new-password-confirmation" type="password" value={form.data.password_confirmation} className="mt-1.5 block w-full" onChange={(e) => form.setData('password_confirmation', e.target.value)} required autoComplete="new-password" /></div>
                            <div className="flex gap-3 sm:col-span-2">
                                <button disabled={form.processing} className={btnPrimary}>{form.processing ? 'Criando…' : 'Criar usuário'}</button>
                                <button type="button" onClick={() => setAdding(false)} className={btnSecondary}>Cancelar</button>
                            </div>
                        </form>
                    </section>
                )}

                <section className={`${panelClass} overflow-hidden`}>
                    <div className="border-b border-neutral-100 px-6 py-5">
                        <h2 className="font-display text-lg font-extrabold tracking-tight text-neutral-950">Usuários vinculados</h2>
                        <p className="mt-1 text-sm text-neutral-500">As permissões são aplicadas no servidor em cada operação.</p>
                    </div>
                    <ul>{users.map((user) => <UserRow key={user.id} user={user} currentUserId={auth.user.id} />)}</ul>
                    {users.length === 0 && <p className="p-8 text-center text-sm text-neutral-500">Nenhum usuário cadastrado.</p>}
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
