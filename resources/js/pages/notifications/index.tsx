import { Head, Link, router, usePage } from "@inertiajs/react";
import { useState } from "react";
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
    const [reading, setReading] = useState<string | null>(null);

    function markRead(path: string, key: string, errorMessage: string) {
        setReading(key);
        router.post(path, {}, {
            preserveScroll: true,
            onError: () => notify.error(errorMessage),
            onFinish: () => setReading(null),
        });
    }

    return (
        <AppLayout title="اعلان‌ها" description="پیام‌های پایدار ثبت‌شده در پایگاه؛ Toastهای موقت جایگزین این فهرست نیستند.">
            <Head title="اعلان‌ها" />
            {unread > 0 ? (
                <div className="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-accent/20 bg-accent-soft/60 p-4">
                    <div>
                        <p className="font-semibold text-ink">
                            {unread.toLocaleString("fa-IR")} اعلان خوانده‌نشده
                        </p>
                        <p className="mt-1 text-sm text-muted">
                            اعلان‌های پایدار تا زمان ثبت خواندن در این فهرست می‌مانند.
                        </p>
                    </div>
                    <Button
                        type="button"
                        variant="secondary"
                        loading={reading === "all"}
                        loadingLabel="در حال ثبت"
                        disabled={reading !== null}
                        onClick={() =>
                            markRead(
                                `${base}/notifications/read-all`,
                                "all",
                                "علامت‌زدن اعلان‌ها انجام نشد.",
                            )
                        }
                    >
                        خواندن همه
                    </Button>
                </div>
            ) : null}
            {notifications.data.length === 0 ? (
                <EmptyState title="اعلانی ندارید">اعلان پایدار فقط وقتی در پایگاه برای شما ثبت شود اینجا می‌آید. Toast جای آن را نمی‌گیرد.</EmptyState>
            ) : (
                <ul className="space-y-3">
                    {notifications.data.map((item) => (
                        <li
                            key={item.uuid}
                            className={
                                item.read_at
                                    ? "rounded-2xl border border-line bg-surface/96 p-4 sm:p-5"
                                    : "rounded-2xl border border-accent/25 bg-accent-soft/35 p-4 shadow-sm sm:p-5"
                            }
                        >
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <div className="flex items-center gap-2">
                                    {item.read_at ? null : (
                                        <span
                                            className="size-2 rounded-full bg-accent"
                                            aria-label="خوانده‌نشده"
                                        />
                                    )}
                                    <h2 className="font-semibold">{item.title}</h2>
                                </div>
                                <StatusBadge value={item.level} />
                            </div>
                            <p className="mt-2 text-sm leading-7 text-ink">{item.body}</p>
                            <div className="mt-4 flex flex-wrap items-center gap-2 text-sm text-muted">
                                <PersianDate iso={item.created_at} />
                                {item.read_at ? (
                                    <span className="rounded-full bg-surface-muted px-2.5 py-1 text-xs">
                                        خوانده‌شده در <PersianDate iso={item.read_at} />
                                    </span>
                                ) : (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        loading={reading === item.uuid}
                                        loadingLabel="در حال ثبت"
                                        disabled={reading !== null}
                                        onClick={() =>
                                            markRead(
                                                `${base}/notifications/${item.uuid}/read`,
                                                item.uuid,
                                                "علامت‌زدن اعلان انجام نشد.",
                                            )
                                        }
                                    >
                                        خواندم
                                    </Button>
                                )}
                                {item.action_path ? (
                                    <Button asChild variant="ghost" size="sm">
                                        <Link href={item.action_path}>مشاهده جزئیات</Link>
                                    </Button>
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
