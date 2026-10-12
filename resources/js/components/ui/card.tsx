import type { HTMLAttributes } from 'react';
import { cn } from '@/lib/cn';

export function Card({ className, ...props }: HTMLAttributes<HTMLDivElement>) {
    return <section className={cn('surface-shadow rounded-2xl border border-line bg-surface/95 p-5', className)} {...props} />;
}
