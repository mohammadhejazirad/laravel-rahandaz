import type { SelectHTMLAttributes } from 'react';
import { cn } from '@/lib/cn';
type Props = SelectHTMLAttributes<HTMLSelectElement> & { label: string; error?: string };
export function Select({ label, error, id, name, className, children, ...props }: Props) {
    const fieldId = id ?? name;
    return <label htmlFor={fieldId} className="block text-sm"><span className="mb-1.5 block font-medium">{label}</span><select id={fieldId} name={name} aria-invalid={error ? true : undefined} aria-describedby={error ? `${fieldId}-error` : undefined} className={cn('min-h-11 w-full max-w-full rounded-xl border border-line bg-surface px-3 text-base text-ink', className)} {...props}>{children}</select>{error ? <span id={`${fieldId}-error`} role="alert" className="mt-2 block text-danger">{error}</span> : null}</label>;
}
