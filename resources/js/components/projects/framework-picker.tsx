import { useContext } from 'react';
import { FrameworkIcon } from './framework-icon';
import { frameworkCatalog, frameworkGroups } from './framework-catalog';
import { FormErrors } from '@/components/ui/form-context';
import { RadioCard } from '@/components/ui/radio-card';

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
            <legend id={labelId} className="mb-6 text-sm font-medium text-ink">
                {label}
                {isRequired ? <span className="ms-1 text-danger" aria-hidden="true">*</span> : null}
            </legend>
            <div className="flex flex-col gap-6">
                {groups.map(group => (
                    <div key={group.id} className="flex min-w-0 flex-col gap-6">
                        <p id={`${name}-group-${group.id}`} dir="ltr" className="m-0 font-mono text-sm font-semibold leading-6 text-ink">{group.label}</p>
                        <div role="group" aria-labelledby={`${name}-group-${group.id}`} className="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-5 xl:grid-cols-7">
                            {group.items.map(item => {
                                const selected = value === item.id;
                                return (
                                    <RadioCard key={item.id} name={name} value={item.id} checked={selected} isRequired={isRequired} disabled={disabled} layout="tile" className={`aspect-square min-h-20 gap-1 p-1.5 ${selected ? 'bg-accent-soft' : 'bg-surface'}`} onChange={() => onChange(item.id)}>
                                        <FrameworkIcon icon={item.icon} className="size-6" />
                                        {suggested === item.id ? <span className="rounded-md bg-surface px-1 text-[10px] leading-4 text-muted">پیشنهاد</span> : null}
                                        <span dir="ltr" className="max-w-full font-mono text-xs font-semibold leading-4 text-ink">{item.name}</span>
                                        <span className="line-clamp-2 text-[11px] leading-4 text-muted">{item.description}</span>
                                    </RadioCard>
                                );
                            })}
                        </div>
                    </div>
                ))}
            </div>
            {error ? <span id={errorId} role="alert" className="mt-2 block text-sm text-danger">{error}</span> : null}
        </fieldset>
    );
}
