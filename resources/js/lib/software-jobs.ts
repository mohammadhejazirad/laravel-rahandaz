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
export function useSoftwareCompletion(job: SoftwareJob | null | undefined) {
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
        if (stored === 'notified' || (active.current !== job.uuid && stored !== 'pending')) return;
        active.current = null;
        try { sessionStorage.setItem(key, 'notified'); } catch { /* toastId also deduplicates active notifications. */ }
        const summary = job.summary;
        const counts = summary ? `موفق: ${summary.succeeded.toLocaleString('fa-IR')}، ناموفق: ${summary.failed.toLocaleString('fa-IR')}، اجرا‌نشده: ${summary.skipped.toLocaleString('fa-IR')}` : '';
        const message = job.status === 'succeeded' ? `بررسی و نصب نرم‌افزارهای انتخابی پایان یافت. ${counts}` : `${job.message ?? 'عملیات نصب کامل نشد؛ گزارش هر بسته را بررسی کنید.'} ${counts}`;
        if (job.status === 'succeeded') notify.success(message, { toastId: key, autoClose: 9000 });
        else notify.error(message, { toastId: key, autoClose: 12000 });
    }, [job]);
}
