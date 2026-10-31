import { SoftwareProgress, type SoftwareJob } from '@/components/catalog/software-picker';
import { useSoftwareCompletion } from '@/lib/software-jobs';
import { ReloadButton } from '@/components/ui/request-button';
import { Button } from '@/components/ui/button';
import { useVisiblePoll } from '@/hooks/use-visible-poll';
import { Head, Link, usePage } from "@inertiajs/react";
import { PersianDate } from "@/components/dates/persian-date";
import { Breadcrumb, BreadcrumbItem, BreadcrumbLink, BreadcrumbList, BreadcrumbPage, BreadcrumbSeparator } from "@/components/ui/breadcrumb";
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
    software,
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
    software: SoftwareJob | null;
}) {
    const base = usePage<SharedProps>().props.app.base_path;

    const active = !['failed', 'succeeded', 'cancelled'].includes(job.status);
    useVisiblePoll(active, 'job,logs,software', 5000);
    useSoftwareCompletion(software);

    return (
        <AppLayout title="جزئیات عملیات" description="وضعیت، زمان‌بندی و خروجی پالایش‌شده این Job." eyebrow="عملیات">
            <Head title="جزئیات عملیات" />
            <Breadcrumb>
                <BreadcrumbList>
                    <BreadcrumbItem><BreadcrumbLink asChild><Link href={`${base}/jobs`}>عملیات</Link></BreadcrumbLink></BreadcrumbItem>
                    <BreadcrumbSeparator />
                    <BreadcrumbItem><BreadcrumbPage>{job.type}</BreadcrumbPage></BreadcrumbItem>
                </BreadcrumbList>
            </Breadcrumb>
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
            {software && <Card><SoftwareProgress job={software} /></Card>}
            {logs.data.length === 0 ? (
                <EmptyState title="لاگی ثبت نشده است">خروجی این عملیات پس از نوشته شدن در پایگاه اینجا می‌آید.</EmptyState>
            ) : (
                <ol id="operation-logs" className="space-y-2" aria-label="لاگ عملیات">
                    {logs.data.map((log) => (
                        <li key={log.sequence} className="rounded-lg border border-line bg-surface px-3 py-2">
                            <div className="flex flex-wrap items-center justify-between gap-2 text-xs text-muted">
                                <span className="font-mono" dir="ltr">
                                    #{log.sequence} {log.level}
                                    {log.step_key ? ` ${log.step_key}` : ""}
                                </span>
                                <PersianDate iso={log.recorded_at} />
                            </div>
                            <pre className="mt-1 whitespace-pre-wrap break-words text-sm leading-7 text-ink" dir="auto">
                                {log.message}
                            </pre>
                        </li>
                    ))}
                </ol>
            )}
            <div className="flex flex-wrap items-center justify-between gap-3"><h2 className="text-sm font-semibold">لاگ‌ها از قدیم به جدید · صفحهٔ {logs.meta.current_page.toLocaleString('fa-IR')}</h2>{logs.meta.current_page < logs.meta.last_page ? <Button asChild variant="secondary"><Link href={`${base}/jobs/${job.uuid}?page=${logs.meta.last_page}#operation-logs`}>رفتن به آخرین لاگ‌ها</Link></Button> : null}</div>
            <Pagination meta={logs.meta} />
            <div className="flex flex-wrap items-center justify-between gap-3"><p className="text-xs text-muted">{active ? 'تا پایان عملیات، لاگ‌های همین صفحه هنگام بازبودن پنجره هر ۵ ثانیه تازه می‌شوند.' : 'عملیات در وضعیت پایانی است؛ دریافت دوره‌ای متوقف شده است.'}</p><ReloadButton only={['job', 'logs', 'software']}>تازه‌سازی لاگ‌ها</ReloadButton></div>
        </AppLayout>
    );
}
