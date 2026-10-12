import type { InputHTMLAttributes } from 'react';
import { cn } from '@/lib/cn';

type InputProps = InputHTMLAttributes<HTMLInputElement> & {
    label: string;
    error?: string;
    help?: string;
};

export function Input({ label, error, help, id, className, required, ...props }: InputProps) {
    const inputId = id ?? props.name;
    const errorId = error ? `${inputId}-error` : undefined;
    const helpId = help ? `${inputId}-help` : undefined;
    const describedBy = [errorId, helpId].filter(Boolean).join(' ') || undefined;

    return (
        <label className="block text-sm" htmlFor={inputId}>
            <span className="mb-1.5 block font-medium text-ink">
                {label}
                {required ? <span className="ms-1 text-danger" aria-hidden="true">*</span> : null}
            </span>
            <input
                id={inputId}
                aria-invalid={error ? true : undefined}
                aria-describedby={describedBy}
                required={required}
                className={cn(
                    'min-h-11 w-full rounded-xl border border-line bg-surface px-3.5 py-2 text-ink shadow-sm outline-none transition-[border-color,box-shadow] placeholder:text-muted focus:border-accent focus:ring-4 focus:ring-accent/10',
                    error ? 'border-danger focus:border-danger focus:ring-danger/10' : '',
                    className,
                )}
                {...props}
            />
            {help ? <span id={helpId} className="mt-1.5 block text-xs text-muted">{help}</span> : null}
            {error ? (
                <span id={errorId} role="alert" className="mt-1.5 block text-sm text-danger">
                    {error}
                </span>
            ) : null}
        </label>
    );
}
