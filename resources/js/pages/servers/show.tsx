import { HostServices } from '@/components/servers/host-services';
import { ServerLocationCard, type ServerLocation } from '@/components/servers/server-location-card';
import { Button } from '@/components/ui/button';
import { Head, Link, usePage } from "@inertiajs/react";
import { PersianDate } from "@/components/dates/persian-date";
import { Alert } from "@/components/ui/alert";
import { Breadcrumb, BreadcrumbItem, BreadcrumbLink, BreadcrumbList, BreadcrumbPage, BreadcrumbSeparator } from "@/components/ui/breadcrumb";
import { Card } from "@/components/ui/card";
import { StatusBadge } from "@/components/ui/status-badge";
import { AppLayout } from "@/layouts/app-layout";
import type { SharedProps } from "@/types";

export default function ServerShow({
    host,
    monitoring_available,
    location,
    services,
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
    location: ServerLocation;
    services: {
        can_request: boolean;
        execution_available: false;
        node_note: string;
        items: { unit: string; label: string; actions: string[]; stop_blocked: string | null }[];
    };
}) {
    const base = usePage<SharedProps>().props.app.base_path;

    return (
        <AppLayout title={host.display_name} description="نمای کلی وضعیت ذخیره‌شده و آخرین مشاهده این میزبان محلی." eyebrow="سرورها">
            <Head title={host.display_name} />
            <Breadcrumb>
                <BreadcrumbList>
                    <BreadcrumbItem><BreadcrumbLink asChild><Link href={`${base}/servers`}>سرورها</Link></BreadcrumbLink></BreadcrumbItem>
                    <BreadcrumbSeparator />
                    <BreadcrumbItem><BreadcrumbPage>{host.display_name}</BreadcrumbPage></BreadcrumbItem>
                </BreadcrumbList>
            </Breadcrumb>
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
            <ServerLocationCard location={location} />
            <HostServices hostUuid={host.uuid} base={base} services={services} />
            <Card><h2 className="font-semibold">مدیریت میزبان</h2><p className="mt-2 text-sm text-muted">بخش موردنیاز را باز کنید؛ هر بخش وضعیت و محدودیت‌های واقعی خود را نشان می‌دهد.</p><div className="mt-4 grid gap-3 sm:grid-cols-2"><Button asChild variant="secondary"><Link href={`${base}/monitoring`}>منابع و سلامت سرویس‌ها</Link></Button><Button asChild variant="secondary"><Link href={`${base}/catalog`}>نرم‌افزارها و موتورهای اجرا</Link></Button><Button asChild variant="secondary"><Link href={`${base}/databases`}>دیتابیس‌های پروژه</Link></Button><Button asChild variant="secondary"><Link href={`${base}/jobs`}>عملیات و لاگ‌های میزبان</Link></Button></div></Card>
        </AppLayout>
    );
}
