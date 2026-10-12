import { usePage } from '@inertiajs/react';
import { X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Slide, ToastContainer, type CloseButtonProps } from 'react-toastify/unstyled';
import { notify } from '@/lib/notify';
import type { SharedProps } from '@/types';

export function FlashToaster() {
    const { flash } = usePage<SharedProps>().props;
    const [theme, setTheme] = useState<'light' | 'dark'>(readCurrentTheme);

    useEffect(() => {
        if (flash.status) {
            notify.success(flash.status, { toastId: flashToastId('status', flash.status) });
        }

        if (flash.error) {
            notify.error(flash.error, { toastId: flashToastId('error', flash.error) });
        }

        if (flash.warning) {
            notify.warning(flash.warning, { toastId: flashToastId('warning', flash.warning) });
        }

        if (flash.info) {
            notify.info(flash.info, { toastId: flashToastId('info', flash.info) });
        }
    }, [flash.status, flash.error, flash.warning, flash.info]);

    useEffect(() => {
        const root = document.documentElement;
        const read = () => setTheme(readCurrentTheme());
        read();
        const observer = new MutationObserver(read);
        observer.observe(root, { attributes: true, attributeFilter: ['data-theme'] });

        return () => observer.disconnect();
    }, []);

    return (
        <ToastContainer
            aria-label="پیام‌های موقت سیستم"
            className="rahandaz-toast-container"
            closeButton={ToastCloseButton}
            closeOnClick={false}
            draggable="touch"
            limit={3}
            newestOnTop
            pauseOnFocusLoss
            pauseOnHover
            position="top-center"
            rtl
            theme={theme}
            transition={Slide}
        />
    );
}

function readCurrentTheme(): 'light' | 'dark' {
    return document.documentElement.getAttribute('data-theme') === 'dark'
        ? 'dark'
        : 'light';
}

function flashToastId(type: string, message: string): string {
    let hash = 2166136261;

    for (let index = 0; index < message.length; index += 1) {
        hash ^= message.charCodeAt(index);
        hash = Math.imul(hash, 16777619);
    }

    return `flash:${type}:${(hash >>> 0).toString(36)}`;
}

function ToastCloseButton({ closeToast }: CloseButtonProps) {
    return (
        <button
            type="button"
            className="rahandaz-toast-close"
            aria-label="بستن پیام"
            onClick={closeToast}
        >
            <X aria-hidden="true" />
        </button>
    );
}
