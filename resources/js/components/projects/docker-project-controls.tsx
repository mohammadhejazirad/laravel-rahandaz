import { useState } from 'react';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { RadioCard } from '@/components/ui/radio-card';
import { useForm } from '@inertiajs/react';

const actionsFor: Record<string, string[]> = {
    compose_only: ['status', 'up', 'stop', 'restart', 'down'],
    dockerfile_only: ['status', 'build'],
    compose_and_dockerfile: ['status', 'up', 'stop', 'restart', 'down', 'build'],
};

const actionLabel: Record<string, string> = {
    status: 'وضعیت',
    up: 'اجرا',
    stop: 'توقف',
    restart: 'ریست',
    down: 'جمع‌کردن',
    build: 'ساخت تصویر',
};

export function DockerProjectControls({
    base,
    projectUuid,
    docker,
}: {
    base: string;
    projectUuid: string;
    docker: {
        can_request: boolean;
        execution_available: false;
        layouts: { id: string; label: string; detail: string }[];
    };
}) {
    const [layout, setLayout] = useState(docker.layouts[0]?.id ?? '');
    const form = useForm({
        layout: '',
        action: '',
        service: '',
        compose_file: 'compose.yml',
        dockerfile: 'Dockerfile',
        filenames: [] as string[],
    });
    const [pending, setPending] = useState('');
    const actions = actionsFor[layout] ?? [];

    function request(action: string) {
        const filenames = layout === 'dockerfile_only'
            ? [form.data.dockerfile]
            : layout === 'compose_only'
                ? [form.data.compose_file]
                : [form.data.compose_file, form.data.dockerfile];
        setPending(action);
        form.transform(data => ({ ...data, layout, action, filenames }));
        form.post(`${base}/projects/${projectUuid}/docker`, { preserveScroll: true, onFinish: () => setPending('') });
    }

    return (
        <Card>
            <h2 className="font-semibold">Docker پروژه</h2>
            <p className="mt-2 text-sm leading-7 text-muted">پروژه می‌تواند فقط Compose، فقط Dockerfile، یا هر دو را داشته باشد. فرمان آزاد و مسیر دلخواه پذیرفته نمی‌شود.</p>
            {docker.execution_available ? null : <Alert className="mt-3" tone="info">تا باز شدن موتور اجرا، این دکمه‌ها کانتینر را بالا یا پایین نمی‌آورند.</Alert>}
            <div className="mt-4 grid gap-2 sm:grid-cols-3">
                {docker.layouts.map(item => (
                    <RadioCard key={item.id} name="docker_layout" value={item.id} checked={layout === item.id} onChange={() => setLayout(item.id)}>
                        <span className="font-medium">{item.label}</span>
                        <span className="text-sm text-muted">{item.detail}</span>
                    </RadioCard>
                ))}
            </div>
            <div className="mt-4 grid gap-3 sm:grid-cols-2">
                <Input name="compose_file" label="فایل Compose" dir="ltr" className="font-mono" value={form.data.compose_file} onChange={event => form.setData('compose_file', event.target.value)} error={form.errors.compose_file} />
                <Input name="dockerfile" label="Dockerfile" dir="ltr" className="font-mono" value={form.data.dockerfile} onChange={event => form.setData('dockerfile', event.target.value)} error={form.errors.dockerfile} />
                <Input name="service" label="سرویس Compose" help="خالی یعنی همهٔ سرویس‌های همان فایل." dir="ltr" className="font-mono" value={form.data.service} onChange={event => form.setData('service', event.target.value)} error={form.errors.service} />
            </div>
            {form.errors.layout || form.errors.action ? <p role="alert" className="mt-3 text-sm text-danger">{form.errors.layout || form.errors.action}</p> : null}
            <div className="mt-4 flex flex-wrap gap-2">
                {actions.map(action => (
                    <Button key={action} type="button" variant="secondary" loading={form.processing && pending === action} disabled={!docker.can_request || layout === '' || form.processing} onClick={() => request(action)}>
                        {actionLabel[action] ?? action}
                    </Button>
                ))}
            </div>
        </Card>
    );
}
