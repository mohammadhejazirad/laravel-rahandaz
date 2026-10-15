import { Head, Link, usePage } from "@inertiajs/react";
import { UserRound } from "lucide-react";
import { Alert } from "@/components/ui/alert";
import { Avatar, AvatarFallback } from "@/components/ui/avatar";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { AppLayout } from "@/layouts/app-layout";
import { labelOf } from "@/lib/labels";
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
    const { app, enum_labels: labels } = usePage<SharedProps>().props;
    const base = app.base_path;

    return (
        <AppLayout title="تنظیمات" description="تنظیمات نمایشی حساب، منطقه زمانی و پوسته رابط.">
            <Head title="تنظیمات" />
            <Card>
                <div className="flex items-center gap-3 border-b border-line pb-5">
                    <Avatar size="lg">
                        <AvatarFallback>
                            {(profile.display_name || profile.username)
                                .trim()
                                .charAt(0)
                                .toLocaleUpperCase("fa-IR") || (
                                <UserRound className="size-5" aria-hidden="true" />
                            )}
                        </AvatarFallback>
                    </Avatar>
                    <div>
                        <h2 className="text-lg font-semibold">
                            {profile.display_name || profile.username}
                        </h2>
                        <p className="text-sm text-muted">حساب فعال در {workspace.name}</p>
                    </div>
                </div>
                <dl className="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    <div className="rounded-xl bg-surface-muted/75 p-3.5">
                        <dt className="text-sm text-muted">نام کاربری</dt>
                        <dd className="mt-1 font-mono text-sm" dir="ltr">
                            {profile.username}
                        </dd>
                    </div>
                    <div className="rounded-xl bg-surface-muted/75 p-3.5">
                        <dt className="text-sm text-muted">فضای کاری</dt>
                        <dd className="mt-1 text-sm font-medium">{workspace.name}</dd>
                    </div>
                    <div className="rounded-xl bg-surface-muted/75 p-3.5">
                        <dt className="text-sm text-muted">شناسه فضای کاری</dt>
                        <dd className="mt-1 font-mono text-xs" dir="ltr">
                            {workspace.uuid}
                        </dd>
                    </div>
                    <div className="rounded-xl bg-surface-muted/75 p-3.5">
                        <dt className="text-sm text-muted">منطقه زمانی</dt>
                        <dd className="mt-1 font-mono text-sm" dir="ltr">
                            {profile.timezone}
                        </dd>
                    </div>
                    <div className="rounded-xl bg-surface-muted/75 p-3.5">
                        <dt className="text-sm text-muted">زبان ذخیره‌شده</dt>
                        <dd className="mt-1 font-mono text-sm" dir="ltr">
                            {profile.locale}
                        </dd>
                    </div>
                    <div className="rounded-xl bg-surface-muted/75 p-3.5">
                        <dt className="text-sm text-muted">پوسته ذخیره‌شده</dt>
                        <dd className="mt-1 text-sm font-medium">
                            {labelOf(profile.theme, labels)}
                        </dd>
                    </div>
                </dl>
                <div className="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-line pt-5">
                    <p className="max-w-2xl text-sm leading-7 text-muted">
                        با هر کلیک روی دکمه پوسته در نوار بالا، حالت به‌ترتیب
                        روشن، تیره و سیستم تغییر می‌کند. تاریخ‌ها با منطقه زمانی ذخیره‌شده
                        به جلالی نمایش داده می‌شوند.
                    </p>
                    <Button asChild variant="secondary">
                        <Link href={`${base}/password`}>تغییر گذرواژه</Link>
                    </Button>
                </div>
            </Card>
            {mfa_available ? null : (
                <Alert tone="info">
                    راه‌اندازی MFA در این نسخه وجود ندارد و وضعیت فعال برای آن نشان داده نمی‌شود.
                </Alert>
            )}
        </AppLayout>
    );
}
