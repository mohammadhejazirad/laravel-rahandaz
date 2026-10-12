import type { HTMLAttributes } from 'react';
import { cn } from '@/lib/cn';

type AlertProps = HTMLAttributes<HTMLDivElement> & {
    tone?: 'warning' | 'danger' | 'neutral' | 'info';
};

const tones = {
    warning: 'border-warning/40 bg-surface text-warning',
    danger: 'border-danger/40 bg-surface text-danger',
    info: 'border-info/40 bg-surface text-info',
    neutral: 'border-line bg-surface-muted text-ink',
};

export function Alert({ tone = 'neutral', className, ...props }: AlertProps) {
    return <div role="status" className={cn('rounded-lg border px-3 py-2 text-sm', tones[tone], className)} {...props} />;
}
