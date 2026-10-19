import { Link, usePage } from '@inertiajs/react';
import { useRef, useState } from 'react';
import { isSoftwarePending } from '@/lib/software-jobs';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { StatusBadge } from '@/components/ui/status-badge';
import type { SharedProps } from '@/types';

export interface SoftwarePackageResult {
    package: string; version: string | null; verified: boolean; changed: boolean;
    status: string; phase?: string; phase_label?: string; message?: string | null; executable_path?: string;
}
export interface SoftwareJob {
    uuid: string; status: string; message: string | null;
    packages: SoftwarePackageResult[];
    summary?: { total: number; succeeded: number; failed: number; skipped: number };
}
export interface SoftwareState {
    available: boolean; can_request?: boolean;
    catalog: Record<string, { name: string; description: string; category?: string }>;
    categories?: Record<string, string>;
    installed?: SoftwarePackageResult[];
    job?: SoftwareJob | null; request_key: string;
}
const defaultCategories: Record<string, string> = { web: 'وب‌سرورها', runtime: 'زبان‌ها و runtimeها', database: 'دیتابیس‌ها', cache: 'کش', utility: 'ابزارهای کاربردی', antivirus: 'آنتی‌ویروس‌ها', security: 'ابزارهای امنیتی', process: 'مدیریت پردازش' };
export function SoftwarePicker({ catalog, categories = defaultCategories, selected, onChange, disabled }: {
    catalog: SoftwareState['catalog']; categories?: Record<string, string>; selected: string[]; onChange: (keys: string[]) => void; disabled?: boolean;
}) {
    const groups = Object.entries(categories).map(([key, label]) => ({ key, label, items: Object.entries(catalog).filter(([, item]) => (item.category ?? 'utility') === key) })).filter(group => group.items.length > 0);
    return <div className="space-y-5">{groups.map(group => <fieldset key={group.key} disabled={disabled} className="grid gap-3 sm:grid-cols-2">
        <legend className="mb-3 text-sm font-semibold">{group.label}</legend>
        {group.items.map(([key, item]) => <label key={key} className="flex min-w-0 cursor-pointer items-start gap-3 rounded-xl border border-line p-4 focus-within:ring-2 focus-within:ring-accent">
            <input name={`package-${key}`} type="checkbox" checked={selected.includes(key)} onChange={event => onChange(event.target.checked ? [...selected, key] : selected.filter(value => value !== key))} className="mt-1 size-5 shrink-0 accent-accent" />
            <span className="min-w-0"><span dir="ltr" className="block font-mono text-sm font-semibold">{item.name}</span><span className="mt-1 block text-sm leading-6 text-muted">{item.description}</span></span>
        </label>)}
    </fieldset>)}</div>;
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
        {isSoftwarePending(job) && <p className="text-sm leading-7 text-muted">نصب در پس‌زمینه ادامه دارد. تا بررسی نتیجهٔ نهایی دکمه در حال اجرا می‌ماند؛ بستن صفحه نصب را متوقف نمی‌کند.</p>}
        {job.message && <Alert tone="warning">{job.message}</Alert>}
        {job.summary && <p className="text-sm leading-7">کل: {job.summary.total.toLocaleString('fa-IR')} · موفق: {job.summary.succeeded.toLocaleString('fa-IR')} · ناموفق: {job.summary.failed.toLocaleString('fa-IR')} · اجرا‌نشده: {job.summary.skipped.toLocaleString('fa-IR')}</p>}
        <ul className="space-y-3">{job.packages.map(item => <li key={item.package} className="min-w-0 space-y-2 rounded-lg border border-line p-3 text-sm"><div className="flex flex-wrap items-center gap-2"><bdi dir="ltr" className="break-all font-mono">{item.package}{item.version ? ` ${item.version}` : ''}</bdi><StatusBadge value={item.status} /></div><p className="text-muted">{item.status === 'succeeded' && item.verified ? item.changed ? 'نصب و اجرای آزمایشی تأیید شد.' : 'از قبل نصب است؛ بررسی شد.' : item.status === 'skipped' ? 'اجرا نشد.' : item.phase_label ?? 'در انتظار اجرا'}</p>{item.message && <p className="leading-7 text-danger">{item.message}</p>}</li>)}</ul>
    </div>;
}
