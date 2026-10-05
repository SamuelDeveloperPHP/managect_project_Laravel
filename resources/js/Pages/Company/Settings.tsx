import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

type CompanyProfile = {
    id: number; name: string; document_type: 'CPF' | 'CNPJ'; document_number: string; cnpj: string | null;
    domain: string | null; zip_code: string | null; street: string | null; number: string | null; complement: string | null;
    neighborhood: string | null; city: string | null; state: string | null; contact_name: string | null;
    contact_email: string | null; contact_whatsapp: string | null; secondary_recovery_email: string | null; administrator_emails?: string[];
};
type Document = { id: number; name: string; size_bytes: number; created_at: string };
type ProfileForm = Omit<CompanyProfile, 'id' | 'cnpj'>;

const inputClass = 'mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500';
const labelClass = 'block text-sm font-medium text-slate-700';
const humanSize = (bytes: number) => bytes < 1024 * 1024 ? `${Math.ceil(bytes / 1024)} KB` : `${(bytes / (1024 * 1024)).toFixed(1)} MB`;

export default function Settings({ company, hasLogo, documents, companies, selectedCompanyId }: {
    company: CompanyProfile; hasLogo: boolean; documents: Document[]; companies: { id: number; name: string }[]; selectedCompanyId: number;
}) {
    const [saved, setSaved] = useState(false);
    const form = useForm<ProfileForm>({
        name: company.name ?? '', document_type: company.document_type ?? 'CNPJ', document_number: company.document_number ?? '',
        domain: company.domain ?? '', zip_code: company.zip_code ?? '', street: company.street ?? '', number: company.number ?? '',
        complement: company.complement ?? '', neighborhood: company.neighborhood ?? '', city: company.city ?? '', state: company.state ?? '',
        contact_name: company.contact_name ?? '', contact_email: company.contact_email ?? '', contact_whatsapp: company.contact_whatsapp ?? '',
        secondary_recovery_email: company.secondary_recovery_email ?? '',
    });
    const logo = useForm<{ logo: File | null }>({ logo: null });
    const pdf = useForm<{ document: File | null }>({ document: null });
    const companyQuery = { company_id: selectedCompanyId };

    const save = (event: FormEvent) => {
        event.preventDefault();
        form.put(route('company.settings.update', companyQuery), { preserveScroll: true, onSuccess: () => setSaved(true) });
    };
    const uploadLogo = (event: FormEvent) => {
        event.preventDefault();
        logo.post(route('company.settings.logo', companyQuery), { forceFormData: true, preserveScroll: true });
    };
    const uploadPdf = (event: FormEvent) => {
        event.preventDefault();
        pdf.post(route('company.documents.store', companyQuery), { forceFormData: true, preserveScroll: true, onSuccess: () => pdf.reset('document') });
    };

    return <AuthenticatedLayout header={<div><h2 className="text-2xl font-semibold text-slate-900">Configuração da empresa</h2></div>}>
        <Head title="Configuração da empresa" />
        <div className="mx-auto max-w-5xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
            <div className="flex flex-wrap items-end justify-between gap-4"><div><h1 className="text-lg font-semibold text-slate-900">Dados cadastrais e documentos</h1><p className="mt-1 text-sm text-slate-500">Esses dados ficam isolados para a empresa selecionada.</p></div>{companies.length > 0 && <label className={labelClass}>Empresa<select value={selectedCompanyId} onChange={(event) => router.get(route('company.settings.edit'), { company_id: event.target.value }, { preserveScroll: true })} className={`${inputClass} min-w-64`}>{companies.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select></label>}</div>

            <form onSubmit={save} className="space-y-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <section><h3 className="font-semibold text-slate-900">Identificação</h3><div className="mt-4 grid gap-4 sm:grid-cols-2"><label className={`${labelClass} sm:col-span-2`}>Nome da empresa<input className={inputClass} value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} maxLength={190} />{form.errors.name && <span className="mt-1 block text-xs text-rose-600">{form.errors.name}</span>}</label><label className={labelClass}>Tipo de documento<select className={inputClass} value={form.data.document_type} onChange={(event) => form.setData('document_type', event.target.value as 'CPF' | 'CNPJ')}><option value="CNPJ">CNPJ</option><option value="CPF">CPF</option></select></label><label className={labelClass}>CNPJ / CPF<input className={inputClass} value={form.data.document_number} onChange={(event) => form.setData('document_number', event.target.value)} maxLength={18} />{form.errors.document_number && <span className="mt-1 block text-xs text-rose-600">{form.errors.document_number}</span>}</label><label className={labelClass}>Domínio corporativo<input className={inputClass} placeholder="empresa.com.br" value={form.data.domain ?? ''} onChange={(event) => form.setData('domain', event.target.value)} />{form.errors.domain && <span className="mt-1 block text-xs text-rose-600">{form.errors.domain}</span>}</label></div></section>

                <section className="border-t border-slate-100 pt-5"><h3 className="font-semibold text-slate-900">Contato</h3><div className="mt-4 grid gap-4 sm:grid-cols-2"><label className={labelClass}>Pessoa de contato<input className={inputClass} value={form.data.contact_name ?? ''} onChange={(event) => form.setData('contact_name', event.target.value)} />{form.errors.contact_name && <span className="mt-1 block text-xs text-rose-600">{form.errors.contact_name}</span>}</label><label className={labelClass}>WhatsApp<input className={inputClass} value={form.data.contact_whatsapp ?? ''} onChange={(event) => form.setData('contact_whatsapp', event.target.value)} />{form.errors.contact_whatsapp && <span className="mt-1 block text-xs text-rose-600">{form.errors.contact_whatsapp}</span>}</label><label className={labelClass}>E-mail de contato<input type="email" className={inputClass} value={form.data.contact_email ?? ''} onChange={(event) => form.setData('contact_email', event.target.value)} />{form.errors.contact_email && <span className="mt-1 block text-xs text-rose-600">{form.errors.contact_email}</span>}</label></div></section>

                <section className="border-t border-slate-100 pt-5"><h3 className="font-semibold text-slate-900">Recuperação de acesso</h3><p className="mt-1 text-sm text-slate-500">A recuperação usa o e-mail do administrador e, opcionalmente, um segundo e-mail que deve ser diferente do e-mail do administrador.</p><div className="mt-4 grid gap-4 sm:grid-cols-2"><label className={labelClass}>E-mail do administrador<input type="email" readOnly disabled className={inputClass} value={(company.administrator_emails ?? []).join(', ')} /></label><label className={labelClass}>E-mail de recuperação secundário<input type="email" className={inputClass} value={form.data.secondary_recovery_email ?? ''} onChange={(event) => form.setData('secondary_recovery_email', event.target.value)} />{form.errors.secondary_recovery_email && <span className="mt-1 block text-xs text-rose-600">{form.errors.secondary_recovery_email}</span>}</label></div></section>

                <section className="border-t border-slate-100 pt-5"><h3 className="font-semibold text-slate-900">Endereço</h3><div className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">{([['zip_code', 'CEP'], ['street', 'Logradouro'], ['number', 'Número'], ['complement', 'Complemento'], ['neighborhood', 'Bairro'], ['city', 'Cidade'], ['state', 'UF']] as const).map(([key, label]) => <label key={key} className={labelClass}>{label}<input className={inputClass} maxLength={key === 'state' ? 2 : undefined} value={form.data[key] ?? ''} onChange={(event) => form.setData(key, key === 'state' ? event.target.value.toUpperCase() : event.target.value)} />{form.errors[key] && <span className="mt-1 block text-xs text-rose-600">{form.errors[key]}</span>}</label>)}</div></section>

                <div className="flex flex-wrap items-center justify-end gap-3 border-t border-slate-100 pt-5">{saved && <span className="mr-auto text-sm font-medium text-emerald-700">Alterações salvas.</span>}<button disabled={form.processing} className="rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">{form.processing ? 'Salvando…' : 'Salvar alterações'}</button></div>
            </form>

            <section className="grid gap-6 md:grid-cols-2"><form onSubmit={uploadLogo} className="space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm"><div><h3 className="font-semibold text-slate-900">Logotipo</h3><p className="mt-1 text-sm text-slate-500">Imagem PNG, JPG ou WEBP até 2 MB; armazenada fora da pasta pública.</p></div><input type="file" accept="image/png,image/jpeg,image/webp" onChange={(event) => logo.setData('logo', event.target.files?.[0] ?? null)} className="block w-full text-sm text-slate-600 file:mr-4 file:rounded-lg file:border-0 file:bg-slate-100 file:px-4 file:py-2 file:font-medium" />{logo.errors.logo && <p className="text-xs text-rose-600">{logo.errors.logo}</p>}<div className="flex items-center justify-between gap-3"><span className="text-xs text-slate-500">{hasLogo ? 'Já existe um logotipo cadastrado.' : 'Nenhum logotipo cadastrado.'}</span><button disabled={!logo.data.logo || logo.processing} className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 disabled:opacity-50">Enviar imagem</button></div></form>

                <form onSubmit={uploadPdf} className="space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm"><div><h3 className="font-semibold text-slate-900">Documentos da empresa</h3><p className="mt-1 text-sm text-slate-500">Somente PDF até 10 MB. O recebimento fica bloqueado até os verificadores de estrutura/formulários e antivírus estarem configurados.</p></div><input type="file" accept="application/pdf,.pdf" onChange={(event) => pdf.setData('document', event.target.files?.[0] ?? null)} className="block w-full text-sm text-slate-600 file:mr-4 file:rounded-lg file:border-0 file:bg-slate-100 file:px-4 file:py-2 file:font-medium" />{pdf.errors.document && <p className="text-xs text-rose-600">{pdf.errors.document}</p>}<div className="flex justify-end"><button disabled={!pdf.data.document || pdf.processing} className="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">{pdf.processing ? 'Verificando…' : 'Enviar PDF'}</button></div></form></section>

            <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"><div className="border-b border-slate-100 px-5 py-4"><h3 className="font-semibold text-slate-900">Arquivos PDF</h3><p className="mt-1 text-sm text-slate-500">Acesso restrito à empresa; downloads são entregues como anexos e nunca executados pelo navegador.</p></div><div className="divide-y divide-slate-100">{documents.map((document) => <div key={document.id} className="flex flex-wrap items-center justify-between gap-3 px-5 py-4"><div><p className="font-medium text-slate-900">{document.name}</p><p className="mt-1 text-xs text-slate-500">{humanSize(document.size_bytes)} · {new Date(document.created_at).toLocaleDateString('pt-BR')}</p></div><Link href={route('company.documents.download', { document: document.id, ...companyQuery })} className="text-sm font-semibold text-indigo-700 hover:text-indigo-900">Baixar PDF</Link></div>)}{documents.length === 0 && <p className="px-5 py-8 text-center text-sm text-slate-500">Nenhum PDF cadastrado para esta empresa.</p>}</div></section>
        </div>
    </AuthenticatedLayout>;
}
