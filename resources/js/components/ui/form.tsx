import { useState, type FormHTMLAttributes } from 'react';
import { FormErrors } from './form-context';
import { validateField } from '@/lib/form-validation';

function fieldValue(field: HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement): string {
    if (field instanceof HTMLInputElement && field.type === 'checkbox') {
        return field.checked ? 'yes' : '';
    }

    return field.value;
}

export function Form({ onSubmit, children, ...props }: FormHTMLAttributes<HTMLFormElement>) {
    const [errors, setErrors] = useState<Record<string, string>>({});

    return (
        <FormErrors.Provider value={errors}>
            <form
                {...props}
                noValidate
                onChangeCapture={event => {
                    const field = event.target;
                    if (field instanceof HTMLInputElement || field instanceof HTMLSelectElement || field instanceof HTMLTextAreaElement) {
                        setErrors(previous => {
                            const next = { ...previous };
                            delete next[field.name || field.id];
                            return next;
                        });
                    }
                }}
                onSubmit={event => {
                    const next: Record<string, string> = {};
                    let first: HTMLElement | null = null;
                    const radios = new Map<string, HTMLInputElement[]>();

                    for (const field of event.currentTarget.querySelectorAll<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>('input,select,textarea')) {
                        if (field.disabled || ('readOnly' in field && field.readOnly) || field.type === 'hidden' || field.type === 'submit') {
                            continue;
                        }

                        if (field instanceof HTMLInputElement && field.type === 'radio') {
                            const key = field.name || field.id;
                            radios.set(key, [...(radios.get(key) ?? []), field]);
                            continue;
                        }

                        const error = validateField({
                            value: fieldValue(field),
                            isRequired: field.getAttribute('aria-required') === 'true',
                            type: field.type,
                            minLength: 'minLength' in field ? field.minLength : undefined,
                            maxLength: 'maxLength' in field ? field.maxLength : undefined,
                            pattern: field instanceof HTMLInputElement ? field.pattern : undefined,
                        });

                        if (error) {
                            next[field.name || field.id] = error;
                            first ??= field;
                        }
                    }

                    for (const [key, group] of radios) {
                        const checked = group.find(item => item.checked);
                        const error = validateField({
                            value: checked?.value ?? '',
                            isRequired: group.some(item => item.getAttribute('aria-required') === 'true'),
                        });

                        if (error) {
                            next[key] = error;
                            first ??= group[0];
                        }
                    }

                    setErrors(next);

                    if (first) {
                        event.preventDefault();
                        first.focus();
                        return;
                    }

                    onSubmit?.(event);
                }}
            >
                {children}
            </form>
        </FormErrors.Provider>
    );
}
