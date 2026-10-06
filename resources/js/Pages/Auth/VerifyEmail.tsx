import AuthHeading, { submitButtonClass } from '@/Components/AuthHeading';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function VerifyEmail({ status }: { status?: string }) {
    const { post, processing } = useForm({});

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('verification.send'));
    };

    return (
        <GuestLayout>
            <Head title="Verificar e-mail" />

            <AuthHeading eyebrow="Quase lá" title="Verifique seu e-mail">Enviamos um link de verificação para o e-mail cadastrado. Se não recebeu, podemos enviar outro.</AuthHeading>

            {status === 'verification-link-sent' && (
                <div role="status" className="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    Um novo link de verificação foi enviado para o e-mail informado no cadastro.
                </div>
            )}

            <form onSubmit={submit} className="space-y-4">
                <button type="submit" disabled={processing} className={submitButtonClass}>Reenviar e-mail de verificação</button>
                <div className="text-center">
                    <Link href={route('logout')} method="post" as="button" className="rounded text-sm font-semibold text-neutral-600 underline underline-offset-4 hover:text-neutral-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500">Sair</Link>
                </div>
            </form>
        </GuestLayout>
    );
}
