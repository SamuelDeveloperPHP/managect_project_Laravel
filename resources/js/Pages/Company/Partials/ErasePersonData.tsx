import InputError from '@/Components/InputError';
import Modal from '@/Components/Modal';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import { useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

/** LGPD: o administrador apaga os dados pessoais de um membro (a pedido do titular), confirmando com a própria senha. */
export default function ErasePersonData({ userId, name, className }: { userId: number; name: string; className: string }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors, reset, clearErrors } = useForm({ password: '' });

    const close = () => { setOpen(false); reset(); clearErrors(); };
    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        post(route('company.users.anonymize', userId), { preserveScroll: true, onSuccess: close, onFinish: () => reset('password') });
    };

    return (
        <>
            <button type="button" onClick={() => setOpen(true)} className={className}>Apagar dados</button>
            <Modal show={open} onClose={close}>
                <form onSubmit={submit} className="p-6">
                    <h2 className="font-display text-lg font-extrabold tracking-tight text-neutral-950">Apagar os dados pessoais de {name}?</h2>
                    <p className="mt-2 text-sm text-neutral-600">Nome, e-mail, CPF, foto e acessos serão removidos de forma <strong>irreversível</strong>. O histórico dos projetos continua, sem identificar a pessoa. Antes, use &ldquo;Exportar dados&rdquo; se ela precisar de uma cópia.</p>
                    <div className="mt-5">
                        <label htmlFor={`erase-password-${userId}`} className="text-sm font-semibold text-neutral-700">Sua senha, para confirmar</label>
                        <TextInput id={`erase-password-${userId}`} type="password" value={data.password} onChange={(event) => setData('password', event.target.value)} className="mt-1.5 block w-full" autoComplete="current-password" isFocused />
                        <InputError message={errors.password} className="mt-2" />
                    </div>
                    <div className="mt-6 flex justify-end gap-3">
                        <SecondaryButton onClick={close}>Cancelar</SecondaryButton>
                        <button type="submit" disabled={processing || data.password === ''} className="inline-flex min-h-11 items-center rounded-xl bg-rose-600 px-5 text-sm font-semibold text-white transition hover:bg-rose-700 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-rose-500/30 disabled:cursor-not-allowed disabled:opacity-60">{processing ? 'Apagando…' : 'Apagar dados'}</button>
                    </div>
                </form>
            </Modal>
        </>
    );
}
