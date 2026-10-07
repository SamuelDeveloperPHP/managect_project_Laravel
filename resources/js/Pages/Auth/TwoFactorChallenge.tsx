import AuthHeading, { linkClass, submitButtonClass } from '@/Components/AuthHeading';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

export default function TwoFactorChallenge() {
    const [useRecovery, setUseRecovery] = useState(false);
    const { data, setData, post, processing, errors, reset, clearErrors } = useForm({ code: '', recovery_code: '' });

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        post(route('two-factor.challenge.store'), { onFinish: () => reset('code', 'recovery_code') });
    };

    const toggle = () => {
        setUseRecovery(!useRecovery);
        reset('code', 'recovery_code');
        clearErrors();
    };

    return (
        <GuestLayout>
            <Head title="Verificação em duas etapas" />

            <AuthHeading eyebrow="Segurança" title="Verificação em duas etapas">
                {useRecovery
                    ? 'Digite um dos códigos de recuperação que você guardou ao ativar. Cada código funciona uma única vez.'
                    : 'Abra o aplicativo autenticador no celular e digite o código de 6 dígitos do Trilha+.'}
            </AuthHeading>

            <form onSubmit={submit} className="space-y-5">
                {useRecovery ? (
                    <div>
                        <InputLabel htmlFor="recovery_code" value="Código de recuperação" />
                        <TextInput
                            id="recovery_code"
                            name="recovery_code"
                            value={data.recovery_code}
                            className="mt-2 block w-full font-mono tracking-wider"
                            placeholder="xxxxx-xxxxx"
                            autoComplete="one-time-code"
                            autoCapitalize="none"
                            spellCheck={false}
                            isFocused
                            required
                            onChange={(event) => setData('recovery_code', event.target.value)}
                        />
                        <InputError message={errors.code} className="mt-2" />
                    </div>
                ) : (
                    <div>
                        <InputLabel htmlFor="code" value="Código do aplicativo" />
                        <TextInput
                            id="code"
                            name="code"
                            value={data.code}
                            className="mt-2 block w-full text-center font-mono text-2xl tracking-[0.4em]"
                            inputMode="numeric"
                            pattern="[0-9 ]*"
                            maxLength={7}
                            placeholder="000000"
                            autoComplete="one-time-code"
                            isFocused
                            required
                            onChange={(event) => setData('code', event.target.value)}
                        />
                        <InputError message={errors.code} className="mt-2" />
                    </div>
                )}

                <button type="submit" disabled={processing} className={submitButtonClass}>
                    {processing ? 'Verificando…' : 'Verificar e entrar'}
                </button>
            </form>

            <div className="mt-6 space-y-3 text-center text-sm text-neutral-600">
                <button type="button" onClick={toggle} className={linkClass}>
                    {useRecovery ? 'Usar o código do aplicativo' : 'Perdi o acesso ao aplicativo: usar código de recuperação'}
                </button>
                <p>
                    <Link href={route('login')} className="font-semibold text-neutral-600 underline-offset-4 hover:text-neutral-900 hover:underline">Voltar para o login</Link>
                </p>
            </div>
        </GuestLayout>
    );
}
