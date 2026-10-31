import { Link, usePage } from '@inertiajs/react';
import { useId, useRef, useState } from 'react';
import { Check, Layers, Search } from 'lucide-react';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/cn';
import { isSoftwarePending } from '@/lib/software-jobs';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Modal, ModalContent, ModalDescription, ModalFooter, ModalHeader, ModalTitle } from '@/components/ui/modal';
import { StatusBadge } from '@/components/ui/status-badge';
import type { SharedProps } from '@/types';

export interface SoftwarePackageResult {
    package: string; version: string | null; verified: boolean; changed: boolean;
    status: string; phase?: string; phase_label?: string; message?: string | null; executable_path?: string;
}
export interface SoftwareJob {
    uuid: string; status: string; operation?: string; message: string | null;
    packages: SoftwarePackageResult[];
    summary?: { total: number; succeeded: number; failed: number; skipped: number };
}
export interface CatalogItem {
    name: string; description: string; category?: string;
    product?: string | null; product_name?: string | null; product_description?: string | null; version_label?: string | null;
    version?: string | null;
    upgradeable?: boolean; upgrade_note?: string | null;
}
export interface SoftwareState {
    available: boolean; can_request?: boolean;
    catalog: Record<string, CatalogItem>;
    categories?: Record<string, string>;
    inventory?: { checked_at: string; packages: Record<string, { state: 'installed' | 'not_installed' | 'unknown'; version: string | null }> } | null;
    installed?: SoftwarePackageResult[];
    job?: SoftwareJob | null; active?: SoftwareJob | null; request_key: string;
}
const defaultCategories: Record<string, string> = { web: 'وب‌سرورها', runtime: 'زبان‌ها و runtimeها', database: 'دیتابیس‌ها', cache: 'کش', utility: 'ابزارهای کاربردی', antivirus: 'آنتی‌ویروس‌ها', security: 'ابزارهای امنیتی', process: 'مدیریت پردازش' };
export function SoftwarePicker({ catalog, categories = defaultCategories, selected, onChange, disabled, inventory, installed = [], initialCategory }: {
    catalog: SoftwareState['catalog']; categories?: Record<string, string>; selected: string[]; onChange: (keys: string[]) => void; disabled?: boolean;
    inventory?: SoftwareState['inventory']; installed?: SoftwarePackageResult[]; initialCategory?: string;
}) {
    const id = useId();
    const [category, setCategory] = useState(initialCategory && initialCategory in categories ? initialCategory : 'all');
    const [search, setSearch] = useState('');
    const groups = Object.entries(categories).filter(([key]) => Object.values(catalog).some(item => (item.category ?? 'utility') === key));
    const rows = rowsFor(Object.entries(catalog).filter(([, item]) => (category === 'all' || (item.category ?? 'utility') === category) && `${item.name} ${item.product_name ?? ''} ${item.description}`.toLowerCase().includes(search.trim().toLowerCase())));
    const tabs = [['all', 'همهٔ نرم‌افزارها'], ...groups];
    return <div className="space-y-5">
        <div className="flex flex-wrap gap-2 rounded-2xl bg-surface-muted p-2" role="tablist" aria-label="دسته‌بندی نرم‌افزارها">
            {tabs.map(([key, label], index) => <button key={key} id={`${id}-tab-${key}`} type="button" role="tab" aria-selected={category === key} aria-controls={`${id}-panel`} tabIndex={category === key ? 0 : -1} onClick={() => setCategory(key)} onKeyDown={event => {
                const next = event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1 : event.key === 'ArrowLeft' ? (index + 1) % tabs.length : event.key === 'ArrowRight' ? (index - 1 + tabs.length) % tabs.length : null;
                if (next === null) return;
                event.preventDefault(); setCategory(tabs[next][0]); document.getElementById(`${id}-tab-${tabs[next][0]}`)?.focus();
            }} className={cn('min-h-11 rounded-xl px-3 py-2 text-sm font-medium transition-colors', category === key ? 'bg-surface text-accent shadow-sm' : 'text-muted hover:bg-surface/60 hover:text-ink')}>{label}</button>)}
        </div>
        <div className="relative max-w-md"><Search aria-hidden="true" className="pointer-events-none absolute start-3 top-10 size-4 text-muted" /><Input label="جست‌وجوی نرم‌افزار" id={`${id}-search`} value={search} onChange={event => setSearch(event.target.value)} className="ps-10" onKeyDown={event => { if (event.key === 'Enter') event.preventDefault(); }} placeholder="نام نرم‌افزار یا کاربرد آن…" /></div>
        <div id={`${id}-panel`} role="tabpanel" aria-labelledby={`${id}-tab-${category}`} tabIndex={0} className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            {rows.map(row => <PackageChoice key={row.product} items={row.items} selected={selected} onChange={onChange} disabled={disabled} inventory={inventory} installed={installed} />)}
            {rows.length === 0 && <p className="col-span-full rounded-xl border border-dashed border-line p-8 text-center text-sm text-muted">نرم‌افزاری با این جست‌وجو پیدا نشد.</p>}
        </div>
    </div>;
}
function rowsFor(items: [string, CatalogItem][]) {
    const rows = new Map<string, [string, CatalogItem][]>();
    for (const [key, item] of items) {
        const product = item.product ?? key;
        rows.set(product, [...(rows.get(product) ?? []), [key, item]]);
    }
    return Array.from(rows, ([product, items]) => ({ product, items }));
}
function PackageChoice({ items, selected, onChange, disabled, inventory, installed }: {
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
        <div className="flex items-start justify-between gap-3"><span className="grid size-11 shrink-0 place-items-center rounded-xl bg-accent-soft text-accent"><Layers className="size-5" aria-hidden="true" /></span>{chosen.length > 0 && <span className="inline-flex items-center gap-1 rounded-full bg-accent-soft px-2 py-1 text-xs text-accent"><Check className="size-3" aria-hidden="true" />انتخاب‌شده</span>}</div>
        <div><h3 dir="ltr" className="break-words font-mono font-semibold">{first.product_name ?? first.name}</h3><p className="mt-2 text-sm leading-7 text-muted">{first.product_description ?? first.description}</p></div>
        <div className="space-y-1 rounded-xl bg-surface-muted p-3 text-xs leading-6">
            {present.length ? present.map(row => <p key={row.key} className="text-success">نصب است · نسخهٔ بسته <bdi dir="ltr" className="break-all font-mono">{row.current?.version}</bdi></p>) : <p className="text-muted">{allAbsent ? 'نصب نشده است' : 'وضعیت نصب قابل تأیید نیست'}</p>}
            {present.length === 0 && observations.some(row => row.previous) && <p className="text-muted">سابقهٔ نصب وجود دارد؛ وضعیت فعلی نیاز به بررسی دارد.</p>}
        </div>
        {chosen.length > 0 && <ul className="space-y-2" aria-label="نسخه‌های انتخاب‌شده">{chosen.map(([key, item]) => <li key={key} className="flex min-w-0 items-center justify-between gap-2 text-sm"><bdi dir="ltr" className="break-all font-mono">{label(item)}</bdi><Button type="button" variant="ghost" disabled={disabled} aria-label={`حذف ${item.name} از فهرست نصب`} onClick={() => onChange(selected.filter(value => value !== key))}>حذف</Button></li>)}</ul>}
        <Button type="button" variant={chosen.length ? 'secondary' : 'primary'} className="mt-auto w-full" disabled={disabled} onClick={() => { setDraft(items.find(([key]) => inventory?.packages[key]?.state !== 'installed')?.[0] ?? keys[0]); setCustom(''); setError(''); setOpen(true); }}>انتخاب نسخه و افزودن</Button>
        <Modal open={open} onOpenChange={setOpen}><ModalContent>
            <ModalHeader><ModalTitle>نصب <bdi dir="ltr">{first.product_name ?? first.name}</bdi></ModalTitle><ModalDescription>نسخه را انتخاب و به فهرست اضافه کنید. برای افزودن نسخهٔ دیگر دوباره این پنجره را باز کنید؛ نصب همهٔ انتخاب‌ها با دکمهٔ نهایی آغاز می‌شود.</ModalDescription></ModalHeader>
            <fieldset disabled={disabled} className="mt-5"><legend className="mb-3 text-sm font-semibold">نسخه‌های قابل نصب</legend><div className="grid grid-cols-2 gap-3">
                {items.map(([key, item]) => <label key={key} className={cn('flex min-w-0 cursor-pointer items-start gap-2 rounded-xl border p-3 focus-within:ring-2 focus-within:ring-accent', draft === key ? 'border-accent bg-accent-soft' : 'border-line')}><input type="radio" name={`${id}-version`} value={key} checked={draft === key} onChange={() => { setDraft(key); setError(''); }} className="mt-1 size-4 shrink-0 accent-accent" /><span className="min-w-0 text-sm"><bdi dir="ltr" className="break-words font-mono">{label(item)}</bdi>{inventory?.packages[key]?.state === 'installed' && <span className="mt-1 block text-xs text-success">نصب است · نسخهٔ بسته <bdi dir="ltr" className="break-all font-mono">{inventory.packages[key].version}</bdi></span>}</span></label>)}
            </div></fieldset>
            <details className="mt-4 rounded-xl border border-line p-3"><summary className="text-sm font-medium">ورود نسخهٔ دلخواه</summary><div className="mt-3 space-y-3"><label className="flex items-center gap-2 text-sm"><input type="radio" name={`${id}-version`} checked={draft === 'custom'} onChange={() => setDraft('custom')} className="size-4 accent-accent" />استفاده از نسخهٔ واردشده</label><Input id={`${id}-custom`} label="نسخهٔ کامل" dir="ltr" className="font-mono" value={custom} onChange={event => { setCustom(event.target.value); setDraft('custom'); setError(''); }} help="فقط نسخه‌های موجود در کاتالوگ معتبر قابل نصب‌اند؛ نسخه یا نشانی دانلود خارج از کاتالوگ پذیرفته نمی‌شود." /></div></details>
            {error && <p role="alert" className="mt-3 text-sm text-danger">{error}</p>}
            <ModalFooter><Button type="button" variant="secondary" onClick={() => setOpen(false)}>انصراف</Button><Button type="button" disabled={disabled} onClick={confirm}>تأیید و افزودن به فهرست</Button></ModalFooter>
        </ModalContent></Modal>
    </article>;
}
export function ExecutablePath({ path, name }: { path: string; name: string }) {
    const input = useRef<HTMLInputElement>(null);
    const [status, setStatus] = useState('');
    return <div className="space-y-2"><label className="block text-sm"><span>مسیر اجرایی تأییدشدهٔ <bdi dir="ltr">{name}</bdi></span><input ref={input} readOnly dir="ltr" value={path} className="mt-2 w-full min-w-0 rounded-lg border border-line bg-surface-muted px-3 py-2 font-mono text-xs" onFocus={event => event.target.select()} /></label><Button variant="secondary" onClick={async () => { try { await navigator.clipboard.writeText(path); setStatus('مسیر کپی شد.'); } catch { input.current?.focus(); input.current?.select(); setStatus('مسیر انتخاب شد؛ آن را دستی کپی کنید.'); } }}>کپی مسیر</Button><span role="status" className="ms-3 text-sm text-muted">{status}</span></div>;
}
export function SoftwareProgress({ job }: { job: SoftwareJob | null | undefined }) {
    const base = usePage<SharedProps>().props.app.base_path;
    if (!job) return null;
    return <div className="space-y-3" aria-live="polite">
        <div className="flex flex-wrap items-center gap-3"><StatusBadge value={job.status} /><Link className="text-sm text-primary underline" href={`${base}/jobs/${job.uuid}`}>گزارش کامل نصب و خطاها</Link></div>
        {isSoftwarePending(job) && <p className="text-sm leading-7 text-muted">عملیات در پس‌زمینه ادامه دارد. تا بررسی نتیجهٔ نهایی دکمه در حال اجرا می‌ماند؛ بستن صفحه آن را متوقف نمی‌کند.</p>}
        {job.message && <Alert tone="warning">{job.message}</Alert>}
        {job.summary && <p className="text-sm leading-7">کل: {job.summary.total.toLocaleString('fa-IR')} · موفق: {job.summary.succeeded.toLocaleString('fa-IR')} · ناموفق: {job.summary.failed.toLocaleString('fa-IR')} · اجرا‌نشده: {job.summary.skipped.toLocaleString('fa-IR')}</p>}
        <ul className="space-y-3">{job.packages.map(item => <li key={item.package} className="min-w-0 space-y-2 rounded-lg border border-line p-3 text-sm"><div className="flex flex-wrap items-center gap-2"><bdi dir="ltr" className="break-all font-mono">{item.package}{item.version ? ` ${item.version}` : ''}</bdi><StatusBadge value={item.status} /></div><p className="text-muted">{item.status === 'succeeded' && item.verified ? item.changed ? (job.operation === 'software.upgrade' ? 'به‌روزرسانی و اجرای آزمایشی تأیید شد.' : 'نصب و اجرای آزمایشی تأیید شد.') : (job.operation === 'software.upgrade' ? 'نسخهٔ نصب‌شده آخرین نسخهٔ در دسترس مخزن بود.' : 'از قبل نصب است؛ بررسی شد.') : item.status === 'skipped' ? 'اجرا نشد.' : item.phase_label ?? 'در انتظار اجرا'}</p>{item.message && <p className="leading-7 text-danger">{item.message}</p>}</li>)}</ul>
    </div>;
}
