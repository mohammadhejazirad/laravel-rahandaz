import { isSoftwarePending } from '@/lib/software-jobs';
import { Alert } from '@/components/ui/alert';
import { StatusBadge } from '@/components/ui/status-badge';

export interface SoftwareJob {
    uuid: string;
    status: string;
    message: string | null;
    packages: { package: string; version: string; verified: boolean; changed: boolean }[];
}
export interface SoftwareState {
    available: boolean;
    can_request?: boolean;
    catalog: Record<string, { name: string; description: string }>;
    job?: SoftwareJob | null;
    request_key: string;
}
export function SoftwarePicker({ catalog, selected, onChange, disabled }: {
    catalog: SoftwareState['catalog']; selected: string[]; onChange: (keys: string[]) => void; disabled?: boolean;
}) {
    return <fieldset disabled={disabled} className="grid gap-3 sm:grid-cols-2">
        <legend className="mb-3 text-sm font-semibold">نرم‌افزارهای اختیاری</legend>
        {Object.entries(catalog).map(([key, item]) => <label key={key} className="flex min-w-0 cursor-pointer items-start gap-3 rounded-xl border border-line p-4 focus-within:ring-2 focus-within:ring-accent">
            <input name={`package-${key}`} type="checkbox" checked={selected.includes(key)} onChange={event => onChange(event.target.checked ? [...selected, key] : selected.filter(value => value !== key))} className="mt-1 size-5 shrink-0 accent-accent" />
            <span className="min-w-0"><span dir="ltr" className="block font-mono text-sm font-semibold">{item.name}</span><span className="mt-1 block text-sm leading-6 text-muted">{item.description}</span></span>
        </label>)}
    </fieldset>;
}
export function SoftwareProgress({ job }: { job: SoftwareJob | null | undefined }) {
    if (!job) return null;
    return <div className="space-y-3" aria-live="polite">
        <StatusBadge value={job.status} />
        {isSoftwarePending(job) && <p className="text-sm leading-7 text-muted">درخواست در پس‌زمینه انجام می‌شود. می‌توانید این صفحه را ببندید و بعداً نتیجه را ببینید؛ ثبت دوباره لازم نیست.</p>}
        {job.message && <Alert tone="warning">{job.message}</Alert>}
        {job.status === 'succeeded' && <Alert tone="info">بررسی سلامت و نصب موارد انتخاب‌شده تأیید شد.</Alert>}
        {job.packages.map(item => <p key={item.package} className="flex flex-wrap gap-2 text-sm"><span dir="ltr" className="break-all font-mono">{item.package} {item.version}</span><span className="text-muted">{item.changed ? 'نصب و بررسی شد' : 'از قبل نصب است؛ بررسی شد'}</span></p>)}
    </div>;
}
