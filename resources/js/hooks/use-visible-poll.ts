import { router } from '@inertiajs/react';
import { useEffect } from 'react';

export function useVisiblePoll(enabled: boolean, only: string, interval = 15000) {
    useEffect(() => {
        if (!enabled) return;
        let timer: ReturnType<typeof setTimeout> | undefined;
        let stopped = false;
        let inFlight = false;
        let failures = 0;
        let cancel: (() => void) | undefined;
        const clear = () => { if (timer) clearTimeout(timer); timer = undefined; };
        const ready = () => !stopped && document.visibilityState === 'visible' && navigator.onLine;
        const schedule = () => {
            clear();
            if (!ready() || inFlight) return;
            timer = setTimeout(() => {
                if (!ready()) return;
                inFlight = true;
                router.reload({ only: only.split(','),
                    onCancelToken: token => { cancel = () => token.cancel(); },
                    onSuccess: () => { failures = 0; },
                    onError: () => { failures = Math.min(failures + 1, 4); },
                    onNetworkError: () => { failures = Math.min(failures + 1, 4); return false; },
                    onHttpException: () => { failures = Math.min(failures + 1, 4); return false; },
                    onFinish: () => { cancel = undefined; inFlight = false; schedule(); },
                });
            }, Math.min(interval * 2 ** failures, 120000));
        };
        const visibility = () => { if (!ready()) { clear(); cancel?.(); } else schedule(); };
        document.addEventListener('visibilitychange', visibility);
        window.addEventListener('online', visibility);
        window.addEventListener('offline', visibility);
        schedule();
        return () => { stopped = true; clear(); cancel?.(); document.removeEventListener('visibilitychange', visibility); window.removeEventListener('online', visibility); window.removeEventListener('offline', visibility); };
    }, [enabled, only, interval]);
}
