import { Head, Link } from '@inertiajs/react';
import { useEffect } from 'react';

type Project = { id: number; name: string; code: string; status: string; start_date: string | null; deadline: string | null };
type GanttWindow = Window & { saveGanttOnServer?: () => void; __managectGanttAssets?: Promise<void> };

const ganttStyles = [
    '/assets/jquery-gantt/platform.css',
    '/assets/jquery-gantt/libs/jquery/dateField/jquery.dateField.css',
    '/assets/jquery-gantt/gantt.css',
    '/assets/jquery-gantt/ganttPrint.css',
    '/assets/jquery-gantt/libs/jquery/valueSlider/mb.slider.css',
    '/assets/vendor/sweetalert2.min.css',
    '/assets/jquery-gantt/gantt-reference.css',
    '/assets/jquery-gantt/gantt-laravel.css',
];
const ganttScripts = [
    '/assets/vendor/jquery-3.7.1.min.js',
    '/assets/vendor/jquery-ui-1.13.3.min.js',
    '/assets/jquery-gantt/libs/jquery/jquery.livequery.1.1.1.min.js',
    '/assets/jquery-gantt/libs/jquery/jquery.timers.js',
    '/assets/jquery-gantt/libs/utilities.js',
    '/assets/jquery-gantt/libs/forms.js',
    '/assets/jquery-gantt/libs/date.js',
    '/assets/jquery-gantt/libs/dialogs.js',
    '/assets/jquery-gantt/libs/layout.js',
    '/assets/jquery-gantt/libs/i18nJs.js',
    '/assets/jquery-gantt/libs/jquery/dateField/jquery.dateField.js',
    '/assets/jquery-gantt/libs/jquery/JST/jquery.JST.js',
    '/assets/jquery-gantt/libs/jquery/valueSlider/jquery.mb.slider.js',
    '/assets/jquery-gantt/libs/jquery/svg/jquery.svg.min.js',
    '/assets/jquery-gantt/libs/jquery/svg/jquery.svgdom.1.8.js',
    '/assets/jquery-gantt/ganttUtilities.js',
    '/assets/jquery-gantt/ganttTask.js',
    '/assets/jquery-gantt/ganttDrawerSVG.js',
    '/assets/jquery-gantt/ganttZoom.js',
    '/assets/jquery-gantt/ganttGridEditor.js',
    '/assets/jquery-gantt/ganttMaster.js',
    '/assets/vendor/sweetalert2.min.js',
    '/assets/jquery-gantt/i18n/pt-BR.js',
    '/assets/gantt-app.js',
];

function addStyles(): void {
    for (const href of ganttStyles) {
        if (document.querySelector(`link[data-managect-gantt="${href}"]`)) continue;
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = href;
        link.dataset.managectGantt = href;
        if (href.endsWith('ganttPrint.css')) link.media = 'print';
        document.head.appendChild(link);
    }
}

function addScript(src: string): Promise<void> {
    if (document.querySelector(`script[data-managect-gantt="${src}"]`)) return Promise.resolve();
    return new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = src;
        script.dataset.managectGantt = src;
        script.onload = () => resolve();
        script.onerror = () => reject(new Error(`Não foi possível carregar o cronograma (${src}).`));
        document.body.appendChild(script);
    });
}

function loadGanttAssets(): Promise<void> {
    const appWindow = window as GanttWindow;
    if (!appWindow.__managectGanttAssets) {
        addStyles();
        appWindow.__managectGanttAssets = ganttScripts.reduce(
            (chain, src) => chain.then(() => addScript(src)),
            Promise.resolve(),
        );
    }
    return appWindow.__managectGanttAssets;
}

export default function Timeline({ project, canManage, ganttTemplates }: { project: Project; canManage: boolean; ganttTemplates: string }) {
    useEffect(() => {
        document.body.dataset.projectId = String(project.id);
        loadGanttAssets()
            .then(() => document.dispatchEvent(new Event('managect:gantt:load')))
            .catch((error: Error) => {
                const toast = document.getElementById('gantt-toast');
                if (toast) {
                    toast.textContent = error.message;
                    toast.className = 'gantt-status-bar error';
                    toast.style.display = 'block';
                }
            });
    }, [project.id]);

    return <>
        <Head title={`${project.code} — Cronograma`} />
        <div className="gantt-screen fixed inset-0 z-50 flex h-screen h-[100dvh] flex-col overflow-hidden bg-[#f5f6fa]">
            <header className="flex h-14 shrink-0 items-center justify-between gap-3 border-b border-slate-200 bg-white px-3 sm:px-5">
                <div className="flex min-w-0 items-center gap-3">
                    <Link href={route('projects.overview', project.id)} aria-label="Voltar à visão geral do projeto" className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-100 hover:text-slate-900">
                        <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6M9 12h11" /></svg>
                    </Link>
                    <span className="h-7 w-px shrink-0 bg-slate-200" />
                    <div className="min-w-0"><p className="text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-400">{project.code} · Gantt</p><h1 className="truncate text-sm font-semibold text-slate-900 sm:text-base">{project.name}</h1></div>
                </div>
                <div className="flex shrink-0 items-center gap-2">
                    <Link href={route('projects.overview', project.id)} className="hidden rounded-lg px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-100 sm:inline-flex">Visão geral</Link>
                    {canManage && <button type="button" onClick={() => (window as GanttWindow).saveGanttOnServer?.()} className="rounded-lg bg-indigo-600 px-3 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-indigo-700 sm:px-4 sm:text-sm">Salvar</button>}
                </div>
            </header>

            <div id="gantt-toast" role="status" aria-live="polite" className="gantt-status-bar" />
            <div id="workSpace" className="min-h-0 w-full flex-1 overflow-hidden bg-white" />
            <div id="gantEditorTemplates" className="hidden" dangerouslySetInnerHTML={{ __html: ganttTemplates }} />
        </div>
    </>;
}
