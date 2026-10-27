import { useEffect, useRef } from 'react';
import type { SoftwareJob } from '@/components/catalog/software-picker';
import { useVisiblePoll } from '@/hooks/use-visible-poll';
import { notify } from '@/lib/notify';

export function isSoftwarePending(job: SoftwareJob | null | undefined): boolean {
    return !!job && !['succeeded', 'failed', 'cancelled'].includes(job.status);
}
export function useSoftwarePolling(pending: boolean, props: string[]) {
    useVisiblePoll(pending, props.join(','), 3000);
}
export function shouldNotifySoftware(job: SoftwareJob | null | undefined, observed: string | null, stored: string | null, submitted: string | null): boolean {
    return !!job && !isSoftwarePending(job) && stored !== 'notified' && (observed === job.uuid || stored === 'pending' || submitted === job.uuid);
}
export function useSoftwareCompletion(job: SoftwareJob | null | undefined, submitted: string | null = null, kind: 'install' | 'upgrade' = 'install') {
    const active = useRef<string | null>(null);
    useEffect(() => {
        if (!job) return;
        const key = `software-job:${job.uuid}`;
        let stored: string | null = null;
        try { stored = sessionStorage.getItem(key); } catch { /* The in-memory path still works when storage is denied. */ }
        if (isSoftwarePending(job)) {
            active.current = job.uuid;
            try { sessionStorage.setItem(key, 'pending'); } catch { /* Optional persistence. */ }
            return;
        }
        if (!shouldNotifySoftware(job, active.current, stored, submitted)) return;
        active.current = null;
        try { sessionStorage.setItem(key, 'notified'); } catch { /* toastId also deduplicates active notifications. */ }
        const summary = job.summary;
        const counts = summary ? `موفق: ${summary.succeeded.toLocaleString('fa-IR')}، ناموفق: ${summary.failed.toLocaleString('fa-IR')}، اجرا‌نشده: ${summary.skipped.toLocaleString('fa-IR')}` : '';
        const success = kind === 'upgrade' ? `بررسی به‌روزرسانی پایان یافت. ${counts}` : `بررسی و نصب نرم‌افزارهای انتخابی پایان یافت. ${counts}`;
        const failure = kind === 'upgrade' ? 'به‌روزرسانی کامل نشد؛ گزارش را بررسی کنید.' : 'عملیات نصب کامل نشد؛ گزارش هر بسته را بررسی کنید.';
        const message = job.status === 'succeeded' ? success : `${job.message ?? failure} ${counts}`;
        if (job.status === 'succeeded') notify.success(message, { toastId: key, autoClose: 9000 });
        else notify.error(message, { toastId: key, autoClose: 12000 });
    }, [job, kind, submitted]);
}
