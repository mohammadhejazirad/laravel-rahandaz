import { Head, Link, usePage } from '@inertiajs/react';
import { Activity, ArrowUpLeft, CircleX, Clock3, FolderGit2, Server, type LucideIcon } from 'lucide-react';
import { PersianDate } from '@/components/dates/persian-date';
import { UpdateAvailableCard } from '@/components/updates/UpdateAvailableCard';
import type { ReleaseStatus } from '@/components/updates/update-status';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { EmptyState } from '@/components/ui/empty-state';
import { StatusBadge } from '@/components/ui/status-badge';
import { AppLayout } from '@/layouts/app-layout';
import { labelOf } from '@/lib/labels';
import type { SharedProps } from '@/types';

interface DashboardPageProps {
    workspace: { uuid: string; name: string; slug: string };
    execution_mode: string;
    operations: { queued: number; running: number; failed: number };
    inventory: { projects: number; servers: number; metric_samples: number };
    recent_projects: { uuid: string; name: string; slug: string; status: string; declared_type: string; environment_count: number }[];
    recent_jobs: { uuid: string; type: string; status: string; created_at: string | null }[];
    release: ReleaseStatus;
}

export default function Dashboard({ workspace, execution_mode, operations, inventory, recent_projects, recent_jobs, release }: DashboardPageProps) {
    const { app, enum_labels: labels } = usePage<SharedProps>().props;
    const base = app.base_path;
    return <AppLayout title="داشبورد" description="پروژه‌ها، کارهای در جریان و قدم بعدی شما در یک نگاه.">
        <Head title="داشبورد" />
        <Card className="border-accent/20 bg-accent-soft/30">
            <div className="flex flex-wrap items-center justify-between gap-4"><div><p className="text-xs text-muted">فضای کاری شما</p><h2 className="mt-1 text-xl font-bold">{workspace.name}</h2><p className="mt-2 text-sm text-muted">{execution_mode === 'disabled' ? 'پنل آمادهٔ مدیریت داده‌هاست؛ اجرای میزبان هنوز غیرفعال است.' : 'نتیجهٔ درخواست‌ها را در عملیات و لاگ‌ها دنبال کنید.'}</p></div><Button asChild variant="secondary"><Link href={`${base}/setup`}>بررسی راه‌اندازی</Link></Button></div>
        </Card>
        <div className="grid grid-cols-2 gap-3 xl:grid-cols-4">
            <Metric icon={FolderGit2} label="پروژه‌ها" value={inventory.projects} href={`${base}/projects`} />
            <Metric icon={Clock3} label="در صف" value={operations.queued} href={`${base}/jobs?status=queued`} />
            <Metric icon={Activity} label="در حال اجرا" value={operations.running} href={`${base}/jobs?status=running`} />
            <Metric icon={CircleX} label="نیازمند بررسی" value={operations.failed} href={`${base}/jobs?status=failed`} danger={operations.failed > 0} />
        </div>
        <div className="grid items-start gap-5 xl:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
            <div className="space-y-5">
                <Card><div className="mb-4 flex flex-wrap items-center justify-between gap-3"><h2 className="font-semibold">آخرین پروژه‌ها</h2><Link href={`${base}/projects`} className="inline-flex min-h-11 items-center gap-1 text-sm text-accent">همهٔ پروژه‌ها<ArrowUpLeft className="size-4" aria-hidden="true" /></Link></div>
                    {!recent_projects.length ? <EmptyState title="پروژه‌هایتان را از اینجا مدیریت کنید" action={<Button asChild variant="secondary"><Link href={`${base}/tutorials/projects-environments`}>آشنایی با پروژه و محیط</Link></Button>}>پس از ثبت پروژه، منبع کد و محیط‌های آن در دسترس خواهند بود.</EmptyState> : <ul className="grid gap-3 sm:grid-cols-2">{recent_projects.map(project => <li key={project.uuid} className="rounded-xl border border-line p-4"><Link href={`${base}/projects/${project.uuid}`} className="inline-flex min-h-11 items-center break-words font-semibold hover:text-accent">{project.name}</Link><p dir="ltr" className="break-all font-mono text-xs text-muted">{project.slug}</p><div className="mt-4 flex flex-wrap items-center gap-2"><StatusBadge value={project.status} /><span className="text-xs text-muted">{labelOf(project.declared_type, labels)} · {project.environment_count.toLocaleString('fa-IR')} محیط</span></div></li>)}</ul>}
                </Card>
                <UpdateAvailableCard release={release} detailsHref={`${base}/settings/updates`} />
                <Card><h2 className="font-semibold">میزبان و مشاهده‌ها</h2><div className="mt-4 grid gap-3 sm:grid-cols-2"><Link href={`${base}/servers`} className="flex min-h-16 items-center gap-3 rounded-xl bg-surface-muted p-3"><Server className="size-5 text-accent" aria-hidden="true" /><span className="text-sm">{inventory.servers.toLocaleString('fa-IR')} میزبان ثبت‌شده</span></Link><Link href={`${base}/monitoring`} className="flex min-h-16 items-center gap-3 rounded-xl bg-surface-muted p-3"><Activity className="size-5 text-accent" aria-hidden="true" /><span className="text-sm">{inventory.metric_samples.toLocaleString('fa-IR')} نمونهٔ پایش</span></Link></div><p className="mt-3 text-xs leading-6 text-muted">زمان آخرین مشاهده و تازگی داده را در صفحهٔ میزبان یا پایش بررسی کنید.</p></Card>
            </div>
            <div className="space-y-5">
                <Card><h2 className="font-semibold">از کجا شروع کنم؟</h2><ol className="mt-4 space-y-3">{[
                    ['setup', 'بررسی میزبان', 'مشخصات همین سرور را ببینید.'],
                    ['git', 'اتصال حساب گیت', 'حساب و مخزن‌های دلخواه را انتخاب کنید.'],
                    ['projects', 'مدیریت پروژه', 'منبع کد و محیط پروژه را بررسی کنید.'],
                    ['jobs', 'پیگیری نتیجه', 'مرحله‌ها، زمان‌ها و خطاها را ببینید.'],
                ].map(([path, title, description], index) => <li key={path}><Link href={`${base}/${path}`} className="flex items-start gap-3 rounded-xl p-2 hover:bg-surface-muted"><span className="grid size-7 shrink-0 place-items-center rounded-full bg-accent-soft text-xs text-accent">{(index + 1).toLocaleString('fa-IR')}</span><span><span className="block text-sm font-semibold">{title}</span><span className="mt-1 block text-xs leading-6 text-muted">{description}</span></span></Link></li>)}</ol></Card>
                <Card><div className="flex items-center justify-between gap-3"><h2 className="font-semibold">آخرین عملیات</h2><Link href={`${base}/jobs`} className="inline-flex min-h-11 items-center text-xs text-accent">مشاهدهٔ همه</Link></div>{!recent_jobs.length ? <p className="py-6 text-sm text-muted">هنوز عملیاتی ثبت نشده است.</p> : <ol className="mt-2 divide-y divide-line">{recent_jobs.map(job => <li key={job.uuid} className="py-3"><Link dir="ltr" href={`${base}/jobs/${job.uuid}`} className="block break-all py-2 font-mono text-xs text-accent">{job.type}</Link><div className="flex flex-wrap items-center justify-between gap-2"><StatusBadge value={job.status} /><span className="text-[11px] text-muted"><PersianDate iso={job.created_at} /></span></div></li>)}</ol>}</Card>
            </div>
        </div>
    </AppLayout>;
}

function Metric({ icon: Icon, label, value, href, danger = false }: { icon: LucideIcon; label: string; value: number; href: string; danger?: boolean }) {
    return <Link href={href} className={`rounded-2xl border bg-surface p-4 hover:border-accent/40 focus-visible:outline-accent ${danger ? 'border-danger/30' : 'border-line'}`}><div className="flex flex-wrap items-center justify-between gap-2"><span className="text-xs text-muted">{label}</span><Icon className={`size-4 ${danger ? 'text-danger' : 'text-accent'}`} aria-hidden="true" /></div><p className="mt-3 text-2xl font-bold">{value.toLocaleString('fa-IR')}</p></Link>;
}
