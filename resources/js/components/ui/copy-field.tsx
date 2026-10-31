import { useId, useRef, useState } from 'react';
import { Input } from './input';
import { AsyncButton } from './request-button';

export function CopyField({ value, label, copyLabel = 'کپی', help }: { value: string; label: string; copyLabel?: string; help?: string }) {
    const id = useId();
    const input = useRef<HTMLInputElement>(null);
    const [status, setStatus] = useState('');
    return <div className="min-w-0 space-y-2">
        <Input id={id} ref={input} readOnly dir="ltr" className="font-mono" label={label} value={value} help={help} onFocus={event => event.target.select()} />
        <div className="flex flex-wrap items-center gap-3"><AsyncButton variant="secondary" loadingLabel="در حال کپی…" action={async () => {
            setStatus('');
            try { await navigator.clipboard.writeText(value); setStatus('کپی شد.'); }
            catch { input.current?.focus(); input.current?.select(); setStatus('متن انتخاب شد؛ آن را دستی کپی کنید.'); }
        }}>{copyLabel}</AsyncButton><span role="status" className="text-sm text-muted">{status}</span></div>
    </div>;
}
