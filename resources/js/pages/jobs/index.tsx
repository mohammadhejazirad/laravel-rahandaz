import { Head, Link, usePage } from "@inertiajs/react";
import { PersianDate } from "@/components/dates/persian-date";
import { EmptyState } from "@/components/ui/empty-state";
import { Pagination, type PageMeta } from "@/components/ui/pagination";
import { StatusBadge } from "@/components/ui/status-badge";
import { TableFrame } from "@/components/ui/table-frame";
import { AppLayout } from "@/layouts/app-layout";
import type { SharedProps } from "@/types";

interface JobRow {
    uuid: string;
    type: string;
    status: string;
    attempt: number;
    cancel_requested: boolean;
    error_code: string | null;
    created_at: string | null;
    finished_at: string | null;
}

export default function JobsIndex({ jobs }: { jobs: { data: JobRow[]; meta: PageMeta } }) {
    const base = usePage<SharedProps>().props.app.base_path;

    return (
        <AppLayout title="عملیات" description="وضعیت پایدار درخواست‌ها و Jobهای ثبت‌شده برای همین فضای کاری.">
            <Head title="عملیات" />
            {jobs.data.length === 0 ? (
                <EmptyState title="عملیاتی ثبت نشده است">عملیات‌های همین فضای کاری پس از ثبت واقعی اینجا فهرست می‌شوند.</EmptyState>
            ) : (
                <TableFrame label="فهرست عملیات">
                    <thead className="bg-surface-muted text-muted">
                        <tr>
                            <th className="px-3 py-2 text-start font-medium">نوع</th>
                            <th className="px-3 py-2 text-start font-medium">وضعیت</th>
                            <th className="px-3 py-2 text-start font-medium">ثبت</th>
                        </tr>
                    </thead>
                    <tbody>
                        {jobs.data.map((job) => (
                            <tr key={job.uuid} className="border-t border-line">
                                <td className="px-3 py-2">
                                    <Link className="font-mono" dir="ltr" href={`${base}/jobs/${job.uuid}`}>
                                        {job.type}
                                    </Link>
                                    {job.error_code ? (
                                        <div className="font-mono text-xs text-danger" dir="ltr">
                                            {job.error_code}
                                        </div>
                                    ) : null}
                                </td>
                                <td className="px-3 py-2">
                                    <StatusBadge value={job.status} />
                                </td>
                                <td className="px-3 py-2">
                                    <PersianDate iso={job.created_at} />
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </TableFrame>
            )}
            <Pagination meta={jobs.meta} />
        </AppLayout>
    );
}
