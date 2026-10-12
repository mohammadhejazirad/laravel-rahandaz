import { Head } from "@inertiajs/react";
import { PersianDate } from "@/components/dates/persian-date";
import { EmptyState } from "@/components/ui/empty-state";
import { Pagination, type PageMeta } from "@/components/ui/pagination";
import { StatusBadge } from "@/components/ui/status-badge";
import { TableFrame } from "@/components/ui/table-frame";
import { AppLayout } from "@/layouts/app-layout";

interface AuditRow {
    uuid: string;
    action: string;
    resource_type: string | null;
    resource_id: string | null;
    result: string;
    recorded_at: string | null;
}

export default function AuditIndex({ events }: { events: { data: AuditRow[]; meta: PageMeta } }) {
    return (
        <AppLayout title="گزارش فعالیت" description="رخدادهای حسابرسی‌شده این فضای کاری، بدون نمایش راز یا فراداده حساس.">
            <Head title="گزارش فعالیت" />
            <p className="text-sm text-muted">متن رخداد بدون فراداده و بدون راز است. خروجی فایل از این صفحه ساخته نمی‌شود.</p>
            {events.data.length === 0 ? (
                <EmptyState title="رخدادی ثبت نشده است">زنجیره حسابرسی پس از ثبت رخداد واقعی اینجا دیده می‌شود.</EmptyState>
            ) : (
                <TableFrame label="گزارش فعالیت">
                    <thead className="bg-surface-muted text-muted">
                        <tr>
                            <th className="px-3 py-2 text-start font-medium">کنش</th>
                            <th className="px-3 py-2 text-start font-medium">منبع</th>
                            <th className="px-3 py-2 text-start font-medium">نتیجه</th>
                            <th className="px-3 py-2 text-start font-medium">زمان</th>
                        </tr>
                    </thead>
                    <tbody>
                        {events.data.map((event) => (
                            <tr key={event.uuid} className="border-t border-line">
                                <td className="px-3 py-2 font-mono" dir="ltr">
                                    {event.action}
                                </td>
                                <td className="px-3 py-2 font-mono text-xs" dir="ltr">
                                    {event.resource_type ?? "—"}
                                    {event.resource_id ? <div>{event.resource_id}</div> : null}
                                </td>
                                <td className="px-3 py-2">
                                    <StatusBadge value={event.result} />
                                </td>
                                <td className="px-3 py-2">
                                    <PersianDate iso={event.recorded_at} />
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </TableFrame>
            )}
            <Pagination meta={events.meta} />
        </AppLayout>
    );
}
