/** Locks synchronously, before React renders; completion is controlled by the real request. */
export function createActionLock(onChange: (pending: boolean) => void) {
    let pending = false;
    return {
        run(request: (finish: () => void) => void): boolean {
            if (pending) return false;
            pending = true;
            onChange(true);
            let finished = false;
            const finish = () => {
                if (finished) return;
                finished = true;
                pending = false;
                onChange(false);
            };
            try { request(finish); } catch (error) { finish(); throw error; }
            return true;
        },
    };
}
