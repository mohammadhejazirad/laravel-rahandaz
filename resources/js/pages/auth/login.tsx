import { Head, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { AuthLayout } from '@/layouts/auth-layout';
import type { SharedProps } from '@/types';

export default function Login() {
    const { app } = usePage<SharedProps>().props;
    const form = useForm({ identifier: '', password: '' });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post(`${app.base_path}/login`);
    }

    return (
        <AuthLayout title="ورود">
            <Head title="ورود" />
            <form className="space-y-4" onSubmit={submit}>
                <Input
                    label="نام کاربری یا ایمیل"
                    name="identifier"
                    autoComplete="username"
                    value={form.data.identifier}
                    error={form.errors.identifier}
                    onChange={(event) => form.setData('identifier', event.target.value)}
                />
                <Input
                    label="گذرواژه"
                    name="password"
                    type="password"
                    autoComplete="current-password"
                    value={form.data.password}
                    error={form.errors.password}
                    onChange={(event) => form.setData('password', event.target.value)}
                />
                <Button type="submit" className="w-full" disabled={form.processing}>
                    ورود
                </Button>
            </form>
            <p className="mt-4 text-sm text-muted">ثبت‌نام عمومی بسته است. فقط حساب ساخته‌شده روی همین سرور می‌تواند وارد شود.</p>
        </AuthLayout>
    );
}
