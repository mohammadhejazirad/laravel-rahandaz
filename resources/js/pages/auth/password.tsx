import { Head, useForm, usePage } from "@inertiajs/react";
import type { FormEvent } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { AuthLayout } from "@/layouts/auth-layout";
import type { SharedProps } from "@/types";

export default function Password() {
    const { app } = usePage<SharedProps>().props;
    const form = useForm({
        current_password: "",
        password: "",
        password_confirmation: "",
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.put(`${app.base_path}/password`);
    }

    return (
        <AuthLayout title="تغییر گذرواژه">
            <Head title="تغییر گذرواژه" />
            <p className="mb-4 text-sm text-muted">
                پیش از ادامه، گذرواژه اولیه باید عوض شود. این کار هیچ تغییری روی
                میزبان ایجاد نمی‌کند.
            </p>
            <form className="space-y-4" onSubmit={submit}>
                <Input
                    label="گذرواژه فعلی"
                    name="current_password"
                    type="password"
                    autoComplete="current-password"
                    autoFocus
                    required
                    value={form.data.current_password}
                    error={form.errors.current_password}
                    onChange={(event) =>
                        form.setData("current_password", event.target.value)
                    }
                />
                <Input
                    label="گذرواژه جدید"
                    name="password"
                    type="password"
                    autoComplete="new-password"
                    required
                    value={form.data.password}
                    error={form.errors.password}
                    onChange={(event) =>
                        form.setData("password", event.target.value)
                    }
                />
                <Input
                    label="تکرار گذرواژه جدید"
                    name="password_confirmation"
                    type="password"
                    autoComplete="new-password"
                    required
                    value={form.data.password_confirmation}
                    error={form.errors.password_confirmation}
                    onChange={(event) =>
                        form.setData(
                            "password_confirmation",
                            event.target.value,
                        )
                    }
                />
                <Button type="submit" className="w-full" loading={form.processing} loadingLabel="در حال ذخیره">
                    ذخیره گذرواژه
                </Button>
            </form>
        </AuthLayout>
    );
}
