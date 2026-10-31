import { PageSections } from '@/components/ui/page-sections';
import { Button } from '@/components/ui/button';
import { Head, Link, usePage } from "@inertiajs/react";
import { PersianDate } from "@/components/dates/persian-date";
import { ArchiveUpload } from "@/components/projects/archive-upload";
import { DockerProjectControls } from "@/components/projects/docker-project-controls";
import { Alert } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Breadcrumb, BreadcrumbItem, BreadcrumbLink, BreadcrumbList, BreadcrumbPage, BreadcrumbSeparator } from "@/components/ui/breadcrumb";
import { Card } from "@/components/ui/card";
import { EmptyState } from "@/components/ui/empty-state";
import { StatusBadge } from "@/components/ui/status-badge";
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

interface SourceArchiveRow {
    uuid: string;
    original_name: string;
    sha256: string;
    size_bytes: number;
    entry_count: number;
    uncompressed_bytes: number;
    status: string;
    created_at: string | null;
    deployment: {
        uuid: string;
        status: string;
        environment_name: string | null;
        operation_job_uuid: string | null;
    } | null;
}

export default function ProjectShow({ project, environments, source_archives, archive_upload, deploy_available, docker }: {
    project: {
        uuid: string;
        name: string;
        slug: string;
        status: string;
        declared_type: string;
        detected_type: string | null;
        default_branch: string;
        created_at: string | null;
        workspace_uuid: string | null;
    };
    environments: EnvironmentRow[];
    source_archives: SourceArchiveRow[];
    archive_upload: { available: boolean; max_bytes: number; execution_mode: string };
    deploy_available: false;
    docker: {
        can_request: boolean;
        execution_available: false;
        layouts: { id: string; label: string; detail: string }[];
    };
}) {
    const { app, enum_labels: labels } = usePage<SharedProps>().props;
    const base = app.base_path;

    return (
        <AppLayout title={project.name} description="مدیریت منبع کد، محیط‌ها و درخواست‌های استقرار ثبت‌شده پروژه." eyebrow="پروژه‌ها">
            <Head title={project.name} />
            <Breadcrumb>
                <BreadcrumbList>
                    <BreadcrumbItem><BreadcrumbLink asChild><Link href={`${base}/projects`}>پروژه‌ها</Link></BreadcrumbLink></BreadcrumbItem>
                    <BreadcrumbSeparator />
                    <BreadcrumbItem><BreadcrumbPage>{project.name}</BreadcrumbPage></BreadcrumbItem>
                </BreadcrumbList>
            </Breadcrumb>

            <PageSections label="مدیریت پروژه" sections={[
                { id: 'overview', label: 'نمای کلی', content: <>
            <Card>
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 className="text-2xl font-semibold">{project.name}</h2>
                        <p className="font-mono text-sm text-muted" dir="ltr">{project.slug}</p>
                    </div>
                    <StatusBadge value={project.status} />
                </div>
                <dl className="mt-4 grid gap-3 sm:grid-cols-2">
                    <div><dt className="text-sm text-muted">نوع اعلام‌شده</dt><dd dir="auto">{labelOf(project.declared_type, labels)}</dd></div>
                    {project.detected_type ? <div><dt className="text-sm text-muted">تشخیص اولیه</dt><dd dir="ltr" className="font-mono">{labelOf(project.detected_type, labels)}</dd></div> : null}
                    <div><dt className="text-sm text-muted">شاخه پیش‌فرض Git</dt><dd className="font-mono" dir="ltr">{project.default_branch}</dd></div>
                    <div><dt className="text-sm text-muted">ایجاد</dt><dd><PersianDate iso={project.created_at} /></dd></div>
                </dl>
                {deploy_available ? null : <Alert className="mt-4" tone="neutral">اجرای build و فعال‌سازی release هنوز به executor امن متصل نیست؛ عملیات ZIP فقط ثبت و قابل پیگیری می‌شود.</Alert>}
            </Card>

                    <Card><h2 className="font-semibold">قدم بعدی</h2><p className="mt-2 text-sm text-muted">منبع کد را آماده کنید، محیط مقصد را بررسی و نتیجه را در تاریخچه دنبال کنید.</p><div className="mt-4 flex flex-wrap gap-3"><Button asChild><a href="#source">انتخاب منبع کد</a></Button><Button asChild variant="secondary"><a href="#environments">بررسی محیط‌ها</a></Button><Button asChild variant="secondary"><Link href={`${base}/deployments`}>همهٔ استقرارها</Link></Button></div></Card>
                </> },
                { id: 'source', label: 'منبع کد', content: <>
            <ArchiveUpload basePath={base} projectUuid={project.uuid} environments={environments} available={archive_upload.available} maxBytes={archive_upload.max_bytes} executionMode={archive_upload.execution_mode} />

                </> },
                { id: 'docker', label: 'Docker', content: <DockerProjectControls base={base} projectUuid={project.uuid} docker={docker} /> },
                { id: 'environments', label: 'محیط‌ها', content: <>
            {environments.length === 0 ? (
                <EmptyState title="محیطی ثبت نشده است">محیط‌های این پروژه پس از ثبت واقعی اینجا دیده می‌شوند.</EmptyState>
            ) : (
                <Card>
                    <h2 className="text-lg font-semibold">محیط‌ها</h2>
                    <ul className="mt-3 space-y-2">
                        {environments.map((environment) => (
                            <li key={environment.uuid} className="flex flex-wrap items-center justify-between gap-2 border-t border-line py-2">
                                <span className="min-w-0">{environment.name}{" "}<span className="font-mono text-sm text-muted" dir="ltr">{environment.branch_name}</span></span>
                                <StatusBadge value={environment.status} />
                            </li>
                        ))}
                    </ul>
                </Card>
            )}
                </> },
                { id: 'history', label: 'تاریخچهٔ فایل‌ها', content: <>
            <Card>
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 className="text-lg font-semibold">آرشیوهای اخیر پروژه</h2>
                        <p className="mt-1 text-sm text-muted">فایل‌های دریافت‌شده و وضعیت درخواست استقرار هر نسخه.</p>
                    </div>
                    <Badge>{source_archives.length.toLocaleString("fa-IR")} مورد</Badge>
                </div>
                {source_archives.length === 0 ? (
                    <p className="mt-4 text-sm text-muted">هنوز فایل ZIP معتبری برای این پروژه ثبت نشده است.</p>
                ) : (
                    <ul className="mt-4 divide-y divide-line">
                        {source_archives.map((archive) => (
                            <li key={archive.uuid} className="py-4 first:pt-0 last:pb-0">
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div className="min-w-0">
                                        <p className="break-words font-medium">{archive.original_name}</p>
                                        <p className="mt-1 font-mono text-xs text-muted" dir="ltr">sha256:{archive.sha256}</p>
                                    </div>
                                    <StatusBadge value={archive.deployment?.status ?? archive.status} />
                                </div>
                                <div className="mt-3 flex flex-wrap gap-x-5 gap-y-2 text-xs text-muted">
                                    <span>{formatBytes(archive.size_bytes)} فایل فشرده</span>
                                    <span>{formatBytes(archive.uncompressed_bytes)} پس از بازشدن</span>
                                    <span>{archive.entry_count.toLocaleString("fa-IR")} ورودی</span>
                                    <PersianDate iso={archive.created_at} />
                                </div>
                                {archive.deployment ? (
                                    <div className="mt-3 flex flex-wrap items-center gap-2 text-sm">
                                        <span>محیط: {archive.deployment.environment_name ?? "نامشخص"}</span>
                                        {archive.deployment.operation_job_uuid ? <Link className="font-medium text-accent" href={`${base}/jobs/${archive.deployment.operation_job_uuid}`}>مشاهده عملیات</Link> : null}
                                    </div>
                                ) : null}
                            </li>
                        ))}
                    </ul>
                )}
            </Card>

                </> },
            ]} />
        </AppLayout>
    );
}

function formatBytes(bytes: number): string {
    const units = ["بایت", "KiB", "MiB", "GiB"];
    let value = bytes;
    let unitIndex = 0;
    while (value >= 1024 && unitIndex < units.length - 1) {
        value /= 1024;
        unitIndex += 1;
    }
    return `${new Intl.NumberFormat("fa-IR", { maximumFractionDigits: unitIndex === 0 ? 0 : 1 }).format(value)} ${units[unitIndex]}`;
}
