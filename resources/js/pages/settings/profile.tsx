import { Head, Link, usePage } from "@inertiajs/react";
import { Alert } from "@/components/ui/alert";
import { Card } from "@/components/ui/card";
import { AppLayout } from "@/layouts/app-layout";
import type { SharedProps } from "@/types";

export default function ProfileSettings({
    profile,
    workspace,
    mfa_available,
}: {
    profile: {
        username: string;
        display_name: string | null;
        timezone: string;
        locale: string;
        theme: string;
    };
    workspace: {
        uuid: string;
        name: string;
    };
    mfa_available: false;
}) {
    const base = usePage<SharedProps>().props.app.base_path;

    return (
        <AppLayout title="تنظیمات" description="تنظیمات نمایشی حساب، منطقه زمانی و پوسته رابط.">
            <Head title="تنظیمات" />
            <Card>
                <h2 className="text-lg font-semibold">{profile.display_name || profile.username}</h2>
                <dl className="mt-4 grid gap-3 sm:grid-cols-2">
                    <div>
                        <dt className="text-sm text-muted">نام کاربری</dt>
                        <dd className="font-mono" dir="ltr">
                            {profile.username}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-sm text-muted">فضای کاری</dt>
                        <dd>{workspace.name}</dd>
                    </div>
                    <div>
                        <dt className="text-sm text-muted">منطقه زمانی</dt>
                        <dd className="font-mono" dir="ltr">
                            {profile.timezone}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-sm text-muted">زبان ذخیره‌شده</dt>
                        <dd className="font-mono" dir="ltr">
                            {profile.locale}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-sm text-muted">پوسته ذخیره‌شده</dt>
                        <dd className="font-mono" dir="ltr">
                            {profile.theme}
                        </dd>
                    </div>
                </dl>
                <p className="mt-4 text-sm text-muted">پوسته را از نوار بالا عوض کنید. تاریخ‌های صفحه با همین منطقه زمانی به جلالی تبدیل می‌شوند.</p>
                <Link className="mt-3 inline-block text-sm text-accent" href={`${base}/password`}>
                    تغییر گذرواژه
                </Link>
            </Card>
            {mfa_available ? null : <Alert tone="info">راه‌اندازی MFA در این نسخه وجود ندارد و وضعیت فعال برای آن نشان داده نمی‌شود.</Alert>}
        </AppLayout>
    );
}
