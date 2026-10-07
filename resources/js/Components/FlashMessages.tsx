import { usePage } from '@inertiajs/react';

type Flash = { success?: string | null; warning?: string | null };

/** Mensagens de uma única exibição vindas do servidor (sucesso e avisos de segurança). */
export default function FlashMessages() {
    const flash = (usePage().props as { flash?: Flash }).flash;
    if (!flash?.success && !flash?.warning) {
        return null;
    }

    return (
        <div className="mx-auto max-w-7xl space-y-2 px-4 pt-4 sm:px-6 lg:px-8" aria-live="polite">
            {flash.warning && <p role="alert" className="rounded-2xl border border-amber-300 bg-amber-50 px-5 py-3 text-sm font-medium text-amber-900">{flash.warning}</p>}
            {flash.success && <p role="status" className="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-3 text-sm font-medium text-emerald-900">{flash.success}</p>}
        </div>
    );
}
