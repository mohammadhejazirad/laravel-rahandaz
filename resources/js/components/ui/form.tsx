import { useState, type FormHTMLAttributes } from 'react';
import { FormErrors } from './form-context';
import { validateField } from '@/lib/form-validation';

export function Form({ onSubmit, children, ...props }: FormHTMLAttributes<HTMLFormElement>) {
    const [errors, setErrors] = useState<Record<string, string>>({});
    return <FormErrors.Provider value={errors}><form {...props} noValidate onChangeCapture={event => {
        const field = event.target;
        if (field instanceof HTMLInputElement || field instanceof HTMLSelectElement || field instanceof HTMLTextAreaElement) {
            setErrors(previous => { const next = { ...previous }; delete next[field.name || field.id]; return next; });
        }
    }} onSubmit={event => {
        const next: Record<string, string> = {};
        let first: HTMLElement | null = null;
        for (const field of event.currentTarget.querySelectorAll<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>('input,select,textarea')) {
            if (field.disabled || ('readOnly' in field && field.readOnly) || field.type === 'hidden' || field.type === 'submit') continue;
            const input = field instanceof HTMLInputElement ? field : null;
            const error = validateField({ value: input?.type === 'checkbox' ? (input.checked ? 'yes' : '') : field.value,
                isRequired: field.getAttribute('aria-required') === 'true', type: field.type,
                minLength: 'minLength' in field ? field.minLength : undefined, maxLength: 'maxLength' in field ? field.maxLength : undefined,
                pattern: input?.pattern,
            });
            if (error) { next[field.name || field.id] = error; first ??= field; }
        }
        setErrors(next);
        if (first) { event.preventDefault(); first.focus(); return; }
        onSubmit?.(event);
    }}>{children}</form></FormErrors.Provider>;
}
