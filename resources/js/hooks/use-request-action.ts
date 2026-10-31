import { useEffect, useRef, useState } from 'react';
import { createActionLock } from '@/lib/action-lock';

export function useRequestAction() {
    const [pending, setPending] = useState(false);
    const mounted = useRef(true);
    const [lock] = useState(() => createActionLock(value => { if (mounted.current) setPending(value); }));
    useEffect(() => { mounted.current = true; return () => { mounted.current = false; }; }, []);
    return { pending, run: lock.run };
}
