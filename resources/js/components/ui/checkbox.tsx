import { useContext, useId, type InputHTMLAttributes, type ReactNode } from 'react';
import { FormErrors } from './form-context';
import { cn } from '@/lib/cn';

type CheckboxProps = Omit<InputHTMLAttributes<HTMLInputElement>, 'required' | 'type' | 'children'> & {
    isRequired?: boolean;
    error?: string;
    children: ReactNode;
};

export function Checkbox({ children, isRequired, error: serverError, id, name, className, disabled, ...props }: CheckboxProps) {
    const generatedId = useId();
    const fieldId = id ?? name ?? generatedId;
    const errors = useContext(FormErrors);
    const error = errors[name ?? fieldId] ?? serverError;
    return <div className="min-w-0">
        <label htmlFor={fieldId} className={cn('flex min-h-11 min-w-0 cursor-pointer items-center gap-3 text-sm', disabled && 'cursor-not-allowed opacity-70', className)}>
            <input {...props} id={fieldId} name={name} type="checkbox" disabled={disabled} aria-required={isRequired || undefined} aria-invalid={error ? true : undefined} aria-describedby={error ? `${fieldId}-error` : undefined} className="size-5 shrink-0 rounded accent-accent" />
            {children}
        </label>
        {error && <span id={`${fieldId}-error`} role="alert" className="mt-1.5 block text-sm text-danger">{error}</span>}
    </div>;
}
