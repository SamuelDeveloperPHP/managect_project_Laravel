import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

type ManagedUser = {
    id: number;
    name: string;
    email: string;
    role: 'admin' | 'user' | 'master';
    permissions: Record<string, boolean> | null;
    is_active: boolean;
    last_login_at: string | null;
};

const permissionLabels: Record<string, string> = {
    can_manage_projects: 'Gerenciar projetos',
    can_view_reports: 'Visualizar relatórios',
};

function UserRow({ user, currentUserId }: { user: ManagedUser; currentUserId: number }) {
    const form = useForm({
        name: user.name,
        email: user.email,
        role: user.role === 'master' ? 'user' : user.role,
        permissions: { ...(user.permissions ?? {}) },
    });
    const status = useForm({ is_active: !user.is_active });
    const ownAccount = user.id === currentUserId;

    const save: FormEventHandler = (event) => {
        event.preventDefault();
        form.put(route('company.users.update', user.id), { preserveScroll: true });
    };

    return (
        <form onSubmit={save} className="grid gap-4 border-t border-slate-100 px-5 py-5 lg:grid-cols-[1.2fr_1.4fr_180px_1.5fr_auto] lg:items-center">
            <div><p className="font-semibold text-slate-900">{user.name}{ownAccount && <span className="ml-2 text-xs font-normal text-slate-500">Você</span>}</p><p className="text-sm text-slate-500">{user.last_login_at ? `Último acesso: ${new Date(user.last_login_at).toLocaleString('pt-BR')}` : 'Ainda não acessou'}</p></div>
            <div><InputLabel htmlFor={`email-${user.id}`} value="E-mail" /><TextInput id={`email-${user.id}`} type="email" value={form.data.email} className="mt-1 block w-full" onChange={(e) => form.setData('email', e.target.value)} required /><InputError message={form.errors.email} className="mt-1" /></div>
            <div><InputLabel htmlFor={`role-${user.id}`} value="Perfil" /><select id={`role-${user.id}`} value={form.data.role} disabled={ownAccount || user.role === 'master'} onChange={(e) => form.setData('role', e.target.value as 'admin' | 'user')} className="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-slate-100"><option value="user">Usuário</option><option value="admin">Administrador</option></select><InputError message={form.errors.role} className="mt-1" /></div>
            <div className="space-y-2"><InputLabel value="Permissões" />{Object.entries(permissionLabels).map(([key, label]) => <label key={key} className="flex items-center gap-2 text-sm text-slate-600"><input type="checkbox" checked={Boolean(form.data.permissions[key])} onChange={(e) => form.setData('permissions', { ...form.data.permissions, [key]: e.target.checked })} className="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />{label}</label>)}</div>
            <div className="flex flex-wrap gap-2 lg:flex-col"><PrimaryButton disabled={form.processing || user.role === 'master'}>Salvar</PrimaryButton>{!ownAccount && user.role !== 'master' && <button type="button" onClick={() => status.patch(route('company.users.status', user.id), { preserveScroll: true })} disabled={status.processing} className="rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-50">{user.is_active ? 'Desativar' : 'Ativar'}</button>}</div>
        </form>
    );
}

export default function Users({ users, auth, companies, selectedCompanyId }: { users: ManagedUser[]; auth: { user: { id: number; role?: string } }; companies: { id: number; name: string; document_type?: string | null; document_number?: string | null }[]; selectedCompanyId: number }) {
    const form = useForm({ company_id: selectedCompanyId, name: '', email: '', role: 'user', password: '', password_confirmation: '', permissions: { can_manage_projects: false, can_view_reports: false } });
    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        form.post(route('company.users.store'), { preserveScroll: true, onSuccess: () => form.reset() });
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-slate-800">Gestão da equipe</h2>}>
            <Head title="Equipe da empresa" />
            <div className="py-8"><div className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                {auth.user.role === 'master' && <section className="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200"><label htmlFor="company-scope" className="block text-sm font-semibold text-slate-800">Empresa que será administrada</label><select id="company-scope" value={selectedCompanyId} onChange={(e) => router.get(route('company.users.index'), { company_id: e.target.value }, { preserveState: false })} className="mt-2 block w-full max-w-xl rounded-md border-slate-300 text-sm">{companies.map((company) => <option key={company.id} value={company.id}>{company.name} · {company.document_type ?? 'Documento pendente'} {company.document_number ?? ''}</option>)}</select></section>}
                <div className="rounded-xl border border-indigo-100 bg-indigo-50 p-5 text-sm text-indigo-900"><strong>Acesso restrito à sua empresa.</strong> Administradores podem gerir os perfis da equipe. Nenhum administrador da empresa pode criar ou alterar uma conta master.</div>
                <section className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
                    <div className="border-b border-slate-100 px-5 py-4"><h3 className="font-semibold text-slate-900">Usuários vinculados</h3><p className="mt-1 text-sm text-slate-500">As permissões são aplicadas no servidor em cada operação.</p></div>
                    {users.map((user) => <UserRow key={user.id} user={user} currentUserId={auth.user.id} />)}
                    {users.length === 0 && <p className="p-6 text-sm text-slate-500">Nenhum usuário cadastrado.</p>}
                </section>
                <section className="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200 sm:p-7">
                    <h3 className="font-semibold text-slate-900">Adicionar usuário</h3><p className="mt-1 text-sm text-slate-500">Crie uma conta apenas para alguém autorizado a acessar esta empresa.</p>
                    <form onSubmit={submit} className="mt-5 grid gap-4 sm:grid-cols-2">
                        {auth.user.role === 'master' && <input type="hidden" name="company_id" value={form.data.company_id} />}
                        <div><InputLabel htmlFor="new-name" value="Nome" /><TextInput id="new-name" value={form.data.name} className="mt-1 block w-full" onChange={(e) => form.setData('name', e.target.value)} required /><InputError message={form.errors.name} className="mt-1" /></div>
                        <div><InputLabel htmlFor="new-email" value="E-mail" /><TextInput id="new-email" type="email" value={form.data.email} className="mt-1 block w-full" onChange={(e) => form.setData('email', e.target.value)} required /><InputError message={form.errors.email} className="mt-1" /></div>
                        <div><InputLabel htmlFor="new-role" value="Perfil" /><select id="new-role" value={form.data.role} onChange={(e) => form.setData('role', e.target.value)} className="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"><option value="user">Usuário</option><option value="admin">Administrador</option></select></div>
                        <div className="flex flex-wrap items-end gap-4 pb-2">{Object.entries(permissionLabels).map(([key, label]) => <label key={key} className="flex items-center gap-2 text-sm text-slate-600"><input type="checkbox" checked={form.data.permissions[key as keyof typeof form.data.permissions]} onChange={(e) => form.setData('permissions', { ...form.data.permissions, [key]: e.target.checked })} className="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />{label}</label>)}</div>
                        <div><InputLabel htmlFor="new-password" value="Senha inicial (mínimo 12 caracteres)" /><TextInput id="new-password" type="password" value={form.data.password} className="mt-1 block w-full" onChange={(e) => form.setData('password', e.target.value)} required autoComplete="new-password" /><InputError message={form.errors.password} className="mt-1" /></div>
                        <div><InputLabel htmlFor="new-password-confirmation" value="Confirmar senha" /><TextInput id="new-password-confirmation" type="password" value={form.data.password_confirmation} className="mt-1 block w-full" onChange={(e) => form.setData('password_confirmation', e.target.value)} required autoComplete="new-password" /></div>
                        <div className="sm:col-span-2"><PrimaryButton disabled={form.processing}>Criar usuário</PrimaryButton></div>
                    </form>
                </section>
            </div></div>
        </AuthenticatedLayout>
    );
}
