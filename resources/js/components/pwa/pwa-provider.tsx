import { useEffect, useRef, useState, type ReactNode } from 'react';
import { PwaContext } from './pwa-context';

interface InstallPrompt extends Event {
    prompt(): Promise<void>;
    userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
}

function isStandalone(): boolean {
    return window.matchMedia('(display-mode: standalone)').matches ||
        window.matchMedia('(display-mode: fullscreen)').matches ||
        ('standalone' in navigator && navigator.standalone === true);
}

export function PwaProvider({ children }: { children: ReactNode }) {
    const [installed, setInstalled] = useState(isStandalone);
    const [offline, setOffline] = useState(() => !navigator.onLine);
    const [prompt, setPrompt] = useState<InstallPrompt | null>(null);
    const [waiting, setWaiting] = useState<ServiceWorker | null>(null);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const applying = useRef(false);
    const timeout = useRef<ReturnType<typeof setTimeout> | null>(null);
    const secure = window.isSecureContext;
    const supported = 'serviceWorker' in navigator;

    useEffect(() => {
        const capture = (event: Event) => { event.preventDefault(); setPrompt(event as InstallPrompt); };
        const didInstall = () => { setInstalled(true); setPrompt(null); };
        const network = () => setOffline(!navigator.onLine);
        const display = window.matchMedia('(display-mode: standalone)');
        const changedDisplay = () => setInstalled(isStandalone());
        window.addEventListener('beforeinstallprompt', capture);
        window.addEventListener('appinstalled', didInstall);
        window.addEventListener('online', network);
        window.addEventListener('offline', network);
        display.addEventListener('change', changedDisplay);
        return () => {
            window.removeEventListener('beforeinstallprompt', capture);
            window.removeEventListener('appinstalled', didInstall);
            window.removeEventListener('online', network);
            window.removeEventListener('offline', network);
            display.removeEventListener('change', changedDisplay);
        };
    }, []);

    useEffect(() => {
        if (!import.meta.env.PROD || !secure || !supported) return;
        const scope = document.querySelector<HTMLMetaElement>('meta[name="rahandaz-pwa-scope"]')?.content;
        if (!scope?.startsWith('/') || scope.startsWith('//') || !scope.endsWith('/')) return;
        let disposed = false;
        let registration: ServiceWorkerRegistration | undefined;
        let stopTracking = () => {};
        const controllerChanged = () => {
            setWaiting(null);
            if (applying.current) {
                if (timeout.current) clearTimeout(timeout.current);
                window.location.reload();
            }
        };
        const check = () => {
            if (document.visibilityState === 'visible' && navigator.onLine) void registration?.update().catch(() => {});
        };
        navigator.serviceWorker.addEventListener('controllerchange', controllerChanged);
        document.addEventListener('visibilitychange', check);
        void navigator.serviceWorker.register(`${scope}service-worker.js`, { scope, updateViaCache: 'none' }).then((reg) => {
            if (disposed) return;
            registration = reg;
            if (reg.active && reg.waiting) setWaiting(reg.waiting);
            const track = () => {
                const worker = reg.installing;
                if (!worker) return;
                const changed = () => {
                    if (!disposed && worker.state === 'installed' && reg.active && reg.waiting) setWaiting(reg.waiting);
                };
                worker.addEventListener('statechange', changed);
                const previous = stopTracking;
                stopTracking = () => { previous(); worker.removeEventListener('statechange', changed); };
            };
            reg.addEventListener('updatefound', track);
            track();
            const previous = stopTracking;
            stopTracking = () => { previous(); reg.removeEventListener('updatefound', track); };
        }).catch(() => {
            if (!disposed) setError('آماده‌سازی نصب برنامه انجام نشد؛ اتصال امن و تنظیمات مرورگر را بررسی کنید.');
        });
        return () => {
            disposed = true;
            stopTracking();
            document.removeEventListener('visibilitychange', check);
            navigator.serviceWorker.removeEventListener('controllerchange', controllerChanged);
            if (timeout.current) clearTimeout(timeout.current);
        };
    }, [secure, supported]);

    async function install() {
        if (!prompt || busy) return;
        setBusy(true);
        setError(null);
        try {
            await prompt.prompt();
            await prompt.userChoice;
            setPrompt(null); // Only appinstalled / display-mode proves installation.
        } catch {
            setPrompt(null);
            setError('درخواست نصب انجام نشد. از راهنمای نصب یا منوی مرورگر استفاده کنید.');
        } finally {
            setBusy(false);
        }
    }

    function update() {
        if (!waiting || busy) return;
        setBusy(true);
        setError(null);
        applying.current = true;
        waiting.postMessage({ type: 'RAHANDAZ_APPLY_UPDATE' });
        timeout.current = setTimeout(() => {
            applying.current = false;
            setBusy(false);
            setError('به‌روزرسانی هنوز آماده نیست؛ پس از ذخیره کارها، صفحه را دوباره باز کنید.');
        }, 15000);
    }

    return <PwaContext value={{ installed, offline, secure, supported, canPrompt: prompt !== null, updateAvailable: waiting !== null, busy, error, install, update }}>{children}</PwaContext>;
}
