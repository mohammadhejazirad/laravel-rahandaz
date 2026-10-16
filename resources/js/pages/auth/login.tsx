import { Form } from '@/components/ui/form';
import { Head, useForm, usePage } from "@inertiajs/react";
import type { FormEvent } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Separator } from "@/components/ui/separator";
import { AuthLayout } from "@/layouts/auth-layout";
import { notify } from "@/lib/notify";
import type { SharedProps } from "@/types";

export default function Login() {
    const { app } = usePage<SharedProps>().props;
    const form = useForm({ identifier: "", password: "" });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post(`${app.base_path}/login`, {
            onError: (errors) => notify.formErrors(errors),
        });
    }

    return (
        <AuthLayout title="ورود">
            <Head title="ورود" />
            <Form className="space-y-4" onSubmit={submit}>
                <Input
                    label="نام کاربری یا ایمیل"
                    name="identifier"
                    autoComplete="username"
                    autoFocus
                    isRequired
                    value={form.data.identifier}
                    error={form.errors.identifier}
                    onChange={(event) =>
                        form.setData("identifier", event.target.value)
                    }
                />
                <Input
                    label="گذرواژه"
                    name="password"
                    type="password"
                    autoComplete="current-password"
                    isRequired
                    value={form.data.password}
                    error={form.errors.password}
                    onChange={(event) =>
                        form.setData("password", event.target.value)
                    }
                />
                <Button
                    type="submit"
                    className="w-full"
                    loading={form.processing}
                    loadingLabel="در حال ورود"
                >
                    ورود
                </Button>
            </Form>
            <Separator className="my-5" />
            <p className="text-sm leading-7 text-muted">
                ثبت‌نام عمومی بسته است. فقط حسابی که مدیر این نصب ساخته است
                می‌تواند وارد شود.
            </p>
        </AuthLayout>
    );
}
