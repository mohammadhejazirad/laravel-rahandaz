import { Link, usePage } from '@inertiajs/react';
import { Alert } from '@/components/ui/alert';
import { StatusBadge } from '@/components/ui/status-badge';
import { isSoftwarePending } from '@/lib/software-jobs';
import type { SharedProps } from '@/types';
import type { SoftwareJob } from './software-types';

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
