import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
export function TestConnectionButton({ url, disabled = false, automatic = false }: { url: string; disabled?: boolean; automatic?: boolean }) {
    const [testing, setTesting] = useState(false);
    return <Button variant="secondary" loading={testing} loadingLabel="در حال تست اتصال" disabled={disabled} onClick={() => {
        setTesting(true);
        router.post(url, automatic ? { mode: 'automatic' } : {}, { preserveScroll: true, onFinish: () => setTesting(false) });
    }}>{automatic ? 'تست مسیر اتصال خودکار' : 'تست اتصال'}</Button>;
}
