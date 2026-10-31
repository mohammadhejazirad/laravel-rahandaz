import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { CatalogSoftwareIcon } from '@/components/catalog/catalog-icons';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Form } from '@/components/ui/form';
import { ExecutablePath, SoftwareProgress, type SoftwarePackageResult, type SoftwareState } from '@/components/catalog/software-picker';
import { AppLayout } from '@/layouts/app-layout';
import { isSoftwarePending, useSoftwareCompletion, useSoftwarePolling } from '@/lib/software-jobs';
import type { SharedProps } from '@/types';

export default function InstalledSoftware({ software }: { software: SoftwareState }) {
    const base = usePage<SharedProps>().props.app.base_path;
    const pending = isSoftwarePending(software.job) || isSoftwarePending(software.active);
    useSoftwarePolling(pending, ['software']);
    useSoftwareCompletion(software.job, null, 'upgrade');
    const rows: SoftwarePackageResult[] = Object.entries(software.inventory?.packages ?? {}).filter(([, item]) => item.state === 'installed').map(([key, item]) => {
        const previous = software.installed?.find(row => row.package === key && row.verified && row.version === item.version);
        return { package: key, version: item.version, verified: previous?.verified ?? false, changed: false, status: 'installed', executable_path: previous?.executable_path };
    }).sort((left, right) => (software.catalog[left.package]?.name ?? left.package).localeCompare(software.catalog[right.package]?.name ?? right.package, 'fa'));

    return <AppLayout title="نصب‌شده‌ها" description="نسخهٔ فعلی بسته‌های مشاهده‌شده روی میزبان و به‌روزرسانی همان شاخه.">
        <Head title="نصب‌شده‌ها" />
        <div className="space-y-4">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <p className="max-w-3xl text-sm leading-7 text-muted">این فهرست هنگام ورود از وضعیت فعلی بسته‌های میزبان خوانده می‌شود و نرم‌افزارهای نصب‌شده خارج از پنل را هم نشان می‌دهد. مشاهدهٔ بسته به معنی سلامت سرویس نیست. به‌روزرسانی فقط برای موارد مجاز کاتالوگ انجام می‌شود و نتیجه پس از پایان job مشخص خواهد شد.</p>
                <Link className="text-sm text-primary underline" href={`${base}/catalog`}>نصب نرم‌افزار</Link>
            </div>
            <SoftwareProgress job={software.job} />
            {isSoftwarePending(software.active) && software.active?.uuid !== software.job?.uuid && <p className="text-sm leading-7 text-muted">یک عملیات نرم‌افزار دیگر در حال اجراست. پس از پایان آن می‌توانید به‌روزرسانی را شروع کنید.</p>}
            {rows.length === 0 ? <Card><p className="text-sm leading-7 text-muted">نرم‌افزار نصب‌شده‌ای از کاتالوگ در وضعیت فعلی شناسایی نشد؛ این نتیجه به معنی نبود همهٔ نرم‌افزارها روی سرور نیست.</p></Card> : <ul className="space-y-3">{rows.map(item => <InstalledRow key={item.package} item={item} software={software} pending={pending} />)}</ul>}
        </div>
    </AppLayout>;
}

function InstalledRow({ item, software, pending }: { item: SoftwarePackageResult; software: SoftwareState; pending: boolean }) {
    const base = usePage<SharedProps>().props.app.base_path;
    const meta = software.catalog[item.package];
    const upgradeable = meta?.upgradeable === true;
    const runningHere = isSoftwarePending(software.job) && software.job?.packages.some(row => row.package === item.package) === true;
    const form = useForm({ package: item.package, idempotency_key: software.request_key });

    return <li><Card className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div className="min-w-0 space-y-2">
            <p className="text-xs text-success">نصب است · {item.verified ? 'سابقهٔ بررسی اجرای موفق دارد' : 'مشاهده از فهرست بسته‌های میزبان'}</p><h2 className="flex items-center gap-2 font-semibold"><CatalogSoftwareIcon software={meta?.product ?? item.package} category={meta?.category} /><bdi dir="ltr">{meta?.name ?? item.package}</bdi></h2>
            {item.version ? <p dir="ltr" className="break-all font-mono text-sm">{item.version}</p> : <p className="text-sm text-muted">نسخه در گزارش تأییدشده ثبت نشده است.</p>}
            {!upgradeable && <p className="text-sm leading-7 text-muted">{meta?.upgrade_note ?? 'به‌روزرسانی این مورد از پنل انجام نمی‌شود.'}</p>}
            {item.executable_path?.startsWith('/opt/rahandaz/') && <ExecutablePath path={item.executable_path} name={meta?.name ?? item.package} />}
        </div>
        <Form className="shrink-0" onSubmit={event => {
            event.preventDefault();
            if (!upgradeable) return;
            form.transform(data => ({ ...data, idempotency_key: software.request_key }));
            form.post(`${base}/catalog/software/upgrade`, { preserveScroll: true });
        }}>
            <Button type="submit" loading={form.processing || runningHere} loadingLabel={software.job?.status === 'queued' && runningHere ? 'در صف به‌روزرسانی…' : 'در حال به‌روزرسانی…'} disabled={!upgradeable || !software.available || !software.can_request || (pending && !runningHere)}>{upgradeable ? 'به‌روزرسانی' : 'به‌روزرسانی ندارد'}</Button>
            {form.errors.package && <p role="alert" className="mt-2 max-w-xs text-sm text-danger">{form.errors.package}</p>}
        </Form>
    </Card></li>;
}
