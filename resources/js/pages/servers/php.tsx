import { Head, useForm, usePage } from '@inertiajs/react';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Form } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { AppLayout } from '@/layouts/app-layout';
import type { SharedProps } from '@/types';

interface Profile {
    upload_max_filesize: string;
    post_max_size: string;
    memory_limit: string;
    max_execution_time: number;
    max_input_time: number;
    max_input_vars: number;
    timezone: string;
}

export default function ServerPhp({ profile, can_request }: { profile: Profile; can_request: boolean }) {
    const base = usePage<SharedProps>().props.app.base_path;
    const form = useForm({
        upload_max_filesize: profile.upload_max_filesize,
        post_max_size: profile.post_max_size,
        memory_limit: profile.memory_limit,
        max_execution_time: String(profile.max_execution_time),
        max_input_time: String(profile.max_input_time),
        max_input_vars: String(profile.max_input_vars),
        timezone: profile.timezone,
    });

    return (
        <AppLayout title="PHP سرور" description="اندازه‌ها و زمان‌های پرکاربرد برای PHP همین سرور." eyebrow="میزبان">
            <Head title="PHP سرور" />
            <Alert tone="warning">این مقدارها درخواست کل سرور هستند و هنوز داخل php.ini نوشته نمی‌شوند. تا موفقیت job، PHP در حال اجرا همان تنظیم قبلی را دارد.</Alert>
            <Card>
                <Form className="grid gap-3 sm:grid-cols-2" onSubmit={event => {
                    event.preventDefault();
                    form.transform(data => ({
                        ...data,
                        max_execution_time: Number(data.max_execution_time),
                        max_input_time: Number(data.max_input_time),
                        max_input_vars: Number(data.max_input_vars),
                    }));
                    form.put(`${base}/servers/php`, { preserveScroll: true });
                }}>
                    <Input name="upload_max_filesize" label="حداکثر آپلود" dir="ltr" className="font-mono" isRequired value={form.data.upload_max_filesize} onChange={event => form.setData('upload_max_filesize', event.target.value)} error={form.errors.upload_max_filesize} help="مثل 64M" />
                    <Input name="post_max_size" label="حداکثر POST" dir="ltr" className="font-mono" isRequired value={form.data.post_max_size} onChange={event => form.setData('post_max_size', event.target.value)} error={form.errors.post_max_size} />
                    <Input name="memory_limit" label="حافظه" dir="ltr" className="font-mono" isRequired value={form.data.memory_limit} onChange={event => form.setData('memory_limit', event.target.value)} error={form.errors.memory_limit} />
                    <Input name="max_execution_time" label="زمان اجرا (ثانیه)" dir="ltr" className="font-mono" inputMode="numeric" isRequired value={form.data.max_execution_time} onChange={event => form.setData('max_execution_time', event.target.value)} error={form.errors.max_execution_time} />
                    <Input name="max_input_time" label="زمان ورودی (ثانیه)" dir="ltr" className="font-mono" inputMode="numeric" isRequired value={form.data.max_input_time} onChange={event => form.setData('max_input_time', event.target.value)} error={form.errors.max_input_time} />
                    <Input name="max_input_vars" label="تعداد ورودی" dir="ltr" className="font-mono" inputMode="numeric" isRequired value={form.data.max_input_vars} onChange={event => form.setData('max_input_vars', event.target.value)} error={form.errors.max_input_vars} />
                    <Input name="timezone" label="منطقهٔ زمانی" dir="ltr" className="font-mono" isRequired value={form.data.timezone} onChange={event => form.setData('timezone', event.target.value)} error={form.errors.timezone} />
                    <div className="sm:col-span-2"><Button type="submit" loading={form.processing} disabled={!can_request}>ثبت درخواست PHP</Button></div>
                </Form>
            </Card>
        </AppLayout>
    );
}
