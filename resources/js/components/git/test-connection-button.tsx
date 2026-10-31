import { router } from '@inertiajs/react';
import { RequestButton } from '@/components/ui/request-button';

export function TestConnectionButton({ url, disabled = false, automatic = false }: { url: string; disabled?: boolean; automatic?: boolean }) {
    return <RequestButton variant="secondary" loadingLabel="در حال تست اتصال…" disabled={disabled} request={finish => {
        router.post(url, automatic ? { mode: 'automatic' } : {}, { preserveScroll: true, onFinish: finish });
    }}>{automatic ? 'تست مسیر اتصال خودکار' : 'تست اتصال'}</RequestButton>;
}
