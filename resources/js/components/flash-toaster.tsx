import { usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { Toaster, toast } from 'sonner';
import type { SharedProps } from '@/types';

export function FlashToaster() {
    const { flash } = usePage<SharedProps>().props;

    useEffect(() => {
        if (flash.status) {
            toast.success(flash.status);
        }

        if (flash.error) {
            toast.error(flash.error);
        }
    }, [flash.status, flash.error]);

    return <Toaster dir="rtl" position="top-center" richColors closeButton />;
}
