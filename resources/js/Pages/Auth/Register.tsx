import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function Register() {
    const { data, setData, post, processing, errors, reset } = useForm({
        company_name: '',
        company_cnpj: '',
        name: '',
        cpf: '',
        email: '',
        secondary_recovery_email: '',
        password: '',
        password_confirmation: '',
    });

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        post(route('register'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <GuestLayout>
            <Head title="Cadastrar empresa · Trilha+">
                <meta name="description" content="Cadastre sua empresa e crie o acesso do administrador principal da Trilha+." />
            </Head>

            <div className="mb-7">
                <p className="text-xs font-extrabold text-blue-700">Comece pela sua empresa</p>
                <h1 className="mt-2 text-3xl font-bold tracking-[-.04em] text-slate-950">Criar conta na Trilha+</h1>
                <p className="mt-2 text-sm leading-6 text-slate-500">Cadastre a empresa e os dados do administrador principal. Os documentos são validados e vinculados à conta.</p>
            </div>

            <form onSubmit={submit} className="space-y-6">
                <section aria-labelledby="company-section-title" className="space-y-4">
                    <div className="border-b border-slate-100 pb-2">
                        <h2 id="company-section-title" className="text-sm font-extrabold text-slate-900">Dados da empresa</h2>
                    </div>
                    <div>
                        <InputLabel htmlFor="company_name" value="Razão social ou nome da empresa" />
                        <TextInput id="company_name" name="company_name" value={data.company_name} className="mt-2 block min-h-12 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" autoComplete="organization" maxLength={120} required onChange={(event) => setData('company_name', event.target.value)} />
                        <InputError message={errors.company_name} className="mt-2" />
                    </div>
                    <div>
                        <InputLabel htmlFor="company_cnpj" value="CNPJ" />
                        <TextInput id="company_cnpj" name="company_cnpj" value={data.company_cnpj} className="mt-2 block min-h-12 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" inputMode="numeric" autoComplete="off" maxLength={18} placeholder="00.000.000/0000-00" required aria-describedby="cnpj-help" onChange={(event) => setData('company_cnpj', event.target.value)} />
                        <p id="cnpj-help" className="mt-1.5 text-xs text-slate-500">Informe o CNPJ da empresa. A pontuação é aceita.</p>
                        <InputError message={errors.company_cnpj} className="mt-2" />
                    </div>
                </section>

                <section aria-labelledby="admin-section-title" className="space-y-4">
                    <div className="border-b border-slate-100 pb-2">
                        <h2 id="admin-section-title" className="text-sm font-extrabold text-slate-900">Administrador principal</h2>
                    </div>
                    <div>
                        <InputLabel htmlFor="name" value="Nome completo" />
                        <TextInput id="name" name="name" value={data.name} className="mt-2 block min-h-12 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" autoComplete="name" maxLength={120} required onChange={(event) => setData('name', event.target.value)} />
                        <InputError message={errors.name} className="mt-2" />
                    </div>
                    <div>
                        <InputLabel htmlFor="cpf" value="CPF do administrador" />
                        <TextInput id="cpf" name="cpf" value={data.cpf} className="mt-2 block min-h-12 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" inputMode="numeric" autoComplete="off" maxLength={14} placeholder="000.000.000-00" required aria-describedby="cpf-help" onChange={(event) => setData('cpf', event.target.value)} />
                        <p id="cpf-help" className="mt-1.5 text-xs text-slate-500">O CPF identifica o administrador e não será exibido publicamente.</p>
                        <InputError message={errors.cpf} className="mt-2" />
                    </div>
                    <div>
                        <InputLabel htmlFor="email" value="E-mail de acesso" />
                        <TextInput id="email" type="email" name="email" value={data.email} className="mt-2 block min-h-12 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" autoComplete="username" maxLength={190} required autoCapitalize="none" spellCheck={false} onChange={(event) => setData('email', event.target.value)} />
                        <InputError message={errors.email} className="mt-2" />
                    </div>
                    <div>
                        <InputLabel htmlFor="secondary_recovery_email" value="Segundo e-mail de recuperação (opcional)" />
                        <TextInput id="secondary_recovery_email" type="email" name="secondary_recovery_email" value={data.secondary_recovery_email} className="mt-2 block min-h-12 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" autoComplete="off" onChange={(e) => setData('secondary_recovery_email', e.target.value)} />
                        <p className="mt-1.5 text-xs text-slate-500">Deve ser diferente do e-mail de acesso do administrador.</p>
                        <InputError message={errors.secondary_recovery_email} className="mt-2" />
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel htmlFor="password" value="Senha" />
                            <TextInput id="password" type="password" name="password" value={data.password} className="mt-2 block min-h-12 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" autoComplete="new-password" minLength={12} required onChange={(event) => setData('password', event.target.value)} />
                            <InputError message={errors.password} className="mt-2" />
                        </div>
                        <div>
                            <InputLabel htmlFor="password_confirmation" value="Confirmar senha" />
                            <TextInput id="password_confirmation" type="password" name="password_confirmation" value={data.password_confirmation} className="mt-2 block min-h-12 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" autoComplete="new-password" minLength={12} required onChange={(event) => setData('password_confirmation', event.target.value)} />
                            <InputError message={errors.password_confirmation} className="mt-2" />
                        </div>
                    </div>
                    <p className="-mt-2 text-xs text-slate-500">Use uma senha com pelo menos 12 caracteres.</p>
                </section>

                <button type="submit" disabled={processing} className="inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-lg bg-blue-600 px-5 py-3 text-sm font-extrabold text-white shadow-sm transition hover:bg-blue-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 disabled:cursor-wait disabled:opacity-70">
                    {processing ? 'Criando empresa…' : 'Criar empresa e conta'}
                </button>
            </form>

            <div className="mt-6 border-t border-slate-100 pt-5 text-center text-sm text-slate-600">
                Já tem uma conta?{' '}
                <Link href={route('login')} className="font-extrabold text-blue-700 underline decoration-blue-200 underline-offset-4 transition hover:text-blue-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600">Entrar</Link>
            </div>
        </GuestLayout>
    );
}
