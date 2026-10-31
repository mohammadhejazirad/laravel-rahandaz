import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Alert } from '@/components/ui/alert';
import { useForm } from '@inertiajs/react';

const actionLabel: Record<string, string> = {
    status: 'وضعیت',
    start: 'شروع',
    stop: 'توقف',
    restart: 'ریست',
    reload: 'بارگذاری دوباره',
};

export function HostServices({
    hostUuid,
    base,
    services,
}: {
    hostUuid: string;
    base: string;
    services: {
        can_request: boolean;
        execution_available: false;
        node_note: string;
        items: { unit: string; label: string; actions: string[]; stop_blocked: string | null }[];
    };
}) {
    const form = useForm({ unit: '', action: '' });
    const [pending, setPending] = useState('');

    function request(unit: string, action: string) {
        setPending(`${unit}:${action}`);
        form.transform(() => ({ unit, action }));
        form.post(`${base}/servers/${hostUuid}/services`, { preserveScroll: true, onFinish: () => setPending('') });
    }

    return (
        <Card>
            <h2 className="font-semibold">سرویس‌های میزبان</h2>
            <p className="mt-2 text-sm leading-7 text-muted">وضعیت، شروع، ریست و در صورت اجازه توقف. درخواست تا موفقیت job روی سرور انجام‌شده نیست.</p>
            {services.execution_available ? null : <Alert className="mt-3" tone="info">موتور اجرا بسته است. دکمه‌ها فقط درخواست را در صف ثبت می‌کنند.</Alert>}
            <p className="mt-3 text-sm leading-7 text-muted">{services.node_note}</p>
            <ul className="mt-4 grid gap-3">
                {services.items.map(item => (
                    <li key={item.unit} className="rounded-xl border border-line p-3">
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <span className="font-medium">{item.label}</span>
                            <span dir="ltr" className="font-mono text-xs text-muted">{item.unit}</span>
                        </div>
                        {item.stop_blocked ? <p className="mt-2 text-sm leading-6 text-muted">{item.stop_blocked}</p> : null}
                        <div className="mt-3 flex flex-wrap gap-2">
                            {item.actions.map(action => (
                                <Button key={action} type="button" variant="secondary" loading={form.processing && pending === `${item.unit}:${action}`} disabled={!services.can_request || form.processing} onClick={() => request(item.unit, action)}>
                                    {actionLabel[action] ?? action}
                                </Button>
                            ))}
                        </div>
                    </li>
                ))}
            </ul>
        </Card>
    );
}
