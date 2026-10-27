import { Head, Link, useForm, usePage } from '@inertiajs/react';
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
    const rows = [...(software.installed ?? [])].filter(item => item.verified).sort((left, right) => (software.catalog[left.package]?.name ?? left.package).localeCompare(software.catalog[right.package]?.name ?? right.package, 'fa'));

    return <AppLayout title="نصب‌شده‌ها" description="نسخهٔ تأییدشدهٔ هر نرم‌افزار و به‌روزرسانی همان شاخه.">
        <Head title="نصب‌شده‌ها" />
        <div className="space-y-4">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <p className="max-w-3xl text-sm leading-7 text-muted">هر ردیف نرم‌افزاری است که پنل نصب یا بررسی‌اش را تأیید کرده است. اگر PHP پنل از قبل روی سرور باشد، یک‌بار انتخاب همان نسخه در کاتالوگ فقط آن را بررسی می‌کند و جایگزینش نمی‌کند؛ بعد دکمهٔ به‌روزرسانی آخرین نسخهٔ جزئی همان شاخه را نصب می‌کند. تا پایان بررسی واقعی، همان دکمه در حال اجرا می‌ماند.</p>
                <Link className="text-sm text-primary underline" href={`${base}/catalog`}>نصب نرم‌افزار</Link>
            </div>
            <SoftwareProgress job={software.job} />
            {isSoftwarePending(software.active) && software.active?.uuid !== software.job?.uuid && <p className="text-sm leading-7 text-muted">یک عملیات نرم‌افزار دیگر در حال اجراست. پس از پایان آن می‌توانید به‌روزرسانی را شروع کنید.</p>}
            {rows.length === 0 ? <Card><p className="text-sm leading-7 text-muted">هنوز نرم‌افزاری از کاتالوگ نصب و تأیید نشده است.</p></Card> : <ul className="space-y-3">{rows.map(item => <InstalledRow key={item.package} item={item} software={software} pending={pending} />)}</ul>}
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
            <h2 className="font-semibold"><bdi dir="ltr">{meta?.name ?? item.package}</bdi></h2>
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
