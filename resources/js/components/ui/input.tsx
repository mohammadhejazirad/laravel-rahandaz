import type { InputHTMLAttributes } from 'react';
import { cn } from '@/lib/cn';

type InputProps = InputHTMLAttributes<HTMLInputElement> & {
    label: string;
    error?: string;
};

export function Input({ label, error, id, className, ...props }: InputProps) {
    const inputId = id ?? props.name;
    const errorId = error ? `${inputId}-error` : undefined;

    return (
        <label className="block text-sm" htmlFor={inputId}>
            <span className="mb-1.5 block font-medium text-ink">{label}</span>
            <input
                id={inputId}
                aria-invalid={error ? true : undefined}
                aria-describedby={errorId}
                className={cn(
                    'w-full rounded-lg border border-line bg-surface px-3 py-2 text-ink outline-none placeholder:text-muted',
                    error ? 'border-danger' : '',
                    className,
                )}
                {...props}
            />
            {error ? (
                <span id={errorId} className="mt-1 block text-danger">
                    {error}
                </span>
            ) : null}
        </label>
    );
}
