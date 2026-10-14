import { router } from '@inertiajs/react';
import { useEffect } from 'react';
export function useVisiblePoll(enabled: boolean, only: string, interval = 15000) {
    useEffect(() => {
        if (!enabled) return;
        let timer: ReturnType<typeof setTimeout> | undefined;
        let stopped = false;
        let cancel: (() => void) | undefined;
        const clear = () => { if (timer) clearTimeout(timer); timer = undefined; };
        const schedule = () => {
            clear();
            if (!stopped && document.visibilityState === 'visible' && navigator.onLine) {
                timer = setTimeout(() => {
                    router.reload({ only: only.split(','),
                        onCancelToken: token => { cancel = () => token.cancel(); },
                        onFinish: () => { cancel = undefined; schedule(); },
                    });
                }, interval);
            }
        };
        const visibility = () => { if (document.visibilityState !== 'visible') { clear(); cancel?.(); } else schedule(); };
        document.addEventListener('visibilitychange', visibility);
        window.addEventListener('online', schedule);
        window.addEventListener('offline', visibility);
        schedule();
        return () => { stopped = true; clear(); cancel?.(); document.removeEventListener('visibilitychange', visibility); window.removeEventListener('online', schedule); window.removeEventListener('offline', visibility); };
    }, [enabled, only, interval]);
}
