import Checkbox from '@/Components/Checkbox';
import InputError from '@/Components/InputError';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function Login({ status, canResetPassword, canRegister }: { status?: string; canResetPassword: boolean; canRegister: boolean }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        post(route('login'), { onFinish: () => reset('password') });
    };

    return (
        <GuestLayout>
            <Head title="Entrar">
                <meta name="description" content="Acesse o Trilha para acompanhar os projetos e as tarefas da sua empresa." />
            </Head>

            <div className="mb-7">
                <span className="grid h-12 w-12 place-items-center rounded-xl bg-blue-50 text-blue-700"><LoginIcon /></span>
                <p className="mt-6 text-xs font-extrabold uppercase tracking-[.16em] text-blue-700">Acesso à plataforma</p>
                <h1 className="mt-2 text-3xl font-black tracking-[-.04em] text-slate-950">Bem-vindo de volta</h1>
                <p className="mt-2 text-sm leading-6 text-slate-500">Entre com as credenciais da sua conta para continuar.</p>
            </div>

            {status && <div role="status" className="mb-5 flex gap-3 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm leading-5 text-emerald-800"><SuccessIcon /><span>{status}</span></div>}

            <form onSubmit={submit} className="space-y-5">
                <div>
                    <label htmlFor="email" className="block text-sm font-bold text-slate-700">E-mail</label>
                    <div className="relative mt-2">
                        <span aria-hidden="true" className="pointer-events-none absolute inset-y-0 left-0 grid w-11 place-items-center text-slate-400"><EmailIcon /></span>
                        <TextInput
                            id="email"
                            type="email"
                            name="email"
                            value={data.email}
                            className="block min-h-12 w-full rounded-lg border-slate-300 bg-white pl-11 pr-3 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-blue-500 focus:ring-blue-500"
                            placeholder="voce@empresa.com.br"
                            autoComplete="username"
                            isFocused
                            required
                            autoCapitalize="none"
                            spellCheck={false}
                            aria-invalid={Boolean(errors.email)}
                            aria-describedby={errors.email ? 'email-error' : undefined}
                            onChange={(event) => setData('email', event.target.value)}
                        />
                    </div>
                    <InputError id="email-error" message={errors.email} className="mt-2" />
                </div>

                <div>
                    <div className="flex items-center justify-between gap-3">
                        <label htmlFor="password" className="block text-sm font-bold text-slate-700">Senha</label>
                        {canResetPassword && <Link href={route('password.request')} className="rounded text-xs font-bold text-blue-700 transition hover:text-blue-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600">Esqueceu a senha?</Link>}
                    </div>
                    <div className="relative mt-2">
                        <span aria-hidden="true" className="pointer-events-none absolute inset-y-0 left-0 grid w-11 place-items-center text-slate-400"><LockIcon /></span>
                        <TextInput
                            id="password"
                            type="password"
                            name="password"
                            value={data.password}
                            className="block min-h-12 w-full rounded-lg border-slate-300 bg-white pl-11 pr-3 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-blue-500 focus:ring-blue-500"
                            placeholder="Digite sua senha"
                            autoComplete="current-password"
                            required
                            aria-invalid={Boolean(errors.password)}
                            aria-describedby={errors.password ? 'password-error' : undefined}
                            onChange={(event) => setData('password', event.target.value)}
                        />
                    </div>
                    <InputError id="password-error" message={errors.password} className="mt-2" />
                </div>

                <label className="flex w-fit cursor-pointer items-center gap-2.5 rounded py-1 text-sm text-slate-600 focus-within:outline focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-blue-600">
                    <Checkbox
                        name="remember"
                        checked={data.remember}
                        onChange={(event) => setData('remember', event.target.checked)}
                        className="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                    />
                    <span>Manter sessão neste dispositivo</span>
                </label>

                <button type="submit" disabled={processing} className="inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-lg bg-blue-600 px-5 py-3 text-sm font-extrabold text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-blue-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 disabled:translate-y-0 disabled:cursor-wait disabled:opacity-70">
                    {processing ? <><span className="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white" />Validando acesso…</> : <>Entrar <ArrowIcon /></>}
                </button>
            </form>

            {canRegister && <div className="mt-5 text-center text-sm text-slate-600">
                Ainda não tem uma conta?{' '}
                <Link href={route('register')} className="font-extrabold text-blue-700 underline decoration-blue-200 underline-offset-4 transition hover:text-blue-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600">
                    Cadastre sua empresa
                </Link>
            </div>}

            <div className="mt-6 flex items-start gap-2.5 border-t border-slate-100 pt-5 text-xs leading-5 text-slate-500"><ShieldIcon /><p>Seu acesso e os dados da sua empresa são protegidos por permissões individuais.</p></div>
        </GuestLayout>
    );
}

function LoginIcon() {
    return <svg aria-hidden="true" className="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"><path d="M10 17l5-5-5-5M15 12H3" /><path d="M12 3h5a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-5" /></svg>;
}

function EmailIcon() {
    return <svg aria-hidden="true" className="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"><rect x="3" y="5" width="18" height="14" rx="2" /><path d="m3 7 9 6 9-6" /></svg>;
}

function LockIcon() {
    return <svg aria-hidden="true" className="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"><rect x="4" y="10" width="16" height="11" rx="2" /><path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3" /></svg>;
}

function ShieldIcon() {
    return <svg aria-hidden="true" className="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"><path d="M12 3 20 6v5c0 5-3.4 8.2-8 10-4.6-1.8-8-5-8-10V6l8-3Z" /><path d="m9 12 2 2 4-4" /></svg>;
}

function SuccessIcon() {
    return <svg aria-hidden="true" className="mt-0.5 h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="m5 12 4 4L19 6" /></svg>;
}

function ArrowIcon() {
    return <svg aria-hidden="true" className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M5 12h14" /><path d="m13 6 6 6-6 6" /></svg>;
}
