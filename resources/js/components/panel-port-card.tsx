import { Link, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Form } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { StatusBadge } from '@/components/ui/status-badge';
import type { SharedProps } from '@/types';

export interface PanelPortState {
    available: boolean;
    can_change: boolean;
    port: number | null;
    origin: string | null;
    job: { uuid: string; status: string; error_code: string | null; target_origin: string | null } | null;
}

export function PanelPortCard({ panel }: { panel: PanelPortState }) {
    const base = usePage<SharedProps>().props.app.base_path;
    const form = useForm({ port: String(panel.port ?? 8080), expected_port: panel.port ?? 80 });
    const [connectionNote, setConnectionNote] = useState('');
    const pending = panel.job !== null && ['queued', 'running', 'waiting_retry'].includes(panel.job.status);
    useEffect(() => {
        if (!pending || !panel.job?.target_origin) return;
        const job = panel.job;
        let active = true;
        let checking = false;
        const started = Date.now();
        const timer = window.setInterval(async () => {
            if (checking || !active) return;
            checking = true;
            try {
                // Port changes keep the same host and session cookie. The server allows
                // this read only from the previous, root-configured panel origin.
                for (const origin of [...new Set([window.location.origin, job.target_origin])]) {
                    try {
                        const response = await fetch(`${origin}${base}/settings/panel-port/status?job=${job.uuid}`, { credentials: 'include', cache: 'no-store', signal: AbortSignal.timeout(4000) });
                        if (!response.ok || response.redirected) continue;
                        const status = await response.json() as { status: string };
                        if (!active) return;
                        if (status.status === 'succeeded') {
                            window.location.assign(`${job.target_origin}${base}/settings/profile`);
                            return;
                        }
                        if (status.status === 'failed') {
                            router.reload({ only: ['panel_port'] });
                            return;
                        }
                    } catch { /* A short disconnect during reload is expected. */ }
                }
                if (active && Date.now() - started > 90000) {
                    setConnectionNote('بررسی اتصال طولانی شده است. نشانی جدید و مسیر بازیابی SSH را بررسی کنید؛ موفقیت هنوز تأیید نشده است.');
                }
            } finally { checking = false; }
        }, 2500);
        return () => { active = false; window.clearInterval(timer); };
    }, [base, panel.job, pending]);

    return <Card className="space-y-4" aria-labelledby="panel-port-title">
        <div className="flex flex-wrap items-center justify-between gap-3">
            <h2 id="panel-port-title" className="font-semibold">پورت پنل</h2>
            {panel.job && <StatusBadge value={panel.job.status} />}
        </div>
        <p className="text-sm leading-7 text-muted">پس از ذخیره، سرویس وب پنل یک بار بازخوانی می‌شود و اتصال کوتاهی قطع خواهد شد. سرور reboot نمی‌شود. فقط مالک می‌تواند پورت را تغییر دهد؛ پورت‌های آزاد از ۱۰۲۴ تا ۶۵۵۳۵ پذیرفته می‌شوند.</p>
        {panel.origin && <p className="break-all font-mono text-sm" dir="ltr">{panel.origin}{base}/login</p>}
        {!panel.available && <p className="text-sm text-muted">مدیریت پورت هنوز توسط اپراتور روی این نصب فعال نشده است.</p>}
        {panel.can_change && <Form className="space-y-4" onSubmit={event => {
            event.preventDefault();
            const port = Number(form.data.port);
            if (!Number.isInteger(port) || port < 1024 || port > 65535) {
                form.setError('port', 'یک پورت صحیح بین ۱۰۲۴ و ۶۵۵۳۵ وارد کنید.');
                return;
            }
            form.transform(data => ({ ...data, expected_port: panel.port ?? 80 }));
            form.post(`${base}/settings/panel-port`, { preserveScroll: true });
        }}>
            <Input name="port" label="پورت پنل" isRequired inputMode="numeric" pattern="[0-9]{4,5}" maxLength={5} dir="ltr" className="font-mono" value={form.data.port} error={form.errors.port ?? form.errors.expected_port} onChange={event => form.setData('port', event.target.value)} help="پورت سرویس دیگری را انتخاب نکنید. پورت‌های محدودشدهٔ مرورگر پذیرفته نمی‌شوند." />
            <Button type="submit" loading={form.processing} disabled={pending}>ذخیره و بازخوانی پنل</Button>
        </Form>}
        {panel.available && !panel.can_change && <p className="text-sm text-muted">این تنظیم فقط در اختیار مالک نصب است.</p>}
        {pending && <p role="status" className="text-sm text-muted">در انتظار نتیجهٔ واقعی عملیات؛ پس از تأیید سلامت، به نشانی جدید منتقل می‌شوید.</p>}
        {connectionNote && <p role="status" className="text-sm text-muted">{connectionNote}</p>}
        {panel.job && <div className="flex flex-wrap items-center gap-3 text-sm">
            <Link className="text-primary underline" href={`${base}/jobs/${panel.job.uuid}`}>گزارش تغییر پورت</Link>
            {panel.job.error_code && <code className="break-all font-mono" dir="ltr">{panel.job.error_code}</code>}
            {panel.job.target_origin && <a className="break-all text-primary underline" dir="ltr" href={`${panel.job.target_origin}${base}/login`}>نشانی جدید: {panel.job.target_origin}</a>}
        </div>}
        <p className="text-sm leading-7 text-muted">تغییر پورت رمزگذاری ایجاد نمی‌کند؛ اتصال فعلی HTTP است. اگر پورت مقصد توسط فایروال دیتاسنتر بسته باشد، ابتدا آن را باز کنید. مسیر SSH و بازیابی لوپ‌بک حفظ می‌شود.</p>
    </Card>;
}
