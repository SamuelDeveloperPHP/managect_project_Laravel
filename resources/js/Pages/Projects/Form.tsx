import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useEffect, useState } from 'react';
import type { ReactNode } from 'react';

type TeamMember = { id: number; name: string; email: string };
type Attachment = { id: number; name: string; size_bytes: number };
type ProjectData = { id: number; name: string; code: string; client: string; status: string; priority: string; budget: string; start_date: string; deadline: string; description: string; leader_id: number | ''; member_ids: number[]; image_url: string | null; attachments: Attachment[]; backlogs_count: number; tasks_count: number };
type Props = { mode: 'create' | 'edit'; project: ProjectData | null; members: TeamMember[]; statuses: Record<string, string>; priorities: Record<string, string> };

export default function Form({ mode, project, members, statuses, priorities }: Props) {
    const editing = mode === 'edit' && project !== null;
    const form = useForm({
        name: project?.name ?? '', code: project?.code ?? '', client: project?.client ?? '', status: project?.status ?? 'planning', priority: project?.priority ?? 'low',
        budget: project?.budget ?? '', start_date: project?.start_date ?? '', deadline: project?.deadline ?? '', description: project?.description ?? '',
        leader_id: project?.leader_id ?? '', member_ids: project?.member_ids ?? [], image: null as File | null, attachments: [] as File[],
        _method: editing ? 'put' : 'post',
    });
    const [imagePreview, setImagePreview] = useState<string | null>(project?.image_url ?? null);
    useEffect(() => () => { if (imagePreview?.startsWith('blob:')) URL.revokeObjectURL(imagePreview); }, [imagePreview]);

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        form.post(editing ? route('projects.update', project.id) : route('projects.store'), { forceFormData: true });
    };
    const toggleMember = (id: number, checked: boolean) => {
        const ids = form.data.member_ids.filter((memberId) => memberId !== id);
        if (checked) ids.push(id);
        const leaderId = Number(form.data.leader_id);
        if (leaderId && !ids.includes(leaderId)) ids.push(leaderId);
        form.setData('member_ids', ids);
    };

    return <AuthenticatedLayout header={<div><p className="text-sm font-medium text-indigo-600">Projetos</p><h2 className="text-2xl font-semibold text-slate-900">{editing ? 'Editar projeto' : 'Novo projeto'}</h2></div>}>
        <Head title={editing ? 'Editar projeto' : 'Novo projeto'} />
        <div className="mx-auto max-w-7xl px-4 py-7 sm:px-6 lg:px-8">
            <div className="mb-6 flex items-start justify-between gap-4 border-b border-slate-200 pb-5"><p className="text-sm text-slate-500">Organize as informações essenciais antes de montar o cronograma.</p><Link href={route('projects.index')} className="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Voltar</Link></div>
            <form onSubmit={submit} className="grid items-start gap-4 xl:grid-cols-[minmax(0,1fr)_370px]">
                <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                    <SectionHeading title="Informações principais" subtitle="Identificação, contexto e período planejado." />
                    <div className="grid gap-x-3 gap-y-4 md:grid-cols-6">
                        <Field label="Nome do projeto" error={form.errors.name} required className="md:col-span-3"><input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} maxLength={120} className={inputClass} required /><Hint>Use um nome claro com pelo menos 3 caracteres.</Hint></Field>
                        <Field label="Código" error={form.errors.code} className="md:col-span-2"><input value={form.data.code} onChange={(e) => form.setData('code', e.target.value.toUpperCase())} maxLength={40} placeholder="Ex.: PRJ-001" className={inputClass} /></Field>
                        <Field label="Cliente" error={form.errors.client} className="md:col-span-3"><input value={form.data.client} onChange={(e) => form.setData('client', e.target.value)} maxLength={190} className={inputClass} /></Field>
                        <Field label="Status" error={form.errors.status} className="md:col-span-2"><select value={form.data.status} onChange={(e) => form.setData('status', e.target.value)} className={inputClass}>{Object.entries(statuses).map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select></Field>
                        <Field label="Prioridade" error={form.errors.priority} className="md:col-span-2"><select value={form.data.priority} onChange={(e) => form.setData('priority', e.target.value)} className={inputClass}>{Object.entries(priorities).map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select></Field>
                        <Field label="Orçamento" error={form.errors.budget} className="md:col-span-2"><input type="number" min="0" step="0.01" value={form.data.budget} onChange={(e) => form.setData('budget', e.target.value)} placeholder="0,00" className={inputClass} /></Field>
                        <Field label="Início" error={form.errors.start_date} className="md:col-span-2"><input type="date" value={form.data.start_date} onChange={(e) => form.setData('start_date', e.target.value)} className={inputClass} /></Field>
                        <Field label="Prazo" error={form.errors.deadline} className="md:col-span-2"><input type="date" value={form.data.deadline} onChange={(e) => form.setData('deadline', e.target.value)} className={inputClass} /></Field>
                        <Field label="Descrição" error={form.errors.description} className="md:col-span-6"><textarea value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} maxLength={10000} rows={5} placeholder="Resuma o objetivo, o escopo e os principais resultados esperados." className={inputClass} /></Field>
                    </div>
                </section>
                <div className="space-y-4">
                    <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <SectionHeading title="Líder e responsáveis" subtitle="Defina quem acompanha as entregas." />
                        <Field label="Líder do projeto" error={form.errors.leader_id}><select value={form.data.leader_id} onChange={(e) => { const id = e.target.value ? Number(e.target.value) : ''; form.setData('leader_id', id); if (id && !form.data.member_ids.includes(id)) form.setData('member_ids', [...form.data.member_ids, id]); }} className={inputClass}><option value="">Selecione</option>{members.map((member) => <option value={member.id} key={member.id}>{member.name}</option>)}</select><Hint>O líder também será incluído entre os responsáveis.</Hint></Field>
                        <div className="mt-4"><label htmlFor="member_ids" className="block text-sm font-semibold text-slate-700">Responsáveis</label><select id="member_ids" multiple value={form.data.member_ids.map(String)} onChange={(e) => { const ids = Array.from(e.target.selectedOptions, (option) => Number(option.value)); const leaderId = Number(form.data.leader_id); if (leaderId && !ids.includes(leaderId)) ids.push(leaderId); form.setData('member_ids', ids); }} className={`${inputClass} mt-1 h-48`}>{members.map((member) => <option value={member.id} key={member.id}>{member.name}</option>)}</select><Hint>Use Ctrl (Windows) ou Command (macOS) para selecionar mais de uma pessoa.</Hint><ErrorText>{form.errors.member_ids}</ErrorText></div>
                    </section>
                    <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <SectionHeading title="Imagem e anexos" subtitle="Inclua materiais de apoio opcionais." />
                        <div className="rounded-lg border border-dashed border-slate-300 bg-slate-50 p-4"><label htmlFor="project-image" className="block text-sm font-semibold text-slate-800">Imagem do projeto</label><p className="mt-1 text-xs text-slate-500">PNG, JPG ou outro formato de imagem. Máximo de 4 MB.</p>{imagePreview && <img src={imagePreview} alt="Prévia da imagem do projeto" className="mt-3 h-28 w-full rounded-lg object-cover" />}<input id="project-image" type="file" accept="image/*" onChange={(e) => { const file = e.target.files?.[0] ?? null; form.setData('image', file); if (imagePreview?.startsWith('blob:')) URL.revokeObjectURL(imagePreview); setImagePreview(file ? URL.createObjectURL(file) : project?.image_url ?? null); }} className="mt-3 block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-slate-200 file:px-3 file:py-2 file:font-semibold file:text-slate-700" /><ErrorText>{form.errors.image}</ErrorText></div>
                        <div className="mt-4"><label htmlFor="project-attachments" className="block text-sm font-semibold text-slate-800">Anexos</label><p className="mt-1 text-xs text-slate-500">Até 5 arquivos de apoio, máximo de 10 MB por arquivo.</p><input id="project-attachments" type="file" multiple onChange={(e) => form.setData('attachments', Array.from(e.target.files ?? []))} className="mt-2 block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:font-semibold file:text-slate-700" /><ErrorText>{form.errors.attachments}</ErrorText>{form.data.attachments.length > 0 && <ul className="mt-2 list-inside list-disc text-xs text-slate-600">{form.data.attachments.map((file) => <li key={`${file.name}-${file.lastModified}`}>{file.name}</li>)}</ul>}
                            {project?.attachments?.length ? <ul className="mt-3 divide-y divide-slate-100 rounded-lg border border-slate-200">{project.attachments.map((attachment) => <li key={attachment.id} className="flex items-center justify-between gap-2 px-3 py-2 text-xs"><a className="truncate text-indigo-700 hover:underline" href={route('projects.attachments.download', [project.id, attachment.id])}>{attachment.name}</a><button type="button" onClick={() => { if (window.confirm(`Excluir o anexo “${attachment.name}”?`)) router.delete(route('projects.attachments.destroy', [project.id, attachment.id])); }} className="shrink-0 font-medium text-rose-700 hover:underline">Excluir</button></li>)}</ul> : null}
                        </div>
                    </section>
                    <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">{editing && project && project.backlogs_count === 0 && project.tasks_count === 0 ? <button type="button" onClick={() => { if (window.confirm('Excluir este projeto?')) router.delete(route('projects.destroy', project.id)); }} className="text-sm font-medium text-rose-700 hover:underline">Excluir projeto</button> : <Link href={route('projects.index')} className="text-sm font-medium text-slate-600 hover:text-slate-900">Cancelar</Link>}<button disabled={form.processing} className="rounded-lg bg-slate-900 px-5 py-2.5 text-sm font-bold text-white hover:bg-slate-800 disabled:opacity-50">{form.processing ? 'Salvando…' : editing ? 'Salvar alterações' : 'Criar projeto'}</button></div>
                    {editing && project && <ErrorText>{(form.errors as Record<string, string>).project}</ErrorText>}
                    {editing && project && project.backlogs_count > 0 && <p className="text-xs text-slate-500">Este projeto possui {project.backlogs_count} backlog(s) e {project.tasks_count} tarefa(s). A exclusão fica indisponível para preservar esses dados.</p>}
                </div>
            </form>
        </div>
    </AuthenticatedLayout>;
}

const inputClass = 'mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500';
function Field({ label, error, required = false, className = '', children }: { label: string; error?: string; required?: boolean; className?: string; children: ReactNode }) { return <div className={className}><label className="block text-sm font-semibold text-slate-700">{label}{required && <span className="text-rose-600"> *</span>}</label>{children}<ErrorText>{error}</ErrorText></div>; }
function Hint({ children }: { children: ReactNode }) { return <p className="mt-1 text-xs text-slate-500">{children}</p>; }
function ErrorText({ children }: { children?: ReactNode }) { return children ? <p className="mt-1 text-xs text-rose-600">{children}</p> : null; }
function SectionHeading({ title, subtitle }: { title: string; subtitle: string }) { return <div className="mb-5 border-b border-slate-100 pb-3"><h2 className="font-semibold text-slate-900">{title}</h2><p className="mt-1 text-sm text-slate-500">{subtitle}</p></div>; }
