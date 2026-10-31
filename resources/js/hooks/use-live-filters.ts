import { router, useForm } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { createLiveFilterQueue } from '@/lib/live-filter-queue';

/** User-driven GET filters: no initial request, debounced text, immediate choices. */
export function useLiveFilters<T extends Record<string, string>>(url: string, initial: T, { only, enabled = true }: { only?: string[]; enabled?: boolean } = {}) {
    const form = useForm<Record<string, string>>(initial);
    const [queue] = useState(createLiveFilterQueue);
    const submitted = useRef(JSON.stringify(initial));
    const ownVisit = useRef(false);
    const initialKey = JSON.stringify(initial);
    const context = initial.connection ?? url;
    const latest = useRef({ form, initial, enabled });
    useEffect(() => { latest.current = { form, initial, enabled }; });

    useEffect(() => () => queue.cancel(), [queue, context, enabled]);
    useEffect(() => {
        window.addEventListener('popstate', queue.cancel);
        return () => window.removeEventListener('popstate', queue.cancel);
    }, [queue]);
    useEffect(() => {
        if (!enabled) {
            latest.current.form.setData(latest.current.initial);
            submitted.current = JSON.stringify(latest.current.initial);
        }
    }, [enabled]);
    useEffect(() => router.on('before', event => {
        // Polling partial props must not interrupt typing; other navigation/saves cancel filters.
        if (!ownVisit.current && !(event.detail.visit.only.length && !event.detail.visit.only.includes('filters'))) queue.cancel();
    }), [queue]);
    useEffect(() => {
        // A history/link navigation restores filters; our own responses keep newer typing.
        if (!queue.pending() && submitted.current !== initialKey) {
            latest.current.form.setData(latest.current.initial);
            submitted.current = initialKey;
        }
    }, [initialKey, url, queue]);

    function setFilter<K extends keyof T>(key: K, value: T[K], immediate = false) {
        const next = { ...form.data, [key]: value };
        form.setData(next);
        queue.cancel();
        if (!enabled) return;
        queue.schedule(request => {
            if (!latest.current.enabled) return;
            submitted.current = JSON.stringify(next);
            form.clearErrors();
            ownVisit.current = true;
            try {
                router.get(url, next, {
                    async: true,
                    only,
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                    onCancelToken: token => request.setCancel(() => token.cancel()),
                    onError: errors => { if (request.isCurrent()) form.setError(errors); },
                    onFinish: request.finish,
                });
            } finally { ownVisit.current = false; }
        }, immediate ? 0 : 350);
    }

    return { ...form, setFilter };
}
