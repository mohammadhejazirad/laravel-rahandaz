import { router } from '@inertiajs/react';
import { useEffect } from 'react';
import type { SoftwareJob } from '@/components/catalog/software-picker';

export function isSoftwarePending(job: SoftwareJob | null | undefined): boolean {
    return !!job && ['queued', 'running', 'waiting_retry'].includes(job.status);
}
export function useSoftwarePolling(pending: boolean, props: string[]) {
    const only = props.join(',');
    useEffect(() => {
        if (!pending) return;
        const timer = window.setInterval(() => {
            if (document.visibilityState === 'visible') router.reload({ only: only.split(',') });
        }, 5000);
        return () => window.clearInterval(timer);
    }, [pending, only]);
}
