import { Link } from '@inertiajs/react';

export default function ProductFooter({ className = '' }: { className?: string }) {
    return (
        <div className={`flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500 ${className}`}>
            <p>© {new Date().getFullYear()} Trilha+ · Um produto NexoCore Tecnologia</p>
            <nav aria-label="Documentos legais" className="flex gap-3">
                <Link href={route('legal.privacy')} className="underline-offset-2 transition hover:text-brand-700 hover:underline">Privacidade</Link>
                <Link href={route('legal.terms')} className="underline-offset-2 transition hover:text-brand-700 hover:underline">Termos de Uso</Link>
            </nav>
        </div>
    );
}
