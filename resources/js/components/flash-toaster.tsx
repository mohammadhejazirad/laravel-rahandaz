import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Toaster } from 'sonner';
import { notify } from '@/lib/notify';
import type { SharedProps } from '@/types';

export function FlashToaster() {
    const { flash } = usePage<SharedProps>().props;
    const [theme, setTheme] = useState<'light' | 'dark'>('light');

    useEffect(() => {
        if (flash.status) {
            notify.success(flash.status);
        }

        if (flash.error) {
            notify.error(flash.error);
        }
    }, [flash.status, flash.error]);

    useEffect(() => {
        const root = document.documentElement;
        const read = () => setTheme(root.getAttribute('data-theme') === 'dark' ? 'dark' : 'light');
        read();
        const observer = new MutationObserver(read);
        observer.observe(root, { attributes: true, attributeFilter: ['data-theme'] });

        return () => observer.disconnect();
    }, []);

    return (
        <Toaster
            dir="rtl"
            position="top-center"
            closeButton
            theme={theme}
            toastOptions={{ className: 'font-sans' }}
        />
    );
}
