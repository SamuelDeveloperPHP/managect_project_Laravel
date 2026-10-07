import { btnSecondary } from '@/Components/ui';
import { Link } from '@inertiajs/react';

export default function PrivacyForm({ privacy }: { privacy: { terms_version: string | null; terms_accepted_at: string | null } }) {
    return (
        <section className="max-w-xl space-y-5">
            <header>
                <h2 className="font-display text-lg font-extrabold tracking-tight text-neutral-950">Privacidade e seus dados</h2>
                <p className="mt-1 text-sm text-neutral-600">Você pode levar uma cópia de tudo o que o Trilha+ guarda sobre você e consultar os textos que aceitou.</p>
            </header>

            <dl className="rounded-xl bg-neutral-50 p-4 text-sm">
                <dt className="font-semibold text-neutral-800">Política e Termos aceitos</dt>
                <dd className="mt-0.5 text-neutral-600">
                    {privacy.terms_accepted_at ? `Versão ${privacy.terms_version}, em ${new Date(privacy.terms_accepted_at).toLocaleString('pt-BR')}` : 'Ainda sem registro de aceite.'}
                </dd>
            </dl>

            <div className="flex flex-wrap items-center gap-3">
                <a href={route('profile.data-export')} className={btnSecondary} download>Baixar meus dados (JSON)</a>
                <Link href={route('legal.privacy')} className="text-sm font-semibold text-brand-700 hover:text-brand-900">Política de Privacidade</Link>
                <Link href={route('legal.terms')} className="text-sm font-semibold text-brand-700 hover:text-brand-900">Termos de Uso</Link>
            </div>
        </section>
    );
}
