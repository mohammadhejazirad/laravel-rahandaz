import { usePage } from '@inertiajs/react';
import { ChevronDown, Plus } from 'lucide-react';
import type { ReactNode } from 'react';

/** Keep write forms out of the scanning path; server validation reopens the form. */
export function ActionPanel({ title, description, children }: { title: string; description?: string; children: ReactNode }) {
    const { errors } = usePage().props;
    return <details id="new-request" open={Object.keys(errors ?? {}).length > 0 || undefined} className="group rounded-2xl border border-line bg-surface">
        <summary className="flex min-h-16 list-none flex-wrap items-center gap-3 rounded-2xl p-4 hover:bg-surface-muted focus-visible:outline-accent sm:px-5 [&::-webkit-details-marker]:hidden"><span className="grid size-9 shrink-0 place-items-center rounded-xl bg-accent-soft text-accent"><Plus className="size-4" aria-hidden="true" /></span><span className="min-w-0 flex-1"><span className="block text-sm font-semibold">{title}</span>{description ? <span className="mt-1 block text-xs leading-6 text-muted">{description}</span> : null}</span><ChevronDown className="size-4 text-muted group-open:rotate-180" aria-hidden="true" /></summary>
        <div className="border-t border-line p-4 sm:p-5">{children}</div>
    </details>;
}
