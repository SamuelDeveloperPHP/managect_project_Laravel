import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import { btnPrimary, btnSecondary } from '@/Components/ui';
import { router, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

type TwoFactorProps = {
    enabled: boolean;
    required: boolean;
    recovery_codes_left: number;
    setup: { qr_svg: string; secret: string } | null;
};

function RecoveryCodes({ codes }: { codes: string[] }) {
    const [copied, setCopied] = useState(false);
    const text = codes.join('\n');

    const copy = async () => {
        try {
            await navigator.clipboard.writeText(text);
            setCopied(true);
        } catch {
            setCopied(false);
        }
    };

    const download = () => {
        const url = URL.createObjectURL(new Blob([`Trilha+ — códigos de recuperação (cada um vale uma vez)\n\n${text}\n`], { type: 'text/plain' }));
        const link = document.createElement('a');
        link.href = url;
        link.download = 'trilha-codigos-de-recuperacao.txt';
        link.click();
        URL.revokeObjectURL(url);
    };

    return (
        <div role="alert" className="rounded-2xl border border-amber-300 bg-amber-50 p-5">
            <h3 className="font-display text-base font-extrabold text-amber-900">Guarde estes códigos de recuperação agora</h3>
            <p className="mt-1 text-sm text-amber-900">
                Eles só aparecem esta vez. Se você perder o celular, cada código entra uma única vez no lugar do aplicativo. Guarde em um gerenciador de senhas ou impresso, nunca no mesmo celular.
            </p>
            <ul className="mt-4 grid grid-cols-2 gap-2 font-mono text-sm text-neutral-900 sm:grid-cols-4">
                {codes.map((code) => <li key={code} className="rounded-lg bg-white px-3 py-2 text-center ring-1 ring-amber-200">{code}</li>)}
            </ul>
            <div className="mt-4 flex flex-wrap gap-2">
                <button type="button" onClick={copy} className={btnSecondary}>{copied ? 'Copiado' : 'Copiar'}</button>
                <button type="button" onClick={download} className={btnSecondary}>Baixar .txt</button>
            </div>
        </div>
    );
}

export default function TwoFactorForm({ twoFactor, className = '' }: { twoFactor: TwoFactorProps; className?: string }) {
    const { flash } = usePage().props as { flash?: { recovery_codes?: string[] | null } };
    const recoveryCodes = flash?.recovery_codes ?? null;

    const confirm = useForm({ code: '' });
    const regenerate = useForm({ password: '' });
    const disable = useForm({ password: '' });
    const [panel, setPanel] = useState<'none' | 'regenerate' | 'disable'>('none');

    const start = () => router.post(route('two-factor.start'), {}, { preserveScroll: true });
    const cancel = () => router.delete(route('two-factor.cancel'), { preserveScroll: true });

    const submitConfirm: FormEventHandler = (event) => {
        event.preventDefault();
        confirm.post(route('two-factor.confirm'), { preserveScroll: true, onSuccess: () => confirm.reset('code') });
    };
    const submitRegenerate: FormEventHandler = (event) => {
        event.preventDefault();
        regenerate.post(route('two-factor.recovery-codes'), { preserveScroll: true, onSuccess: () => { regenerate.reset('password'); setPanel('none'); } });
    };
    const submitDisable: FormEventHandler = (event) => {
        event.preventDefault();
        disable.delete(route('two-factor.disable'), { preserveScroll: true, onSuccess: () => { disable.reset('password'); setPanel('none'); } });
    };

    return (
        <section className={className}>
            <header>
                <h2 className="font-display text-lg font-extrabold tracking-tight text-neutral-950">Verificação em duas etapas</h2>
                <p className="mt-1 text-sm text-neutral-600">
                    Além da senha, o login pede um código de 6 dígitos gerado por um aplicativo no seu celular (Google Authenticator, Microsoft Authenticator, Authy, 1Password…).
                    {twoFactor.required && ' O seu perfil exige esta proteção.'}
                </p>
            </header>

            <div className="mt-6 space-y-6">
                {recoveryCodes && <RecoveryCodes codes={recoveryCodes} />}

                {twoFactor.enabled ? (
                    <>
                        <p className="flex items-center gap-2 text-sm font-semibold text-emerald-800">
                            <span aria-hidden="true" className="h-2 w-2 rounded-full bg-emerald-500" /> Ativa. Códigos de recuperação restantes: {twoFactor.recovery_codes_left}
                        </p>
                        {twoFactor.recovery_codes_left <= 2 && <p className="text-sm text-amber-800">Restam poucos códigos de recuperação. Gere novos.</p>}

                        <div className="flex flex-wrap gap-2">
                            <button type="button" onClick={() => setPanel(panel === 'regenerate' ? 'none' : 'regenerate')} className={btnSecondary}>Gerar novos códigos de recuperação</button>
                            {!twoFactor.required && <button type="button" onClick={() => setPanel(panel === 'disable' ? 'none' : 'disable')} className={btnSecondary}>Desativar</button>}
                        </div>

                        {panel === 'regenerate' && (
                            <form onSubmit={submitRegenerate} className="max-w-md space-y-3 rounded-2xl bg-neutral-50 p-4">
                                <p className="text-sm text-neutral-600">Os códigos antigos deixam de valer. Confirme com a sua senha.</p>
                                <div>
                                    <InputLabel htmlFor="regen_password" value="Senha atual" />
                                    <TextInput id="regen_password" type="password" className="mt-1.5 block w-full" autoComplete="current-password" value={regenerate.data.password} onChange={(e) => regenerate.setData('password', e.target.value)} />
                                    <InputError message={regenerate.errors.password} className="mt-2" />
                                </div>
                                <button type="submit" disabled={regenerate.processing} className={btnPrimary}>Gerar novos códigos</button>
                            </form>
                        )}

                        {panel === 'disable' && (
                            <form onSubmit={submitDisable} className="max-w-md space-y-3 rounded-2xl bg-neutral-50 p-4">
                                <p className="text-sm text-neutral-600">Sua conta volta a depender só da senha. Confirme com a sua senha.</p>
                                <div>
                                    <InputLabel htmlFor="disable_password" value="Senha atual" />
                                    <TextInput id="disable_password" type="password" className="mt-1.5 block w-full" autoComplete="current-password" value={disable.data.password} onChange={(e) => disable.setData('password', e.target.value)} />
                                    <InputError message={disable.errors.password} className="mt-2" />
                                </div>
                                <button type="submit" disabled={disable.processing} className="inline-flex min-h-11 items-center rounded-xl bg-rose-600 px-5 text-sm font-semibold text-white transition hover:bg-rose-700">Desativar verificação em duas etapas</button>
                            </form>
                        )}
                    </>
                ) : twoFactor.setup ? (
                    <div className="grid gap-6 md:grid-cols-[220px_1fr]">
                        {/* SVG como imagem (data URI): o navegador não executa scripts dentro de <img>, então não há risco de injeção de HTML. */}
                        <img className="h-auto w-full rounded-2xl border border-neutral-200 bg-white p-3" alt="QR code para o aplicativo autenticador" src={`data:image/svg+xml;base64,${btoa(twoFactor.setup.qr_svg)}`} />
                        <form onSubmit={submitConfirm} className="space-y-4">
                            <ol className="list-decimal space-y-1 pl-5 text-sm text-neutral-700">
                                <li>Abra o aplicativo autenticador e escaneie o QR code.</li>
                                <li>Se não conseguir escanear, digite a chave: <code className="rounded bg-neutral-100 px-1.5 py-0.5 font-mono text-xs">{twoFactor.setup.secret}</code></li>
                                <li>Digite abaixo o código de 6 dígitos que o aplicativo mostrar.</li>
                            </ol>
                            <div>
                                <InputLabel htmlFor="confirm_code" value="Código do aplicativo" />
                                <TextInput id="confirm_code" className="mt-1.5 block w-full max-w-xs text-center font-mono text-xl tracking-[0.3em]" inputMode="numeric" maxLength={7} placeholder="000000" autoComplete="one-time-code" value={confirm.data.code} onChange={(e) => confirm.setData('code', e.target.value)} required />
                                <InputError message={confirm.errors.code} className="mt-2" />
                            </div>
                            <div className="flex gap-2">
                                <button type="submit" disabled={confirm.processing} className={btnPrimary}>{confirm.processing ? 'Verificando…' : 'Ativar'}</button>
                                <button type="button" onClick={cancel} className={btnSecondary}>Cancelar</button>
                            </div>
                        </form>
                    </div>
                ) : (
                    <div>
                        <button type="button" onClick={start} className={btnPrimary}>Ativar verificação em duas etapas</button>
                    </div>
                )}
            </div>
        </section>
    );
}
