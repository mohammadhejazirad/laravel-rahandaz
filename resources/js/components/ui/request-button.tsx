import { router } from '@inertiajs/react';
import { Button, type ButtonProps } from './button';
import { useRequestAction } from '@/hooks/use-request-action';
import { notify } from '@/lib/notify';

type RequestButtonProps = Omit<ButtonProps, 'asChild' | 'onClick' | 'type'> & {
    request: (finish: () => void) => void;
};

/** Callback requests stay busy until onFinish, including validation, errors and cancellation. */
export function RequestButton({ request, loading, ...props }: RequestButtonProps) {
    const action = useRequestAction();
    return <Button {...props} type="button" loading={loading || action.pending} onClick={() => {
        try { action.run(request); } catch { notify.error('درخواست آغاز نشد؛ دوباره تلاش کنید.'); }
    }} />;
}

export function ReloadButton({ only, ...props }: Omit<RequestButtonProps, 'request'> & { only: string[] }) {
    return <RequestButton variant="secondary" loadingLabel="در حال تازه‌سازی…" {...props} request={finish => router.reload({ only, onFinish: finish })} />;
}

export function AsyncButton({ action, errorMessage = 'عملیات انجام نشد؛ دوباره تلاش کنید.', ...props }: Omit<RequestButtonProps, 'request'> & {
    action: () => Promise<void>;
    errorMessage?: string;
}) {
    return <RequestButton {...props} request={finish => {
        Promise.resolve().then(action).catch(() => notify.error(errorMessage)).finally(finish);
    }} />;
}
