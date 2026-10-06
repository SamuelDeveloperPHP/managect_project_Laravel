import AuthHeading, { linkClass, submitButtonClass } from '@/Components/AuthHeading';
import Checkbox from '@/Components/Checkbox';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
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
                <meta name="description" content="Acesse o Trilha+ para acompanhar os projetos e as tarefas da sua empresa." />
            </Head>

            <AuthHeading eyebrow="Acesso" title="Bem-vindo de volta">Entre com o e-mail e a senha da sua conta para continuar.</AuthHeading>

            {status && <div role="status" className="mb-5 flex gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm leading-5 text-emerald-800"><SuccessIcon /><span>{status}</span></div>}

            <form onSubmit={submit} className="space-y-5">
                <div>
                    <InputLabel htmlFor="email" value="E-mail" />
                    <TextInput
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        className="mt-2 block w-full"
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
                    <InputError id="email-error" message={errors.email} className="mt-2" />
                </div>

                <div>
                    <div className="flex items-center justify-between gap-3">
                        <InputLabel htmlFor="password" value="Senha" />
                        {canResetPassword && <Link href={route('password.request')} className="rounded text-sm font-semibold text-brand-700 transition hover:text-brand-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500">Esqueceu a senha?</Link>}
                    </div>
                    <TextInput
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        className="mt-2 block w-full"
                        placeholder="Digite sua senha"
                        autoComplete="current-password"
                        required
                        aria-invalid={Boolean(errors.password)}
                        aria-describedby={errors.password ? 'password-error' : undefined}
                        onChange={(event) => setData('password', event.target.value)}
                    />
                    <InputError id="password-error" message={errors.password} className="mt-2" />
                </div>

                <label className="flex w-fit cursor-pointer items-center gap-2.5 rounded py-1 text-sm text-neutral-600 focus-within:outline focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-brand-500">
                    <Checkbox
                        name="remember"
                        checked={data.remember}
                        onChange={(event) => setData('remember', event.target.checked)}
                        className="h-4 w-4 rounded border-neutral-300 text-brand-700 focus:ring-brand-500"
                    />
                    <span>Manter sessão neste dispositivo</span>
                </label>

                <button type="submit" disabled={processing} className={submitButtonClass}>
                    {processing ? <><span className="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white" />Validando acesso…</> : 'Entrar'}
                </button>
            </form>

            {canRegister && <div className="mt-6 rounded-xl bg-neutral-50 px-4 py-4 text-center text-sm text-neutral-600">
                Ainda não tem conta?{' '}
                <Link href={route('register')} className={linkClass}>Fazer cadastro</Link>
            </div>}
        </GuestLayout>
    );
}

function SuccessIcon() {
    return <svg aria-hidden="true" className="mt-0.5 h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="m5 12 4 4L19 6" /></svg>;
}
