import AuthHeading, { linkClass, submitButtonClass } from '@/Components/AuthHeading';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function ForgotPassword({ status }: { status?: string }) {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('password.email'));
    };

    return (
        <GuestLayout>
            <Head title="Recuperar acesso" />

            <AuthHeading eyebrow="Recuperação de acesso" title="Esqueceu a senha?">
                Informe o e-mail da sua conta e enviaremos um link para criar uma nova senha. Para o administrador da empresa, o link também chega ao e-mail secundário de recuperação, quando cadastrado.
            </AuthHeading>

            {status && (
                <div role="status" className="mb-5 flex gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm leading-5 text-emerald-800">
                    <svg aria-hidden="true" className="mt-0.5 h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="m5 12 4 4L19 6" /></svg>
                    <span>{status}</span>
                </div>
            )}

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
                        autoCapitalize="none"
                        spellCheck={false}
                        required
                        isFocused={true}
                        onChange={(e) => setData('email', e.target.value)}
                    />
                    <InputError message={errors.email} className="mt-2" />
                </div>

                <button type="submit" disabled={processing} className={submitButtonClass}>
                    {processing ? 'Enviando…' : 'Enviar link de recuperação'}
                </button>
            </form>

            <p className="mt-6 text-center text-sm text-neutral-600">
                Lembrou a senha? <Link href={route('login')} className={linkClass}>Voltar para o login</Link>
            </p>
        </GuestLayout>
    );
}
