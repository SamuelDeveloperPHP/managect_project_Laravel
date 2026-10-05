import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import ApplicationLogo from '@/Components/ApplicationLogo';
import { Head, Link } from '@inertiajs/react';

type Notes = { implemented: string[]; fixed: string[]; updated: string[] };
type Version = { id: number; branch_name: string; commit_sha: string; commit_message: string; executed_by: string; released_at: string; notes: Notes };
type PageAuth = { user: { id: number; name: string } | null };

const dateTime = (value: string) => new Date(value).toLocaleString('pt-BR', { dateStyle: 'medium', timeStyle: 'short' });
const groups: { key: keyof Notes; label: string; accent: string }[] = [
    { key: 'implemented', label: 'Implantado', accent: 'bg-emerald-500' },
    { key: 'fixed', label: 'Corrigido', accent: 'bg-amber-500' },
    { key: 'updated', label: 'Atualizado', accent: 'bg-indigo-500' },
];

export default function Versions({ versions, auth }: { versions: Version[]; auth?: PageAuth }) {
    const history = <>
        <Head title="Histórico de versões" />
        <div className="mx-auto max-w-5xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
            <header>
                <p className="text-xs font-semibold uppercase tracking-[0.14em] text-indigo-600">Notas de release</p>
                <h1 className="mt-2 text-2xl font-semibold tracking-tight text-slate-900">Histórico de versões</h1>
                <p className="mt-1 text-sm text-slate-500">Veja o que foi implantado, corrigido e atualizado em cada publicação.</p>
            </header>

            {versions.length === 0 ? <section className="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center"><h2 className="font-semibold text-slate-800">Nenhuma versão registrada</h2><p className="mt-2 text-sm text-slate-500">As publicações aparecerão aqui quando forem importadas.</p></section> :
                <section className="relative space-y-4 before:absolute before:bottom-8 before:left-[15px] before:top-8 before:w-px before:bg-slate-200" aria-label="Linha do tempo de versões">
                    {versions.map((version) => <article key={version.id} className="relative pl-10">
                        <span className="absolute left-[9px] top-7 h-3.5 w-3.5 rounded-full border-[3px] border-indigo-100 bg-indigo-600" aria-hidden="true" />
                        <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                            <header className="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 px-5 py-4 sm:px-6">
                                <div><p className="text-xs font-medium text-slate-500">{dateTime(version.released_at)}</p><h2 className="mt-1 text-base font-semibold text-slate-900">{version.commit_message}</h2></div>
                                <span className="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">{version.branch_name}</span>
                            </header>
                            <div className="grid gap-4 px-5 py-5 sm:grid-cols-3 sm:px-6">
                                {groups.map(({ key, label, accent }) => version.notes[key].length > 0 && <section key={key}>
                                    <h3 className="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-slate-600"><span className={`h-2 w-2 rounded-full ${accent}`} />{label}<span className="text-slate-400">{version.notes[key].length}</span></h3>
                                    <ul className="mt-2 space-y-2 text-sm leading-5 text-slate-600">{version.notes[key].map((note, index) => <li key={`${key}-${index}`} className="border-l border-slate-200 pl-3">{note}</li>)}</ul>
                                </section>)}
                            </div>
                            <footer className="flex flex-wrap gap-x-5 gap-y-1 border-t border-slate-100 bg-slate-50/70 px-5 py-3 text-xs text-slate-500 sm:px-6">
                                <span>Commit <code className="font-mono text-slate-700">{version.commit_sha.slice(0, 12)}</code></span>
                                <span>Publicado por {version.executed_by}</span>
                            </footer>
                        </div>
                    </article>)}
                </section>}
        </div>
    </>;

    if (auth?.user) {
        return <AuthenticatedLayout>{history}</AuthenticatedLayout>;
    }

    return <div className="min-h-screen bg-[#f5f6fa]">
        <header className="border-b border-slate-200 bg-white">
            <div className="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                <Link href="/" className="flex items-center gap-2 font-bold tracking-tight text-slate-900"><ApplicationLogo className="h-9 w-9" />Trilha</Link>
                <Link href={route('login')} className="rounded-lg border border-slate-200 px-3.5 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Entrar</Link>
            </div>
        </header>
        <main>{history}</main>
        <footer className="border-t border-slate-200 bg-white py-5 text-center text-xs text-slate-500">Histórico público de versões do Trilha</footer>
    </div>;
}
