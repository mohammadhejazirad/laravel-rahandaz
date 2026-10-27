import { Link, usePage } from '@inertiajs/react';
import { useRef, useState } from 'react';
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
    upgradeable?: boolean; upgrade_note?: string | null;
}
export interface SoftwareState {
    available: boolean; can_request?: boolean;
    catalog: Record<string, CatalogItem>;
    categories?: Record<string, string>;
    installed?: SoftwarePackageResult[];
    job?: SoftwareJob | null; active?: SoftwareJob | null; request_key: string;
}
const defaultCategories: Record<string, string> = { web: 'وب‌سرورها', runtime: 'زبان‌ها و runtimeها', database: 'دیتابیس‌ها', cache: 'کش', utility: 'ابزارهای کاربردی', antivirus: 'آنتی‌ویروس‌ها', security: 'ابزارهای امنیتی', process: 'مدیریت پردازش' };
export function SoftwarePicker({ catalog, categories = defaultCategories, selected, onChange, disabled }: {
    catalog: SoftwareState['catalog']; categories?: Record<string, string>; selected: string[]; onChange: (keys: string[]) => void; disabled?: boolean;
}) {
    const groups = Object.entries(categories).map(([key, label]) => ({ key, label, items: rowsFor(Object.entries(catalog).filter(([, item]) => (item.category ?? 'utility') === key)) })).filter(group => group.items.length > 0);
    return <div className="space-y-5">{groups.map(group => <fieldset key={group.key} disabled={disabled} className="grid gap-3 sm:grid-cols-2">
        <legend className="mb-3 text-sm font-semibold">{group.label}</legend>
        {group.items.map(row => row.kind === 'single'
            ? <PackageCheckbox key={row.key} itemKey={row.key} item={row.item} selected={selected} onChange={onChange} />
            : <VersionChoice key={row.product} items={row.items} selected={selected} onChange={onChange} disabled={disabled} />)}
    </fieldset>)}</div>;
}
function rowsFor(items: [string, CatalogItem][]): Array<{ kind: 'single'; key: string; item: CatalogItem } | { kind: 'product'; product: string; items: [string, CatalogItem][] }> {
    const seen = new Set<string>();
    const rows: Array<{ kind: 'single'; key: string; item: CatalogItem } | { kind: 'product'; product: string; items: [string, CatalogItem][] }> = [];
    for (const [key, item] of items) {
        if (!item.product) {
            rows.push({ kind: 'single', key, item });
            continue;
        }
        if (seen.has(item.product)) continue;
        seen.add(item.product);
        const group = items.filter(([, candidate]) => candidate.product === item.product);
        if (group.length < 2) rows.push({ kind: 'single', key: group[0][0], item: group[0][1] });
        else rows.push({ kind: 'product', product: item.product, items: group });
    }
    return rows;
}
function PackageCheckbox({ itemKey, item, selected, onChange }: { itemKey: string; item: CatalogItem; selected: string[]; onChange: (keys: string[]) => void }) {
    return <label className="flex min-w-0 cursor-pointer items-start gap-3 rounded-xl border border-line p-4 focus-within:ring-2 focus-within:ring-accent">
        <input name={`package-${itemKey}`} type="checkbox" checked={selected.includes(itemKey)} onChange={event => onChange(event.target.checked ? [...selected, itemKey] : selected.filter(value => value !== itemKey))} className="mt-1 size-5 shrink-0 accent-accent" />
        <span className="min-w-0"><span dir="ltr" className="block font-mono text-sm font-semibold">{item.name}</span><span className="mt-1 block text-sm leading-6 text-muted">{item.description}</span></span>
    </label>;
}
function VersionChoice({ items, selected, onChange, disabled }: { items: [string, CatalogItem][]; selected: string[]; onChange: (keys: string[]) => void; disabled?: boolean }) {
    const [open, setOpen] = useState(false);
    const [draft, setDraft] = useState<string[]>([]);
    const first = items[0][1];
    const chosen = items.filter(([key]) => selected.includes(key));
    const keys = items.map(([key]) => key);
    return <div className="flex min-w-0 flex-col gap-3 rounded-xl border border-line p-4">
        <div className="min-w-0">
            <span dir="ltr" className="block font-mono text-sm font-semibold">{first.product_name ?? first.name}</span>
            <span className="mt-1 block text-sm leading-6 text-muted">{first.product_description ?? first.description}</span>
            {chosen.length > 0 && <span dir="ltr" className="mt-2 block break-words font-mono text-xs">{chosen.map(([, item]) => item.version_label ?? item.name).join(', ')}</span>}
        </div>
        <Button type="button" variant="secondary" disabled={disabled} onClick={() => { setDraft(keys.filter(key => selected.includes(key))); setOpen(true); }}>انتخاب نسخه</Button>
        <Modal open={open} onOpenChange={setOpen}>
            <ModalContent>
                <ModalHeader>
                    <ModalTitle>انتخاب نسخهٔ <bdi dir="ltr">{first.product_name ?? first.name}</bdi></ModalTitle>
                    <ModalDescription>یک یا چند نسخه را از فهرست ثابت انتخاب کنید. نام بسته یا نشانی دلخواه پذیرفته نمی‌شود.</ModalDescription>
                </ModalHeader>
                <fieldset className="mt-4 space-y-2">
                    <legend className="sr-only">نسخه‌های قابل نصب</legend>
                    {items.map(([key, item]) => <label key={key} className="flex min-w-0 cursor-pointer items-start gap-3 rounded-xl border border-line p-3">
                        <input name={`version-${key}`} type="checkbox" checked={draft.includes(key)} onChange={event => setDraft(current => event.target.checked ? [...current, key] : current.filter(value => value !== key))} className="mt-1 size-5 shrink-0 accent-accent" />
                        <span className="min-w-0"><bdi dir="ltr" className="font-mono text-sm font-semibold">{item.version_label ?? item.name}</bdi><span className="mt-1 block text-sm leading-6 text-muted">{item.description}</span></span>
                    </label>)}
                </fieldset>
                <ModalFooter>
                    <Button type="button" variant="secondary" onClick={() => setOpen(false)}>انصراف</Button>
                    <Button type="button" onClick={() => { onChange([...selected.filter(key => !keys.includes(key)), ...draft]); setOpen(false); }}>تأیید انتخاب</Button>
                </ModalFooter>
            </ModalContent>
        </Modal>
    </div>;
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
