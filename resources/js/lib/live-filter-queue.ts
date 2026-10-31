export interface FilterRequest {
    isCurrent: () => boolean;
    setCancel: (cancel: () => void) => void;
    finish: () => void;
}

/** Owns only filter requests; never cancels unrelated saves or host operations. */
export function createLiveFilterQueue() {
    let timer: ReturnType<typeof setTimeout> | null = null;
    let abort: (() => void) | null = null;
    let generation = 0;
    function cancel() {
        generation++;
        if (timer !== null) clearTimeout(timer);
        timer = null;
        const previous = abort;
        abort = null;
        previous?.();
    }
    return {
        cancel,
        pending: () => timer !== null || abort !== null,
        schedule(run: (request: FilterRequest) => void, delay: number) {
            cancel();
            const current = generation;
            timer = setTimeout(() => {
                timer = null;
                if (current !== generation) return;
                run({
                    isCurrent: () => current === generation,
                    setCancel: callback => { if (current === generation) abort = callback; else callback(); },
                    finish: () => { if (current === generation) abort = null; },
                });
            }, delay);
        },
    };
}
