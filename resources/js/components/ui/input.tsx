import { useContext, type Ref, type InputHTMLAttributes } from 'react';
import { FormErrors } from './form-context';
import { cn } from '@/lib/cn';

type InputProps = Omit<InputHTMLAttributes<HTMLInputElement>, 'required'> & {
    ref?: Ref<HTMLInputElement>;
    isRequired?: boolean;
    label: string;
    error?: string;
    help?: string;
};

export function Input({ label, error: serverError, help, id, className, isRequired, ...props }: InputProps) {
    const clientErrors = useContext(FormErrors);
    const error = clientErrors[props.name ?? id ?? ''] ?? serverError;
    const inputId = id ?? props.name;
    const errorId = error ? `${inputId}-error` : undefined;
    const helpId = help ? `${inputId}-help` : undefined;
    const describedBy = [errorId, helpId].filter(Boolean).join(' ') || undefined;

    return (
        <label data-slot="field" className="block text-sm" htmlFor={inputId}>
            <span className="mb-1.5 block font-medium text-ink">
                {label}
                {isRequired ? <span className="ms-1 text-danger" aria-hidden="true">*</span> : null}
            </span>
            <input
                data-slot="input"
                id={inputId}
                aria-invalid={error ? true : undefined}
                aria-describedby={describedBy}
                aria-required={isRequired || undefined}
                className={cn(
                    'min-h-11 w-full max-w-full rounded-xl border border-line bg-surface px-3.5 py-2 text-base text-ink shadow-sm outline-none transition-[border-color,box-shadow,background-color] placeholder:text-muted/75 hover:border-accent/35 focus:border-accent focus:ring-4 focus:ring-accent/10 disabled:cursor-not-allowed disabled:bg-surface-muted disabled:opacity-70',
                    error ? 'border-danger focus:border-danger focus:ring-danger/10' : '',
                    className,
                )}
                {...props}
            />
            {help ? <span id={helpId} className="mt-1.5 block break-words text-xs text-muted">{help}</span> : null}
            {error ? (
                <span id={errorId} role="alert" className="mt-1.5 block break-words text-sm text-danger">
                    {error}
                </span>
            ) : null}
        </label>
    );
}
