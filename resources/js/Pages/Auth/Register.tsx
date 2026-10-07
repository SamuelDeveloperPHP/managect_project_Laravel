import AuthHeading, { linkClass, submitButtonClass } from '@/Components/AuthHeading';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

type DocumentType = 'CNPJ' | 'CPF';

const documentConfig: Record<DocumentType, { label: string; placeholder: string; maxLength: number; help: string }> = {
    CNPJ: { label: 'CNPJ da empresa', placeholder: '00.000.000/0000-00', maxLength: 18, help: 'Informe o CNPJ da empresa. A pontuação é aceita.' },
    CPF: { label: 'CPF do titular', placeholder: '000.000.000-00', maxLength: 14, help: 'Para profissionais autônomos. A pontuação é aceita.' },
};

function SectionTitle({ id, step, children }: { id: string; step: string; children: string }) {
    return (
        <div className="flex items-center gap-3">
            <span aria-hidden="true" className="grid h-6 w-6 place-items-center rounded-full bg-brand-50 text-xs font-bold text-brand-700">{step}</span>
            <h2 id={id} className="font-display text-base font-bold tracking-tight text-neutral-900">{children}</h2>
        </div>
    );
}

export default function Register() {
    const { data, setData, post, processing, errors, reset } = useForm({
        company_name: '',
        document_type: 'CNPJ' as DocumentType,
        company_document: '',
        name: '',
        cpf: '',
        email: '',
        secondary_recovery_email: '',
        password: '',
        password_confirmation: '',
        accept_terms: false,
    });
    const doc = documentConfig[data.document_type];

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        post(route('register'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <GuestLayout wide>
            <Head title="Fazer cadastro">
                <meta name="description" content="Faça seu cadastro e crie o acesso do administrador principal da empresa no Trilha+." />
            </Head>

            <AuthHeading eyebrow="Cadastro" title="Faça seu cadastro">Informe os dados da empresa e do administrador principal. Os documentos são validados e vinculados à conta.</AuthHeading>

            <form onSubmit={submit} className="space-y-9">
                <section aria-labelledby="company-section-title" className="space-y-4">
                    <SectionTitle id="company-section-title" step="1">Dados da empresa</SectionTitle>

                    <div>
                        <InputLabel htmlFor="company_name" value="Razão social ou nome da empresa" />
                        <TextInput id="company_name" name="company_name" value={data.company_name} className="mt-2 block w-full" autoComplete="organization" maxLength={120} required onChange={(event) => setData('company_name', event.target.value)} />
                        <InputError message={errors.company_name} className="mt-2" />
                    </div>

                    <fieldset>
                        <legend className="text-sm font-semibold text-neutral-700">Cadastrar com</legend>
                        <div className="mt-2 grid grid-cols-2 gap-2 rounded-xl bg-neutral-100 p-1" role="radiogroup">
                            {(['CNPJ', 'CPF'] as DocumentType[]).map((type) => (
                                <label key={type} className={`flex min-h-10 cursor-pointer items-center justify-center rounded-lg text-sm font-semibold transition focus-within:ring-4 focus-within:ring-brand-500/20 ${data.document_type === type ? 'bg-white text-brand-800 shadow-sm' : 'text-neutral-600 hover:text-neutral-900'}`}>
                                    <input type="radio" name="document_type" value={type} checked={data.document_type === type} onChange={() => { setData((previous) => ({ ...previous, document_type: type, company_document: '' })); }} className="sr-only" />
                                    {type === 'CNPJ' ? 'CNPJ (empresa)' : 'CPF (autônomo)'}
                                </label>
                            ))}
                        </div>
                    </fieldset>

                    <div>
                        <InputLabel htmlFor="company_document" value={doc.label} />
                        <TextInput id="company_document" name="company_document" value={data.company_document} className="mt-2 block w-full" inputMode="numeric" autoComplete="off" maxLength={doc.maxLength} placeholder={doc.placeholder} required aria-describedby="document-help" onChange={(event) => setData('company_document', event.target.value)} />
                        <p id="document-help" className="mt-1.5 text-xs text-neutral-500">{doc.help}</p>
                        <InputError message={errors.company_document} className="mt-2" />
                    </div>
                </section>

                <section aria-labelledby="admin-section-title" className="space-y-4">
                    <SectionTitle id="admin-section-title" step="2">Administrador principal</SectionTitle>

                    <div>
                        <InputLabel htmlFor="name" value="Nome completo" />
                        <TextInput id="name" name="name" value={data.name} className="mt-2 block w-full" autoComplete="name" maxLength={120} required onChange={(event) => setData('name', event.target.value)} />
                        <InputError message={errors.name} className="mt-2" />
                    </div>
                    <div>
                        <InputLabel htmlFor="cpf" value="CPF do administrador" />
                        <TextInput id="cpf" name="cpf" value={data.cpf} className="mt-2 block w-full" inputMode="numeric" autoComplete="off" maxLength={14} placeholder="000.000.000-00" required aria-describedby="cpf-help" onChange={(event) => setData('cpf', event.target.value)} />
                        <p id="cpf-help" className="mt-1.5 text-xs text-neutral-500">O CPF identifica o administrador e não será exibido publicamente. O acesso ao sistema é sempre por e-mail e senha.</p>
                        <InputError message={errors.cpf} className="mt-2" />
                    </div>
                    <div>
                        <InputLabel htmlFor="email" value="E-mail de acesso" />
                        <TextInput id="email" type="email" name="email" value={data.email} className="mt-2 block w-full" autoComplete="username" maxLength={190} required autoCapitalize="none" spellCheck={false} onChange={(event) => setData('email', event.target.value)} />
                        <InputError message={errors.email} className="mt-2" />
                    </div>
                    <div>
                        <InputLabel htmlFor="secondary_recovery_email" value="Segundo e-mail de recuperação (opcional)" />
                        <TextInput id="secondary_recovery_email" type="email" name="secondary_recovery_email" value={data.secondary_recovery_email} className="mt-2 block w-full" autoComplete="off" onChange={(e) => setData('secondary_recovery_email', e.target.value)} />
                        <p className="mt-1.5 text-xs text-neutral-500">Deve ser diferente do e-mail de acesso do administrador.</p>
                        <InputError message={errors.secondary_recovery_email} className="mt-2" />
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel htmlFor="password" value="Senha" />
                            <TextInput id="password" type="password" name="password" value={data.password} className="mt-2 block w-full" autoComplete="new-password" minLength={12} required onChange={(event) => setData('password', event.target.value)} />
                            <InputError message={errors.password} className="mt-2" />
                        </div>
                        <div>
                            <InputLabel htmlFor="password_confirmation" value="Confirmar senha" />
                            <TextInput id="password_confirmation" type="password" name="password_confirmation" value={data.password_confirmation} className="mt-2 block w-full" autoComplete="new-password" minLength={12} required onChange={(event) => setData('password_confirmation', event.target.value)} />
                            <InputError message={errors.password_confirmation} className="mt-2" />
                        </div>
                    </div>
                    <p className="-mt-2 text-xs text-neutral-500">Use uma senha com pelo menos 12 caracteres.</p>
                </section>

                <div>
                    <label className="flex cursor-pointer items-start gap-3 text-sm text-neutral-700">
                        <input type="checkbox" name="accept_terms" checked={data.accept_terms} onChange={(event) => setData('accept_terms', event.target.checked)} required className="mt-0.5 h-5 w-5 shrink-0 rounded border-neutral-300 text-brand-700 focus:ring-brand-500" />
                        <span>Li e aceito a <Link href={route('legal.privacy')} target="_blank" className={linkClass}>Política de Privacidade</Link> e os <Link href={route('legal.terms')} target="_blank" className={linkClass}>Termos de Uso</Link>.</span>
                    </label>
                    <InputError message={errors.accept_terms} className="mt-2" />
                </div>

                <button type="submit" disabled={processing} className={submitButtonClass}>
                    {processing ? 'Enviando cadastro…' : 'Fazer cadastro'}
                </button>
            </form>

            <div className="mt-6 rounded-xl bg-neutral-50 px-4 py-4 text-center text-sm text-neutral-600">
                Já tem uma conta?{' '}
                <Link href={route('login')} className={linkClass}>Entrar</Link>
            </div>
        </GuestLayout>
    );
}
