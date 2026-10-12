import type { HTMLAttributes } from 'react';
import { cn } from '@/lib/cn';

type AlertProps = HTMLAttributes<HTMLDivElement> & {
    tone?: 'warning' | 'danger' | 'neutral' | 'info';
};

const tones = {
    warning: 'border-warning/30 bg-warning-soft/75 text-warning',
    danger: 'border-danger/30 bg-danger-soft/75 text-danger',
    info: 'border-info/30 bg-info-soft/75 text-info',
    neutral: 'border-line bg-surface-muted/75 text-ink',
};

export function Alert({ tone = 'neutral', className, ...props }: AlertProps) {
    return <div data-slot="alert" role={tone === 'danger' ? 'alert' : 'status'} className={cn('min-w-0 max-w-full break-words rounded-2xl border px-4 py-3.5 text-sm leading-7 shadow-[0_1px_1px_color-mix(in_srgb,var(--color-ink)_3%,transparent)]', tones[tone], className)} {...props} />;
}
