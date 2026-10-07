import AuthHeading, { linkClass, submitButtonClass } from '@/Components/AuthHeading';
import InputError from '@/Components/InputError';
import GuestLayout from '@/Layouts/GuestLayout';
import { LegalProps } from '@/Layouts/LegalLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function AcceptTerms({ legal }: LegalProps) {
    const { data, setData, post, processing, errors } = useForm({ accept: false });

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        post(route('terms.accept.store'));
    };

    return (
        <GuestLayout>
            <Head title="Aceite dos termos" />

            <AuthHeading eyebrow="Privacidade" title="Antes de continuar">Para usar o Trilha+ é preciso ler e aceitar a Política de Privacidade e os Termos de Uso (versão {legal.version}).</AuthHeading>

            <form onSubmit={submit} className="space-y-6">
                <ul className="space-y-2 rounded-xl bg-neutral-50 p-4 text-sm">
                    <li><Link href={route('legal.privacy')} target="_blank" className={linkClass}>Política de Privacidade</Link></li>
                    <li><Link href={route('legal.terms')} target="_blank" className={linkClass}>Termos de Uso</Link></li>
                </ul>

                <div>
                    <label className="flex cursor-pointer items-start gap-3 text-sm text-neutral-700">
                        <input type="checkbox" name="accept" checked={data.accept} onChange={(event) => setData('accept', event.target.checked)} className="mt-0.5 h-5 w-5 rounded border-neutral-300 text-brand-700 focus:ring-brand-500" />
                        <span>Li e aceito a Política de Privacidade e os Termos de Uso.</span>
                    </label>
                    <InputError message={errors.accept} className="mt-2" />
                </div>

                <button type="submit" disabled={processing || !data.accept} className={`${submitButtonClass} disabled:cursor-not-allowed disabled:opacity-60`}>
                    {processing ? 'Registrando…' : 'Aceitar e continuar'}
                </button>

                <p className="text-center text-sm text-neutral-500">Não concorda? <Link href={route('logout')} method="post" as="button" className={linkClass}>Sair</Link></p>
            </form>
        </GuestLayout>
    );
}
