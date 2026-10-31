import { Children, Fragment, isValidElement, useContext, useId, useRef, useState, type KeyboardEvent, type ReactNode, type SelectHTMLAttributes } from 'react';
import { Popover } from 'radix-ui';
import { Check, ChevronDown, Search } from 'lucide-react';
import { FormErrors } from './form-context';
import { Button } from './button';
import { cn } from '@/lib/cn';
import { matchesSelectSearch } from '@/lib/select-search';

type Props = Omit<SelectHTMLAttributes<HTMLSelectElement>, 'required' | 'multiple' | 'size'> & {
    isRequired?: boolean;
    label: string;
    error?: string;
    /** Dynamic lists should opt in even before they reach eight options. */
    searchable?: boolean;
};
type Option = { value: string; label: string; disabled: boolean };

function optionText(node: ReactNode): string {
    return Children.toArray(node).map(child => isValidElement<{ children?: ReactNode }>(child) ? optionText(child.props.children) : String(child)).join('');
}

function readOptions(children: ReactNode, groupDisabled = false): Option[] {
    return Children.toArray(children).flatMap(child => {
        if (!isValidElement<{ value?: string | number; children?: ReactNode; disabled?: boolean }>(child)) return [];
        if (child.type === Fragment || child.type === 'optgroup') return readOptions(child.props.children, groupDisabled || !!child.props.disabled);
        if (child.type !== 'option') return [];
        const label = optionText(child.props.children);
        return [{ value: String(child.props.value ?? label), label, disabled: groupDisabled || !!child.props.disabled }];
    });
}

export function SelectBox({ label, error: serverError, isRequired, searchable, id, name, className, children, value, defaultValue, disabled, onChange, onFocus, ...props }: Props) {
    const generatedId = useId();
    const fieldId = id ?? name ?? generatedId;
    const native = useRef<HTMLSelectElement>(null);
    const trigger = useRef<HTMLButtonElement>(null);
    const search = useRef<HTMLInputElement>(null);
    const list = useRef<HTMLDivElement>(null);
    const tabbing = useRef(false);
    const typeahead = useRef({ text: '', time: 0 });
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [active, setActive] = useState(-1);
    const options = readOptions(children);
    const [internalValue, setInternalValue] = useState(String(defaultValue ?? options[0]?.value ?? ''));
    const selectedValue = String(value ?? internalValue);
    const selected = options.find(option => option.value === selectedValue);
    const hasSearch = searchable === true || options.length >= 8;
    const filtered = options.filter(option => matchesSelectSearch(option.label, query));
    const errors = useContext(FormErrors);
    const error = errors[name ?? fieldId] ?? serverError;
    const describedBy = [props['aria-describedby'], error ? `${fieldId}-error` : undefined].filter(Boolean).join(' ') || undefined;
    const activeId = active >= 0 && filtered[active] && !filtered[active].disabled ? `${fieldId}-option-${active}` : undefined;

    function choose(option: Option) {
        if (option.disabled || disabled || !native.current) return;
        native.current.value = option.value;
        native.current.dispatchEvent(new Event('change', { bubbles: true }));
        setOpen(false);
    }

    function navigate(event: KeyboardEvent) {
        if (event.nativeEvent.isComposing) return;
        const enabled = filtered.flatMap((option, index) => option.disabled ? [] : [index]);
        let next: number | undefined;
        if (event.key === 'ArrowDown') next = enabled[(enabled.indexOf(active) + 1) % enabled.length];
        else if (event.key === 'ArrowUp') next = enabled[(enabled.indexOf(active) - 1 + enabled.length) % enabled.length];
        else if (event.key === 'Home' && !hasSearch) next = enabled[0];
        else if (event.key === 'End' && !hasSearch) next = enabled.at(-1);
        else if (event.key === 'Enter') {
            event.preventDefault();
            if (active >= 0 && filtered[active]) choose(filtered[active]);
            return;
        } else if (event.key === 'Tab') {
            tabbing.current = true;
            trigger.current?.focus();
            setOpen(false);
            return;
        } else if (!hasSearch && event.key.length === 1 && !event.ctrlKey && !event.metaKey && !event.altKey) {
            const now = Date.now();
            const text = (now - typeahead.current.time < 700 ? typeahead.current.text : '') + event.key;
            typeahead.current = { text, time: now };
            next = enabled.find(index => matchesSelectSearch(filtered[index].label, text));
        }
        else return;
        event.preventDefault();
        if (next !== undefined) {
            setActive(next);
            document.getElementById(`${fieldId}-option-${next}`)?.scrollIntoView({ block: 'nearest' });
        }
    }

    return <div className="min-w-0 text-sm">
        <label id={`${fieldId}-label`} htmlFor={`${fieldId}-trigger`} className="mb-1.5 block font-medium">{label}</label>
        {/* A real select preserves FormData, React change events and shared validation. */}
        <select {...props} ref={native} id={fieldId} name={name} value={value} defaultValue={defaultValue} disabled={disabled} tabIndex={-1} aria-hidden="true" aria-required={isRequired || undefined} className="sr-only" onFocus={event => { trigger.current?.focus(); onFocus?.(event); }} onChange={event => { setInternalValue(event.target.value); onChange?.(event); }}>{children}</select>
        <Popover.Root open={open && !disabled} onOpenChange={next => {
            setOpen(next);
            if (next) {
                setQuery('');
                setActive(options.findIndex(option => option.value === selectedValue && !option.disabled));
            }
        }}>
            <Popover.Trigger asChild>
                <Button ref={trigger} id={`${fieldId}-trigger`} variant="secondary" role="combobox" aria-haspopup="listbox" aria-expanded={open && !disabled} aria-controls={`${fieldId}-list`} aria-labelledby={`${fieldId}-label ${fieldId}-value`} aria-required={isRequired || undefined} aria-invalid={error ? true : undefined} aria-describedby={describedBy} disabled={disabled} dir={props.dir ?? 'rtl'} className={cn('min-h-11 w-full max-w-full justify-between gap-3 rounded-xl border border-line bg-surface px-3 text-start text-base font-normal text-ink hover:bg-surface-muted focus-visible:ring-2 focus-visible:ring-accent', error && 'border-danger', className)} onKeyDown={event => {
                    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') { event.preventDefault(); setQuery(''); setActive(options.findIndex(option => option.value === selectedValue && !option.disabled)); setOpen(true); }
                }}>
                    <span id={`${fieldId}-value`} className="min-w-0 truncate">{selected?.label ?? 'انتخاب کنید'}</span>
                    <span aria-hidden="true" className="grid size-7 shrink-0 place-items-center rounded-lg bg-surface-muted text-muted"><ChevronDown className={cn('size-4 motion-safe:transition-transform', open && 'rotate-180')} /></span>
                </Button>
            </Popover.Trigger>
            <Popover.Portal>
                <Popover.Content role="presentation" dir="rtl" align="start" sideOffset={6} collisionPadding={12} onCloseAutoFocus={event => { if (tabbing.current) { event.preventDefault(); tabbing.current = false; } }} onOpenAutoFocus={event => { event.preventDefault(); (hasSearch ? search.current : list.current)?.focus(); }} className="surface-shadow z-[90] flex max-h-[min(22rem,var(--radix-popover-content-available-height))] w-[var(--radix-popover-trigger-width)] max-w-[calc(100vw-1.5rem)] flex-col overflow-hidden rounded-xl border border-line bg-surface-raised p-1.5 text-ink outline-none">
                    {hasSearch ? <div className="relative mb-1.5 shrink-0 border-b border-line pb-1.5">
                        <Search aria-hidden="true" className="pointer-events-none absolute start-3 top-3 size-4 text-muted" />
                        <input ref={search} type="search" autoComplete="off" aria-label={`جست‌وجو در ${label}`} role="combobox" aria-expanded="true" aria-controls={`${fieldId}-list`} aria-activedescendant={activeId} value={query} onChange={event => { setQuery(event.target.value); setActive(options.filter(option => matchesSelectSearch(option.label, event.target.value)).findIndex(option => !option.disabled)); }} onKeyDown={navigate} className="min-h-10 w-full rounded-lg bg-surface py-2 pe-3 ps-9 text-base outline-none focus-visible:ring-2 focus-visible:ring-accent" placeholder="جست‌وجو…" />
                    </div> : null}
                    <div ref={list} id={`${fieldId}-list`} role="listbox" aria-labelledby={`${fieldId}-label`} aria-activedescendant={!hasSearch ? activeId : undefined} tabIndex={hasSearch ? -1 : 0} onKeyDown={!hasSearch ? navigate : undefined} className="min-h-0 overflow-y-auto overscroll-contain outline-none">
                        {filtered.map((option, index) => <Button key={option.value} id={`${fieldId}-option-${index}`} role="option" aria-selected={option.value === selectedValue} aria-disabled={option.disabled || undefined} disabled={option.disabled} tabIndex={-1} variant="ghost" onMouseDown={event => event.preventDefault()} onPointerMove={() => { if (!option.disabled) setActive(index); }} onClick={() => choose(option)} className={cn('mb-0.5 h-auto min-h-11 w-full justify-between gap-2 whitespace-normal rounded-lg px-3 py-2 text-start font-normal', index === active && 'bg-surface-muted', option.value === selectedValue && 'text-accent')}>
                            <span className="min-w-0 break-words">{option.label}</span>{option.value === selectedValue ? <Check aria-hidden="true" className="size-4 shrink-0" /> : null}
                        </Button>)}
                    </div>
                    {!filtered.length ? <p role="status" className="px-3 py-5 text-center text-sm text-muted">گزینه‌ای پیدا نشد.</p> : null}
                </Popover.Content>
            </Popover.Portal>
        </Popover.Root>
        {error ? <span id={`${fieldId}-error`} role="alert" className="mt-2 block text-danger">{error}</span> : null}
    </div>;
}

export const Select = SelectBox;
