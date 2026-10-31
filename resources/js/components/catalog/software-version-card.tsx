import { RadioCard } from '@/components/ui/radio-card';
import { useId, useState } from 'react';
import { Check } from 'lucide-react';
import { CatalogSoftwareIcon } from './catalog-icons';
import type { CatalogItem, SoftwarePackageResult, SoftwareState } from './software-types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Modal, ModalContent, ModalDescription, ModalFooter, ModalHeader, ModalTitle } from '@/components/ui/modal';
import { cn } from '@/lib/cn';

export function SoftwareVersionCard({ items, selected, onChange, disabled, inventory, installed }: {
    items: [string, CatalogItem][]; selected: string[]; onChange: (keys: string[]) => void; disabled?: boolean;
    inventory?: SoftwareState['inventory']; installed: SoftwarePackageResult[];
}) {
    const id = useId();
    const [open, setOpen] = useState(false);
    const [draft, setDraft] = useState('');
    const [custom, setCustom] = useState('');
    const [error, setError] = useState('');
    const first = items[0][1];
    const keys = items.map(([key]) => key);
    const chosen = items.filter(([key]) => selected.includes(key));
    const label = (item: CatalogItem) => item.version_label ?? item.version ?? 'نسخهٔ مخزن سیستم';
    const observations = items.map(([key, item]) => ({ key, item, current: inventory?.packages[key], previous: installed.find(row => row.package === key && row.verified) }));
    const present = observations.filter(row => row.current?.state === 'installed');
    const allAbsent = observations.every(row => row.current?.state === 'not_installed');
    function confirm() {
        const match = draft === 'custom' ? items.find(([, item]) => (item.version_label === custom.trim() || item.version === custom.trim()))?.[0] : draft;
        if (!match || !keys.includes(match)) { setError('این نسخه در کاتالوگ معتبر موجود نیست. یکی از نسخه‌های قابل نصب را انتخاب کنید.'); return; }
        onChange(selected.includes(match) ? selected : [...selected, match]); setOpen(false);
    }
    return <article className={cn('flex min-w-0 flex-col gap-4 rounded-2xl border bg-surface p-5', chosen.length ? 'border-accent ring-1 ring-accent/20' : 'border-line')}>
        <div className="flex items-start justify-between gap-3"><span className="grid size-11 shrink-0 place-items-center rounded-xl bg-accent-soft text-accent"><CatalogSoftwareIcon software={first.product ?? items[0][0]} category={first.category} /></span>{chosen.length > 0 && <span className="inline-flex items-center gap-1 rounded-full bg-accent-soft px-2 py-1 text-xs text-accent"><Check className="size-3" aria-hidden="true" />انتخاب‌شده</span>}</div>
        <div><h3 dir="ltr" className="break-words font-mono font-semibold">{first.product_name ?? first.name}</h3><p className="mt-2 text-sm leading-7 text-muted">{first.product_description ?? first.description}</p></div>
        <div className="space-y-1 rounded-xl bg-surface-muted p-3 text-xs leading-6">
            {present.length ? present.map(row => <p key={row.key} className="text-success">نصب است · نسخهٔ بسته <bdi dir="ltr" className="break-all font-mono">{row.current?.version}</bdi></p>) : <p className="text-muted">{allAbsent ? 'نصب نشده است' : 'وضعیت نصب قابل تأیید نیست'}</p>}
            {present.length === 0 && observations.some(row => row.previous) && <p className="text-muted">سابقهٔ نصب وجود دارد؛ وضعیت فعلی نیاز به بررسی دارد.</p>}
        </div>
        {chosen.length > 0 && <ul className="space-y-2" aria-label="نسخه‌های انتخاب‌شده">{chosen.map(([key, item]) => <li key={key} className="flex min-w-0 items-center justify-between gap-2 text-sm"><bdi dir="ltr" className="break-all font-mono">{label(item)}</bdi><Button type="button" variant="ghost" disabled={disabled} aria-label={`حذف ${item.name} از فهرست نصب`} onClick={() => onChange(selected.filter(value => value !== key))}>حذف</Button></li>)}</ul>}
        <Button type="button" variant={chosen.length ? 'secondary' : 'primary'} className="mt-auto w-full" disabled={disabled} onClick={() => { setDraft(items.find(([key]) => inventory?.packages[key]?.state !== 'installed')?.[0] ?? keys[0]); setCustom(''); setError(''); setOpen(true); }}>انتخاب نسخه و افزودن</Button>
        <Modal open={open} onOpenChange={setOpen}><ModalContent>
            <ModalHeader><ModalTitle className="flex items-center gap-2"><CatalogSoftwareIcon software={first.product ?? items[0][0]} category={first.category} />نصب <bdi dir="ltr">{first.product_name ?? first.name}</bdi></ModalTitle><ModalDescription>نسخه را انتخاب و به فهرست اضافه کنید. برای افزودن نسخهٔ دیگر دوباره این پنجره را باز کنید؛ نصب همهٔ انتخاب‌ها با دکمهٔ نهایی آغاز می‌شود.</ModalDescription></ModalHeader>
            <fieldset disabled={disabled} className="mt-5"><legend className="mb-3 text-sm font-semibold">نسخه‌های قابل نصب</legend><div className="grid grid-cols-2 gap-3">
                {items.map(([key, item]) => <RadioCard key={key} name={`${id}-version`} value={key} checked={draft === key} disabled={disabled} onChange={() => { setDraft(key); setError(''); }}><span className="min-w-0 text-sm"><bdi dir="ltr" className="break-words font-mono">{label(item)}</bdi>{inventory?.packages[key]?.state === 'installed' && <span className="mt-1 block text-xs text-success">نصب است · نسخهٔ بسته <bdi dir="ltr" className="break-all font-mono">{inventory.packages[key].version}</bdi></span>}</span></RadioCard>)}
            </div></fieldset>
            <details className="mt-4 rounded-xl border border-line p-3"><summary className="text-sm font-medium">ورود نسخهٔ دلخواه</summary><div className="mt-3 space-y-3"><RadioCard name={`${id}-version`} value="custom" checked={draft === 'custom'} disabled={disabled} onChange={() => setDraft('custom')}>استفاده از نسخهٔ واردشده</RadioCard><Input id={`${id}-custom`} label="نسخهٔ کامل" dir="ltr" className="font-mono" value={custom} onChange={event => { setCustom(event.target.value); setDraft('custom'); setError(''); }} help="فقط نسخه‌های موجود در کاتالوگ معتبر قابل نصب‌اند؛ نسخه یا نشانی دانلود خارج از کاتالوگ پذیرفته نمی‌شود." /></div></details>
            {error && <p role="alert" className="mt-3 text-sm text-danger">{error}</p>}
            <ModalFooter><Button type="button" variant="secondary" onClick={() => setOpen(false)}>انصراف</Button><Button type="button" disabled={disabled} onClick={confirm}>تأیید و افزودن به فهرست</Button></ModalFooter>
        </ModalContent></Modal>
    </article>;
}
