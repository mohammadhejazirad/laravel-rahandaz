import { Head, useForm, usePage } from '@inertiajs/react';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Form } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { AppLayout } from '@/layouts/app-layout';
import type { SharedProps } from '@/types';

function envKeys(compose: string): string[] {
    const found = new Set<string>();
    for (const match of compose.matchAll(/\$\{([A-Z][A-Z0-9_]{0,63})/g)) {
        if (match[1]) found.add(match[1]);
    }
    return [...found].sort();
}

export default function DockerCompose({ installed, can_request }: { installed: boolean; can_request: boolean }) {
    const base = usePage<SharedProps>().props.app.base_path;
    const form = useForm({ slug: '', compose: 'services:\n  app:\n    image: nginx:1.27\n', env: {} as Record<string, string> });
    const keys = envKeys(form.data.compose);

    function submit() {
        form.post(`${base}/docker/stacks`, { preserveScroll: true });
    }

    return (
        <AppLayout title="Compose و env" description="Compose فقط با فایل yml هم کافی است. متغیرها از ${NAME} خوانده می‌شوند." eyebrow="Docker">
            <Head title="Compose و env" />
            {!installed ? <Alert tone="warning">تا نصب Docker از کاتالوگ، این درخواست روی میزبان اجرا نمی‌شود.</Alert> : <Alert tone="info">رمز را داخل yml ننویسید. مقدار env رمزنگاری می‌شود و در صفحه باقی نمی‌ماند.</Alert>}
            <Card>
                <Form className="grid gap-3" onSubmit={event => { event.preventDefault(); submit(); }}>
                    <Input name="slug" label="نام استک" dir="ltr" className="font-mono" isRequired value={form.data.slug} onChange={event => form.setData('slug', event.target.value)} error={form.errors.slug} />
                    <label className="block text-sm">
                        <span className="mb-1.5 block font-medium text-ink">متن Compose<span className="ms-1 text-danger" aria-hidden="true">*</span></span>
                        <textarea name="compose" dir="ltr" aria-required="true" value={form.data.compose} onChange={event => form.setData('compose', event.target.value)} className="min-h-56 w-full rounded-xl border border-line bg-surface p-3 font-mono text-sm text-ink" />
                    </label>
                    {form.errors.compose ? <p role="alert" className="text-sm text-danger">{form.errors.compose}</p> : null}
                    {keys.length > 0 ? keys.map(name => {
                        const secret = /PASS|SECRET|TOKEN|KEY/.test(name);
                        return (
                            <Input key={name} name={`env-${name}`} label={name} dir="ltr" className="font-mono" type={secret ? 'password' : 'text'} autoComplete="off" isRequired value={form.data.env[name] ?? ''} onChange={event => form.setData('env', { ...form.data.env, [name]: event.target.value })} />
                        );
                    }) : <p className="text-sm text-muted">متغیر {'${NAME}'} در متن دیده نشد. اگر برنامه رمز ندارد، همین کافی است.</p>}
                    {form.errors.env ? <p role="alert" className="text-sm text-danger">{form.errors.env}</p> : null}
                    <Button type="submit" loading={form.processing} disabled={!can_request || form.processing}>ثبت درخواست</Button>
                </Form>
            </Card>
        </AppLayout>
    );
}
