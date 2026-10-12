import type { HTMLAttributes } from 'react';
import { cn } from '@/lib/cn';

export function Card({ className, ...props }: HTMLAttributes<HTMLDivElement>) {
    return <section data-slot="card" className={cn('min-w-0 max-w-full rounded-2xl border border-line bg-surface/96 p-4 shadow-[0_1px_2px_color-mix(in_srgb,var(--color-ink)_4%,transparent)] sm:p-5', className)} {...props} />;
}
