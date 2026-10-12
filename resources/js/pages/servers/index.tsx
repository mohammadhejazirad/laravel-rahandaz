import { Head, Link, usePage } from "@inertiajs/react";
import { PersianDate } from "@/components/dates/persian-date";
import { EmptyState } from "@/components/ui/empty-state";
import { Pagination, type PageMeta } from "@/components/ui/pagination";
import { StatusBadge } from "@/components/ui/status-badge";
import { TableFrame } from "@/components/ui/table-frame";
import { AppLayout } from "@/layouts/app-layout";
import type { SharedProps } from "@/types";

interface HostRow {
    uuid: string;
    slug: string;
    display_name: string;
    state: string;
    compatibility: string;
    last_seen_at: string | null;
}

export default function ServersIndex({ hosts }: { hosts: { data: HostRow[]; meta: PageMeta } }) {
    const base = usePage<SharedProps>().props.app.base_path;

    return (
        <AppLayout title="سرورها">
            <Head title="سرورها" />
            <p className="text-sm text-muted">نسخه اول فقط میزبان محلی همین نصب را می‌شناسد. افزودن سرور راه‌دور اینجا نیست.</p>
            {hosts.data.length === 0 ? (
                <EmptyState title="میزبانی ثبت نشده است">راه‌اندازی فقط خواندنی است و از این صفحه میزبان ساخته نمی‌شود.</EmptyState>
            ) : (
                <TableFrame>
                    <thead className="bg-surface-muted text-muted">
                        <tr>
                            <th className="px-3 py-2 text-start font-medium">نام</th>
                            <th className="px-3 py-2 text-start font-medium">وضعیت</th>
                            <th className="px-3 py-2 text-start font-medium">سازگاری</th>
                            <th className="px-3 py-2 text-start font-medium">آخرین مشاهده</th>
                        </tr>
                    </thead>
                    <tbody>
                        {hosts.data.map((host) => (
                            <tr key={host.uuid} className="border-t border-line">
                                <td className="px-3 py-2">
                                    <Link className="font-medium" href={`${base}/servers/${host.uuid}`}>
                                        {host.display_name}
                                    </Link>
                                    <div className="font-mono text-xs text-muted" dir="ltr">
                                        {host.slug}
                                    </div>
                                </td>
                                <td className="px-3 py-2">
                                    <StatusBadge value={host.state} />
                                </td>
                                <td className="px-3 py-2">
                                    <StatusBadge value={host.compatibility} />
                                </td>
                                <td className="px-3 py-2">
                                    <PersianDate iso={host.last_seen_at} />
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </TableFrame>
            )}
            <Pagination meta={hosts.meta} />
        </AppLayout>
    );
}
