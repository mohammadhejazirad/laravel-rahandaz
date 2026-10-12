import type { HTMLAttributes } from 'react';
import { cn } from '@/lib/cn';

type AlertProps = HTMLAttributes<HTMLDivElement> & {
    tone?: 'warning' | 'danger' | 'neutral' | 'info';
};

const tones = {
    warning: 'border-warning/35 bg-warning-soft text-warning',
    danger: 'border-danger/35 bg-danger-soft text-danger',
    info: 'border-info/35 bg-info-soft text-info',
    neutral: 'border-line bg-surface-muted text-ink',
};

export function Alert({ tone = 'neutral', className, ...props }: AlertProps) {
    return <div role={tone === 'danger' ? 'alert' : 'status'} className={cn('rounded-xl border px-4 py-3 text-sm', tones[tone], className)} {...props} />;
}
