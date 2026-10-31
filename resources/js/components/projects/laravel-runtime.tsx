import { useForm } from '@inertiajs/react';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Form } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';

export interface LaravelRuntime {
    can_request: boolean;
    schedule_enabled: boolean;
    horizon_enabled: boolean;
    reverb_enabled: boolean;
    reverb_host: string;
    reverb_port: number;
    octane_enabled: boolean;
    octane_server: string;
    octane_host: string;
    octane_port: number;
    octane_workers: number;
    inertia_ssr_enabled: boolean;
    inertia_ssr_path: string;
    maintenance_enabled: boolean;
}

function ToggleCard({ title, body, enabled, feature, action, canRequest }: { title: string; body: string; enabled: boolean; feature: string; action: string; canRequest: boolean }) {
    const form = useForm({ enabled });
    return (
        <Card>
            <h2 className="font-semibold">{title}</h2>
            <p className="mt-2 text-sm leading-7 text-muted">{body}</p>
            <Form className="mt-4 space-y-3" onSubmit={event => { event.preventDefault(); form.transform(data => ({ feature, enabled: data.enabled ? 1 : 0 })); form.put(action, { preserveScroll: true }); }}>
                <Checkbox name={`${feature}-enabled`} checked={form.data.enabled} onChange={event => form.setData('enabled', event.target.checked)}>روشن</Checkbox>
                <Button type="submit" loading={form.processing} disabled={!canRequest}>ذخیرهٔ درخواست</Button>
            </Form>
        </Card>
    );
}

export function LaravelRuntimeCards({ laravel, action }: { laravel: LaravelRuntime; action: string }) {
    const reverb = useForm({ enabled: laravel.reverb_enabled, host: laravel.reverb_host, port: String(laravel.reverb_port) });
    const octane = useForm({ enabled: laravel.octane_enabled, server: laravel.octane_server, host: laravel.octane_host, port: String(laravel.octane_port), workers: String(laravel.octane_workers) });
    const inertia = useForm({ enabled: laravel.inertia_ssr_enabled, path: laravel.inertia_ssr_path });

    return (
        <div className="space-y-4">
            <Alert tone="info">این کارت‌ها درخواست همان پروژه را ذخیره می‌کنند. تا وقتی job میزبان موفق نشود، scheduler، Horizon، Reverb، Octane، SSR یا حالت تعمیر روی خود اپلیکیشن اجرا نشده است.</Alert>
            <div className="grid gap-4 lg:grid-cols-2">
                <ToggleCard title="زمان‌بند" body="اجرای دوره‌ای schedule:run برای همین پروژه." enabled={laravel.schedule_enabled} feature="schedule" action={action} canRequest={laravel.can_request} />
                <ToggleCard title="Horizon" body="صفحه و پردازش صف Horizon برای همین پروژه." enabled={laravel.horizon_enabled} feature="horizon" action={action} canRequest={laravel.can_request} />
                <Card>
                    <h2 className="font-semibold">Reverb</h2>
                    <p className="mt-2 text-sm leading-7 text-muted">میزبان و پورت وب‌سوکت همین پروژه. پورت پنل و SSH عوض نمی‌شود.</p>
                    <Form className="mt-4 grid gap-3" onSubmit={event => { event.preventDefault(); reverb.transform(data => ({ feature: 'reverb', enabled: data.enabled ? 1 : 0, host: data.host, port: Number(data.port) })); reverb.put(action, { preserveScroll: true }); }}>
                        <Checkbox name="reverb-enabled" checked={reverb.data.enabled} onChange={event => reverb.setData('enabled', event.target.checked)}>روشن</Checkbox>
                        <Input name="reverb-host" label="میزبان" dir="ltr" className="font-mono" isRequired value={reverb.data.host} onChange={event => reverb.setData('host', event.target.value)} error={reverb.errors.host} />
                        <Input name="reverb-port" label="پورت" dir="ltr" className="font-mono" inputMode="numeric" isRequired value={reverb.data.port} onChange={event => reverb.setData('port', event.target.value)} error={reverb.errors.port} />
                        <Button type="submit" loading={reverb.processing} disabled={!laravel.can_request}>ذخیرهٔ درخواست</Button>
                    </Form>
                </Card>
                <Card>
                    <h2 className="font-semibold">Octane</h2>
                    <p className="mt-2 text-sm leading-7 text-muted">سرور برنامه، میزبان، پورت و تعداد worker. PHP-FPM پنل دست نمی‌خورد.</p>
                    <Form className="mt-4 grid gap-3" onSubmit={event => { event.preventDefault(); octane.transform(data => ({ feature: 'octane', enabled: data.enabled ? 1 : 0, server: data.server, host: data.host, port: Number(data.port), workers: Number(data.workers) })); octane.put(action, { preserveScroll: true }); }}>
                        <Checkbox name="octane-enabled" checked={octane.data.enabled} onChange={event => octane.setData('enabled', event.target.checked)}>روشن</Checkbox>
                        <Select name="octane-server" label="سرور" value={octane.data.server} onChange={event => octane.setData('server', event.target.value)} error={octane.errors.server} isRequired>
                            <option value="swoole">swoole</option>
                            <option value="roadrunner">roadrunner</option>
                        </Select>
                        <Input name="octane-host" label="میزبان" dir="ltr" className="font-mono" isRequired value={octane.data.host} onChange={event => octane.setData('host', event.target.value)} error={octane.errors.host} />
                        <Input name="octane-port" label="پورت" dir="ltr" className="font-mono" inputMode="numeric" isRequired value={octane.data.port} onChange={event => octane.setData('port', event.target.value)} error={octane.errors.port} />
                        <Input name="octane-workers" label="Workerها" dir="ltr" className="font-mono" inputMode="numeric" isRequired value={octane.data.workers} onChange={event => octane.setData('workers', event.target.value)} error={octane.errors.workers} />
                        <Button type="submit" loading={octane.processing} disabled={!laravel.can_request}>ذخیرهٔ درخواست</Button>
                    </Form>
                </Card>
                <Card>
                    <h2 className="font-semibold">Inertia SSR</h2>
                    <p className="mt-2 text-sm leading-7 text-muted">مسیر فایل ssr.js نسبت به ریشهٔ پروژه. فایل از اینجا ساخته یا اجرا نمی‌شود.</p>
                    <Form className="mt-4 grid gap-3" onSubmit={event => { event.preventDefault(); inertia.transform(data => ({ feature: 'inertia', enabled: data.enabled ? 1 : 0, path: data.path })); inertia.put(action, { preserveScroll: true }); }}>
                        <Checkbox name="inertia-enabled" checked={inertia.data.enabled} onChange={event => inertia.setData('enabled', event.target.checked)}>روشن</Checkbox>
                        <Input name="ssr-path" label="مسیر SSR" dir="ltr" className="font-mono" isRequired value={inertia.data.path} onChange={event => inertia.setData('path', event.target.value)} error={inertia.errors.path} />
                        <Button type="submit" loading={inertia.processing} disabled={!laravel.can_request}>ذخیرهٔ درخواست</Button>
                    </Form>
                </Card>
                <ToggleCard title="حالت تعمیر" body="درخواست بستن سایت برای عموم. تا موفقیت job، بازدیدکننده هنوز همان وضعیت قبلی را می‌بیند." enabled={laravel.maintenance_enabled} feature="maintenance" action={action} canRequest={laravel.can_request} />
            </div>
        </div>
    );
}
