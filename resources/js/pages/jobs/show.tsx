import { Head, Link, usePage } from "@inertiajs/react";
import { PersianDate } from "@/components/dates/persian-date";
import { Alert } from "@/components/ui/alert";
import { Card } from "@/components/ui/card";
import { EmptyState } from "@/components/ui/empty-state";
import { Pagination, type PageMeta } from "@/components/ui/pagination";
import { StatusBadge } from "@/components/ui/status-badge";
import { AppLayout } from "@/layouts/app-layout";
import type { SharedProps } from "@/types";

interface LogRow {
    sequence: number;
    level: string;
    step_key: string | null;
    message: string;
    recorded_at: string | null;
}

export default function JobShow({
    job,
    logs,
    cancel_available,
}: {
    job: {
        uuid: string;
        type: string;
        status: string;
        attempt: number;
        cancel_requested: boolean;
        error_code: string | null;
        created_at: string | null;
        finished_at: string | null;
    };
    logs: { data: LogRow[]; meta: PageMeta };
    cancel_available: false;
}) {
    const base = usePage<SharedProps>().props.app.base_path;

    return (
        <AppLayout title="جزئیات عملیات">
            <Head title="جزئیات عملیات" />
            <p className="text-sm">
                <Link className="text-accent" href={`${base}/jobs`}>
                    عملیات
                </Link>
            </p>
            <Card>
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h2 className="font-mono text-xl" dir="ltr">
                        {job.type}
                    </h2>
                    <StatusBadge value={job.status} />
                </div>
                <dl className="mt-4 grid gap-3 sm:grid-cols-2">
                    <div>
                        <dt className="text-sm text-muted">تلاش</dt>
                        <dd className="font-mono" dir="ltr">
                            {job.attempt}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-sm text-muted">ثبت</dt>
                        <dd>
                            <PersianDate iso={job.created_at} />
                        </dd>
                    </div>
                    <div>
                        <dt className="text-sm text-muted">پایان</dt>
                        <dd>
                            <PersianDate iso={job.finished_at} />
                        </dd>
                    </div>
                    {job.error_code ? (
                        <div>
                            <dt className="text-sm text-muted">کد خطا</dt>
                            <dd className="font-mono text-danger" dir="ltr">
                                {job.error_code}
                            </dd>
                        </div>
                    ) : null}
                </dl>
                {job.cancel_requested ? <p className="mt-3 text-sm text-warning">درخواست لغو ثبت شده است. این به‌معنی توقف اثبات‌شده فرایند نیست.</p> : null}
                {cancel_available ? null : <p className="mt-3 text-sm text-muted">دکمه لغو اینجا نیست، چون نقطه امن لغو هنوز پیاده‌سازی نشده است.</p>}
            </Card>
            {logs.data.length === 0 ? (
                <EmptyState title="لاگی ثبت نشده است">خروجی این عملیات پس از نوشته شدن در پایگاه اینجا می‌آید.</EmptyState>
            ) : (
                <ol className="space-y-2" aria-label="لاگ عملیات">
                    {logs.data.map((log) => (
                        <li key={log.sequence} className="rounded-lg border border-line bg-surface px-3 py-2">
                            <div className="flex flex-wrap items-center justify-between gap-2 text-xs text-muted">
                                <span className="font-mono" dir="ltr">
                                    #{log.sequence} {log.level}
                                    {log.step_key ? ` ${log.step_key}` : ""}
                                </span>
                                <PersianDate iso={log.recorded_at} />
                            </div>
                            <pre className="mt-1 whitespace-pre-wrap font-mono text-sm text-ink" dir="ltr">
                                {log.message}
                            </pre>
                        </li>
                    ))}
                </ol>
            )}
            <Pagination meta={logs.meta} />
            <Alert tone="neutral">به‌روزرسانی خودکار لاگ وصل نیست. برای دیدن ردیف‌های جدید صفحه را دوباره باز کنید.</Alert>
        </AppLayout>
    );
}
