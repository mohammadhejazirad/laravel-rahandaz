import { Head, Link, usePage } from "@inertiajs/react";
import { PersianDate } from "@/components/dates/persian-date";
import { EmptyState } from "@/components/ui/empty-state";
import { StatusBadge } from "@/components/ui/status-badge";
import { Card } from "@/components/ui/card";
import { AppLayout } from "@/layouts/app-layout";
import { labelOf } from "@/lib/labels";
import type { SharedProps } from "@/types";

interface EnvironmentRow {
    uuid: string;
    name: string;
    slug: string;
    branch_name: string;
    status: string;
}

export default function ProjectShow({
    project,
    environments,
    deploy_available,
}: {
    project: {
        uuid: string;
        name: string;
        slug: string;
        status: string;
        declared_type: string;
        default_branch: string;
        created_at: string | null;
        workspace_uuid: string | null;
    };
    environments: EnvironmentRow[];
    deploy_available: false;
}) {
    const base = usePage<SharedProps>().props.app.base_path;

    return (
        <AppLayout title={project.name}>
            <Head title={project.name} />
            <p className="text-sm">
                <Link className="text-accent" href={`${base}/projects`}>
                    پروژه‌ها
                </Link>
            </p>
            <Card>
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 className="text-2xl font-semibold">{project.name}</h2>
                        <p className="font-mono text-sm text-muted" dir="ltr">
                            {project.slug}
                        </p>
                    </div>
                    <StatusBadge value={project.status} />
                </div>
                <dl className="mt-4 grid gap-3 sm:grid-cols-2">
                    <div>
                        <dt className="text-sm text-muted">نوع اعلام‌شده</dt>
                        <dd className="font-mono" dir="ltr">
                            {labelOf(project.declared_type)}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-sm text-muted">شاخه پیش‌فرض</dt>
                        <dd className="font-mono" dir="ltr">
                            {project.default_branch}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-sm text-muted">ایجاد</dt>
                        <dd>
                            <PersianDate iso={project.created_at} />
                        </dd>
                    </div>
                </dl>
                {deploy_available ? null : (
                    <p className="mt-4 text-sm text-muted">دکمه استقرار اینجا نیست، چون اجرای میزبان خاموش است و وضعیت موفق جعل نمی‌شود.</p>
                )}
            </Card>
            {environments.length === 0 ? (
                <EmptyState title="محیطی ثبت نشده است">محیط‌های این پروژه پس از ثبت واقعی اینجا دیده می‌شوند.</EmptyState>
            ) : (
                <Card>
                    <h2 className="text-lg font-semibold">محیط‌ها</h2>
                    <ul className="mt-3 space-y-2">
                        {environments.map((environment) => (
                            <li key={environment.uuid} className="flex flex-wrap items-center justify-between gap-2 border-t border-line py-2">
                                <span>
                                    {environment.name}{" "}
                                    <span className="font-mono text-sm text-muted" dir="ltr">
                                        {environment.branch_name}
                                    </span>
                                </span>
                                <StatusBadge value={environment.status} />
                            </li>
                        ))}
                    </ul>
                </Card>
            )}
        </AppLayout>
    );
}
