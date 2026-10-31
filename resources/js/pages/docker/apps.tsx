import { useState } from 'react';
import { Head, useForm, usePage } from '@inertiajs/react';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Form } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { AppLayout } from '@/layouts/app-layout';
import type { SharedProps } from '@/types';

interface AppTemplate {
    id: string;
    name: string;
    summary: string;
    ports: string;
    env: { name: string; label: string }[];
}

export default function DockerApps({ installed, can_request, apps }: { installed: boolean; can_request: boolean; apps: AppTemplate[] }) {
    const base = usePage<SharedProps>().props.app.base_path;
    const [chosen, setChosen] = useState<string | null>(null);
    const form = useForm({ slug: '', template: '', env: {} as Record<string, string> });
    const app = apps.find(item => item.id === chosen) ?? null;

    function submit() {
        if (!app) return;
        form.transform(data => ({ slug: data.slug, template: app.id, env: data.env }));
        form.post(`${base}/docker/stacks`, { preserveScroll: true });
    }

    return (
        <AppLayout title="برنامه‌های آماده" description="یک قالب را انتخاب کنید، نام استک و env را بدهید و درخواست را ثبت کنید." eyebrow="Docker">
            <Head title="برنامه‌های آماده Docker" />
            {!installed ? <Alert tone="warning">تا نصب Docker از کاتالوگ، این درخواست‌ها روی میزبان اجرا نمی‌شوند.</Alert> : <Alert tone="info">مقدارهای env رمز می‌شوند و دوباره در صفحه نشان داده نمی‌شوند.</Alert>}
            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                {apps.map(item => (
                    <Card key={item.id}>
                        <h2 className="font-semibold" dir="ltr">{item.name}</h2>
                        <p className="mt-2 text-sm leading-7 text-muted">{item.summary}</p>
                        <p className="mt-2 font-mono text-xs text-muted" dir="ltr">ports {item.ports}</p>
                        <Button type="button" variant={chosen === item.id ? 'primary' : 'secondary'} className="mt-4" onClick={() => { setChosen(item.id); form.setData({ slug: form.data.slug, template: item.id, env: {} }); }}>{chosen === item.id ? 'انتخاب شده' : 'انتخاب'}</Button>
                    </Card>
                ))}
            </div>
            {app ? (
                <Card>
                    <h2 className="font-semibold">راه‌اندازی {app.name}</h2>
                    <Form className="mt-4 grid gap-3" onSubmit={event => { event.preventDefault(); submit(); }}>
                        <Input name="slug" label="نام استک" dir="ltr" className="font-mono" isRequired value={form.data.slug} onChange={event => form.setData('slug', event.target.value)} error={form.errors.slug} />
                        {app.env.map(field => {
                            const secret = /PASS|SECRET|TOKEN|KEY/.test(field.name);
                            return (
                                <Input key={field.name} name={`env-${field.name}`} label={field.label} help={field.name} dir="ltr" className="font-mono" type={secret ? 'password' : 'text'} autoComplete="off" isRequired value={form.data.env[field.name] ?? ''} onChange={event => form.setData('env', { ...form.data.env, [field.name]: event.target.value })} />
                            );
                        })}
                        {form.errors.env || form.errors.template ? <p role="alert" className="text-sm text-danger">{form.errors.env || form.errors.template}</p> : null}
                        <Button type="submit" loading={form.processing} disabled={!can_request || form.processing}>ثبت درخواست</Button>
                    </Form>
                </Card>
            ) : null}
        </AppLayout>
    );
}
