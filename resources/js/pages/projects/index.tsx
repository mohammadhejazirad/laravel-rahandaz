import { Head, Link, usePage } from "@inertiajs/react";
import { PersianDate } from "@/components/dates/persian-date";
import { Alert } from "@/components/ui/alert";
import { EmptyState } from "@/components/ui/empty-state";
import { Pagination, type PageMeta } from "@/components/ui/pagination";
import { StatusBadge } from "@/components/ui/status-badge";
import { TableFrame } from "@/components/ui/table-frame";
import { AppLayout } from "@/layouts/app-layout";
import { labelOf } from "@/lib/labels";
import type { SharedProps } from "@/types";

interface ProjectRow {
    uuid: string;
    name: string;
    slug: string;
    status: string;
    declared_type: string;
    environment_count: number;
    created_at: string | null;
}

export default function ProjectsIndex({
    projects,
    create_available,
}: {
    projects: { data: ProjectRow[]; meta: PageMeta };
    create_available: false;
}) {
    const { app, enum_labels: labels } = usePage<SharedProps>().props;
    const base = app.base_path;

    return (
        <AppLayout title="پروژه‌ها" description="پروژه‌های ثبت‌شده در فضای کاری و محیط‌های مستقل آن‌ها.">
            <Head title="پروژه‌ها" />
            {create_available ? null : (
                <Alert tone="neutral">ساخت پروژه از این صفحه هنوز آماده نیست؛ برای پروژه‌های موجود می‌توانید از صفحه جزئیات، فایل ZIP را انتخاب یا drag-and-drop کنید.</Alert>
            )}
            {projects.data.length === 0 ? (
                <EmptyState title="پروژه‌ای ثبت نشده است">وقتی پروژه‌ای در این فضای کاری ساخته شود، اینجا با شناسه عمومی دیده می‌شود.</EmptyState>
            ) : (
                <TableFrame label="فهرست پروژه‌ها">
                    <thead className="bg-surface-muted text-muted">
                        <tr>
                            <th className="px-3 py-2 text-start font-medium">نام</th>
                            <th className="px-3 py-2 text-start font-medium">نوع</th>
                            <th className="px-3 py-2 text-start font-medium">وضعیت</th>
                            <th className="px-3 py-2 text-start font-medium">محیط</th>
                            <th className="px-3 py-2 text-start font-medium">ایجاد</th>
                        </tr>
                    </thead>
                    <tbody>
                        {projects.data.map((project) => (
                            <tr key={project.uuid} className="border-t border-line">
                                <td className="px-3 py-2">
                                    <Link className="font-medium text-ink" href={`${base}/projects/${project.uuid}`}>
                                        {project.name}
                                    </Link>
                                    <div className="font-mono text-xs text-muted" dir="ltr">
                                        {project.slug}
                                    </div>
                                </td>
                                <td className="px-3 py-2" dir="auto">
                                    {labelOf(project.declared_type, labels)}
                                </td>
                                <td className="px-3 py-2">
                                    <StatusBadge value={project.status} />
                                </td>
                                <td className="px-3 py-2 font-mono" dir="ltr">
                                    {project.environment_count}
                                </td>
                                <td className="px-3 py-2">
                                    <PersianDate iso={project.created_at} />
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </TableFrame>
            )}
            <Pagination meta={projects.meta} />
        </AppLayout>
    );
}
