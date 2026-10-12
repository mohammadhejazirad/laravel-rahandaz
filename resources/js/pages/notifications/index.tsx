import { Head, Link, router, usePage } from "@inertiajs/react";
import { PersianDate } from "@/components/dates/persian-date";
import { Button } from "@/components/ui/button";
import { EmptyState } from "@/components/ui/empty-state";
import { Pagination, type PageMeta } from "@/components/ui/pagination";
import { StatusBadge } from "@/components/ui/status-badge";
import { AppLayout } from "@/layouts/app-layout";
import { notify } from "@/lib/notify";
import type { SharedProps } from "@/types";

interface NotificationRow {
    uuid: string;
    level: string;
    title: string;
    body: string;
    read_at: string | null;
    action_path: string | null;
    created_at: string | null;
}

export default function NotificationsIndex({
    notifications,
}: {
    notifications: { data: NotificationRow[]; meta: PageMeta };
}) {
    const base = usePage<SharedProps>().props.app.base_path;
    const unread = usePage<SharedProps>().props.notifications.unread;

    return (
        <AppLayout title="اعلان‌ها" description="پیام‌های پایدار ثبت‌شده در پایگاه؛ Toastهای موقت جایگزین این فهرست نیستند.">
            <Head title="اعلان‌ها" />
            {unread > 0 ? (
                <Button
                    type="button"
                    variant="secondary"
                    onClick={() =>
                        router.post(`${base}/notifications/read-all`, {}, {
                            onError: () => notify.error("علامت‌زدن اعلان‌ها انجام نشد."),
                        })
                    }
                >
                    علامت‌زدن همه به‌عنوان خوانده‌شده
                </Button>
            ) : null}
            {notifications.data.length === 0 ? (
                <EmptyState title="اعلانی ندارید">اعلان پایدار فقط وقتی در پایگاه برای شما ثبت شود اینجا می‌آید. Toast جای آن را نمی‌گیرد.</EmptyState>
            ) : (
                <ul className="space-y-3">
                    {notifications.data.map((item) => (
                        <li key={item.uuid} className="rounded-xl border border-line bg-surface p-4">
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <h2 className="font-semibold">{item.title}</h2>
                                <StatusBadge value={item.level} />
                            </div>
                            <p className="mt-2 text-sm">{item.body}</p>
                            <div className="mt-3 flex flex-wrap items-center gap-3 text-sm text-muted">
                                <PersianDate iso={item.created_at} />
                                {item.read_at ? (
                                    <span>
                                        خوانده‌شده در <PersianDate iso={item.read_at} />
                                    </span>
                                ) : (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        onClick={() =>
                                            router.post(`${base}/notifications/${item.uuid}/read`, {}, {
                                                onError: () => notify.error("علامت‌زدن اعلان انجام نشد."),
                                            })
                                        }
                                    >
                                        خواندم
                                    </Button>
                                )}
                                {item.action_path ? (
                                    <Link className="text-accent" href={item.action_path}>
                                        اقدام
                                    </Link>
                                ) : null}
                            </div>
                        </li>
                    ))}
                </ul>
            )}
            <Pagination meta={notifications.meta} />
        </AppLayout>
    );
}
