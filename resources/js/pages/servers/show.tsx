import { Head, Link, usePage } from "@inertiajs/react";
import { PersianDate } from "@/components/dates/persian-date";
import { Alert } from "@/components/ui/alert";
import { Card } from "@/components/ui/card";
import { StatusBadge } from "@/components/ui/status-badge";
import { AppLayout } from "@/layouts/app-layout";
import type { SharedProps } from "@/types";

export default function ServerShow({
    host,
    monitoring_available,
}: {
    host: {
        uuid: string;
        slug: string;
        display_name: string;
        hostname: string | null;
        state: string;
        compatibility: string;
        last_seen_at: string | null;
    };
    monitoring_available: false;
}) {
    const base = usePage<SharedProps>().props.app.base_path;

    return (
        <AppLayout title={host.display_name}>
            <Head title={host.display_name} />
            <p className="text-sm">
                <Link className="text-accent" href={`${base}/servers`}>
                    سرورها
                </Link>
            </p>
            {monitoring_available ? null : (
                <Alert tone="info">نمودار CPU و RAM، شبکه، فایروال و کنسول برای این میزبان وصل نیست. فقط فیلدهای ذخیره‌شده نشان داده می‌شود.</Alert>
            )}
            <Card>
                <h2 className="text-2xl font-semibold">{host.display_name}</h2>
                <p className="font-mono text-sm text-muted" dir="ltr">
                    {host.slug}
                </p>
                <dl className="mt-4 grid gap-3 sm:grid-cols-2">
                    <div>
                        <dt className="text-sm text-muted">نام میزبان</dt>
                        <dd className="font-mono" dir="ltr">
                            {host.hostname ?? "ثبت نشده"}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-sm text-muted">وضعیت</dt>
                        <dd>
                            <StatusBadge value={host.state} />
                        </dd>
                    </div>
                    <div>
                        <dt className="text-sm text-muted">سازگاری</dt>
                        <dd>
                            <StatusBadge value={host.compatibility} />
                        </dd>
                    </div>
                    <div>
                        <dt className="text-sm text-muted">آخرین مشاهده</dt>
                        <dd>
                            <PersianDate iso={host.last_seen_at} />
                        </dd>
                    </div>
                </dl>
            </Card>
        </AppLayout>
    );
}
