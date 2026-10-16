import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { FolderGit2 } from 'lucide-react';
import { PersianDate } from '@/components/dates/persian-date';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { EmptyState } from '@/components/ui/empty-state';
import { Form } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Pagination, type PageMeta } from '@/components/ui/pagination';
import { StatusBadge } from '@/components/ui/status-badge';
import { AppLayout } from '@/layouts/app-layout';
import { labelOf } from '@/lib/labels';
import type { SharedProps } from '@/types';

interface ProjectRow { uuid: string; name: string; slug: string; status: string; declared_type: string; environment_count: number; created_at: string | null }

export default function ProjectsIndex({ projects, create_available, filters }: { projects: { data: ProjectRow[]; meta: PageMeta }; create_available: false; filters: { q: string } }) {
    const { app, enum_labels: labels } = usePage<SharedProps>().props;
    const base = app.base_path;
    const search = useForm({ q: filters.q });
    return <AppLayout title="پروژه‌ها" description="پروژه را انتخاب کنید؛ منبع کد، محیط‌ها و تاریخچهٔ آن در یک صفحه در دسترس‌اند.">
        <Head title="پروژه‌ها" />
        <Card><Form className="flex flex-col gap-3 sm:flex-row sm:items-end" onSubmit={event => { event.preventDefault(); search.get(`${base}/projects`, { preserveState: true }); }}><div className="flex-1"><Input name="q" label="پیداکردن پروژه" placeholder="نام پروژه یا شناسهٔ متنی" maxLength={120} value={search.data.q} onChange={event => search.setData('q', event.target.value)} error={search.errors.q} /></div><Button type="submit" variant="secondary" loading={search.processing}>جست‌وجو</Button>{filters.q ? <Button asChild variant="ghost"><Link href={`${base}/projects`}>نمایش همه</Link></Button> : null}</Form></Card>
        <div className="flex flex-wrap items-center justify-between gap-3"><h2 className="font-semibold">{projects.meta.total.toLocaleString('fa-IR')} پروژه</h2><Button asChild variant="secondary"><Link href={`${base}/git`}>مدیریت حساب‌های گیت</Link></Button></div>
        {!create_available ? <Alert tone="neutral">ساخت پروژهٔ جدید هنوز فعال نیست. برای پروژه‌های موجود، دریافت فایل ZIP و پیگیری درخواست استقرار از جزئیات پروژه در دسترس است.</Alert> : null}
        {!projects.data.length ? <EmptyState title={filters.q ? 'پروژه‌ای با این نام پیدا نشد' : 'هنوز پروژه‌ای ثبت نشده است'} action={<Button asChild variant="secondary"><Link href={`${base}/${filters.q ? 'projects' : 'tutorials/projects-environments'}`}>{filters.q ? 'پاک‌کردن جست‌وجو' : 'راهنمای پروژه‌ها'}</Link></Button>}>{filters.q ? 'عبارت کوتاه‌تری جست‌وجو کنید.' : 'ابتدا راهنمای پروژه و محیط را ببینید؛ پروژه‌های ثبت‌شده اینجا نمایش داده می‌شوند.'}</EmptyState> : <ul className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">{projects.data.map(project => <li key={project.uuid} className="min-w-0"><Card className="flex h-full flex-col">
            <div className="flex items-start gap-3"><span className="grid size-10 shrink-0 place-items-center rounded-xl bg-accent-soft text-accent"><FolderGit2 className="size-5" aria-hidden="true" /></span><div className="min-w-0 flex-1"><Link href={`${base}/projects/${project.uuid}`} className="inline-flex min-h-11 items-center break-words font-semibold text-ink hover:text-accent">{project.name}</Link><p dir="ltr" className="break-all font-mono text-xs text-muted">{project.slug}</p></div></div>
            <div className="mt-4 flex flex-wrap items-center gap-2"><StatusBadge value={project.status} /><span className="rounded-lg bg-surface-muted px-2 py-1 text-xs text-muted">{labelOf(project.declared_type, labels)}</span></div>
            <dl className="my-4 grid grid-cols-2 gap-3 border-t border-line pt-4 text-xs"><div><dt className="text-muted">محیط‌ها</dt><dd className="mt-1">{project.environment_count.toLocaleString('fa-IR')}</dd></div><div><dt className="text-muted">تاریخ ایجاد</dt><dd className="mt-1"><PersianDate iso={project.created_at} /></dd></div></dl>
            <Button asChild variant="secondary" className="mt-auto w-full"><Link href={`${base}/projects/${project.uuid}`}>مدیریت پروژه</Link></Button>
        </Card></li>)}</ul>}
        <Pagination meta={projects.meta} />
    </AppLayout>;
}
