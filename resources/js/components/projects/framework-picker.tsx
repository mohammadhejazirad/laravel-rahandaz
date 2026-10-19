import { useContext } from 'react';
import { FrameworkIcon } from './framework-icon';
import { frameworkCatalog, frameworkGroups } from './framework-catalog';
import { FormErrors } from '@/components/ui/form-context';
import { cn } from '@/lib/cn';

export function FrameworkPicker({
    name,
    label,
    value,
    onChange,
    error: serverError,
    isRequired,
    allowed,
    suggested,
    disabled,
}: {
    name: string;
    label: string;
    value: string;
    onChange: (value: string) => void;
    error?: string;
    isRequired?: boolean;
    allowed?: readonly string[];
    suggested?: string;
    disabled?: boolean;
}) {
    const clientErrors = useContext(FormErrors);
    const error = clientErrors[name] ?? serverError;
    const errorId = error ? `${name}-error` : undefined;
    const labelId = `${name}-label`;
    const visible = allowed ? frameworkCatalog.filter(item => allowed.includes(item.id)) : frameworkCatalog;
    const groups = frameworkGroups
        .map(group => ({ ...group, items: visible.filter(item => item.group === group.id) }))
        .filter(group => group.items.length > 0);

    return (
        <fieldset aria-invalid={error ? true : undefined} aria-describedby={errorId} disabled={disabled} className="m-0 w-full min-w-0 border-0 p-0 [min-inline-size:0]">
            <legend id={labelId} className="mb-3 text-sm font-medium text-ink">
                {label}
                {isRequired ? <span className="ms-1 text-danger" aria-hidden="true">*</span> : null}
            </legend>
            <div className="space-y-5">
                {groups.map(group => (
                    <fieldset key={group.id} className="m-0 min-w-0 border-0 p-0 [min-inline-size:0]">
                        <legend dir="ltr" className="mb-2 font-mono text-sm font-semibold text-ink">{group.label}</legend>
                        <div className="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6">
                            {group.items.map(item => {
                                const selected = value === item.id;
                                return (
                                    <label
                                        key={item.id}
                                        className={cn(
                                            'flex aspect-square min-h-28 cursor-pointer flex-col items-center justify-center gap-1.5 rounded-xl border bg-surface p-2 text-center transition-[border-color,background-color,box-shadow] focus-within:ring-2 focus-within:ring-accent',
                                            selected ? 'border-accent bg-accent-soft' : 'border-line hover:border-accent',
                                            disabled ? 'cursor-not-allowed opacity-70' : '',
                                        )}
                                    >
                                        <input
                                            type="radio"
                                            name={name}
                                            value={item.id}
                                            checked={selected}
                                            aria-required={isRequired || undefined}
                                            disabled={disabled}
                                            className="sr-only"
                                            onChange={() => onChange(item.id)}
                                        />
                                        <FrameworkIcon icon={item.icon} />
                                        {suggested === item.id ? <span className="rounded-md bg-surface px-1.5 text-[11px] leading-5 text-muted">پیشنهاد</span> : null}
                                        <span dir="ltr" className="max-w-full font-mono text-sm font-semibold leading-5 text-ink">{item.name}</span>
                                        <span className="line-clamp-2 text-xs leading-4 text-muted">{item.description}</span>
                                    </label>
                                );
                            })}
                        </div>
                    </fieldset>
                ))}
            </div>
            {error ? <span id={errorId} role="alert" className="mt-2 block text-sm text-danger">{error}</span> : null}
        </fieldset>
    );
}
