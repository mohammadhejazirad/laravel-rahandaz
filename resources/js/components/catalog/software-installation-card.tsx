import { Link, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { CatalogSoftwareIcon } from './catalog-icons';
import { ReloadButton } from '@/components/ui/request-button';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Form } from '@/components/ui/form';
import { isSoftwarePending, useSoftwarePolling, useSoftwareCompletion } from '@/lib/software-jobs';
import { ExecutablePath, SoftwarePicker, SoftwareProgress, type SoftwareState } from './software-picker';
import { secondDatabase } from '@/lib/database-install';
import { Alert } from '@/components/ui/alert';
import { Input } from '@/components/ui/input';
import type { SharedProps } from '@/types';

export function SoftwareInstallationCard({ software }: { software: SoftwareState }) {
    const page = usePage<SharedProps>();
    const base = page.props.app.base_path;
    const initialCategory = new URLSearchParams(page.url.split('?')[1] ?? '').get('category') ?? undefined;
    const form = useForm({ packages: [] as string[], package_versions: {} as Record<string, string>, listen_port: '', idempotency_key: software.request_key });
    const [review, setReview] = useState(false);
    const [submitted, setSubmitted] = useState<string | null>(null);
    const pending = isSoftwarePending(software.job) || isSoftwarePending(software.active);
    useSoftwarePolling(pending, ['software']);
    useSoftwareCompletion(software.job, submitted);
    const installedCount = Object.values(software.inventory?.packages ?? {}).filter(item => item.state === 'installed').length;
    const installedEngines = ['mysql', 'mariadb'].filter(key => software.inventory?.packages[key]?.state === 'installed');
    const second = secondDatabase(form.data.packages, installedEngines);
    const packageLabel = (key: string) => {
        const item = software.catalog[key];
        const picked = form.data.package_versions[key];
        const version = item?.versions?.find(row => row.id === picked)?.label ?? item?.version_label;
        const name = item?.product_name ?? item?.name ?? key;
        return version ? `${name} ${version}` : name;
    };
    return <Card className="space-y-6">
        <div className="grid gap-3 sm:grid-cols-3">{[['نرم‌افزار در کاتالوگ', Object.keys(software.catalog).length], ['نسخهٔ نصب‌شدهٔ مشاهده‌شده', installedCount], ['نسخه در فهرست نصب', form.data.packages.length]].map(([label, count]) => <div key={label} className="rounded-2xl border border-line bg-surface-muted p-4"><p className="text-2xl font-bold tabular-nums">{Number(count).toLocaleString('fa-IR')}</p><p className="mt-1 text-xs text-muted">{label}</p></div>)}</div>
        <div className="flex flex-wrap items-center justify-between gap-3"><h2 className="font-semibold">نصب نرم‌افزار روی سرور</h2><Link className="text-sm text-primary underline" href={`${base}/catalog/installed`}>نرم‌افزارهای نصب‌شده</Link></div>
        <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-surface-muted p-3"><p className="text-xs leading-6 text-muted">{software.inventory ? `بررسی وضعیت: ${new Date(software.inventory.checked_at).toLocaleString('fa-IR')} · مشاهدهٔ بسته‌ها به معنی سلامت سرویس نیست.` : 'وضعیت میزبان هنوز قابل مشاهده نیست.'}</p><ReloadButton only={['software']} loadingLabel="در حال بررسی…">بررسی دوبارهٔ وضعیت</ReloadButton></div>
        <SoftwareProgress job={software.job} />
        {software.active?.uuid !== software.job?.uuid && <SoftwareProgress job={software.active} />}
        {(software.installed ?? []).filter(item => item.verified && software.inventory?.packages[item.package]?.state === 'installed' && item.executable_path?.startsWith('/opt/rahandaz/')).map(item => <ExecutablePath key={item.package} path={item.executable_path!} name={software.catalog[item.package]?.name ?? item.package} />)}
        <p className="text-sm leading-7 text-muted">Chrome اختیاری است و فقط با انتخاب شما نصب می‌شود. مسیر تأییدشده را می‌توانید در کد یا تنظیمات پروژه کپی کنید؛ فایل محیطی پروژه خودکار تغییر نمی‌کند.</p>
        {!software.available && <p className="text-sm text-muted">سرویس نصب در دسترس نیست. سرویس‌های راه‌انداز را بررسی کنید.</p>}
        <Form className="space-y-4" onSubmit={event => {
            event.preventDefault();
            if (!form.data.packages.length || pending || !software.available || !software.can_request) return;
            if (!review) { setReview(true); return; }
            form.transform(data => ({ packages: data.packages, package_versions: data.package_versions, listen_port: second ? Number(data.listen_port) : null, idempotency_key: software.request_key }));
            form.post(`${base}/catalog/software/install`, { preserveScroll: true, onSuccess: page => { setSubmitted((page.props.software as SoftwareState | undefined)?.job?.uuid ?? null); setReview(false); form.setData({ ...form.data, packages: [], package_versions: {}, listen_port: '' }); } });
        }}>
            {!review ? <SoftwarePicker initialCategory={initialCategory} inventory={software.inventory} installed={software.installed} categories={software.categories} catalog={software.catalog} selected={form.data.packages} onChange={keys => form.setData('packages', keys)} onPick={(key, versionId) => form.setData('package_versions', { ...form.data.package_versions, [key]: versionId })} disabled={pending || !software.can_request || !software.available} /> : <div className="space-y-2 text-sm leading-7"><h3 className="font-semibold">بازبینی نصب</h3><p dir="ltr" className="break-words font-mono">{form.data.packages.map(packageLabel).join(', ')}</p><p className="text-muted">دریافت از مخزن معتبر سرور یا فایل نسخه‌دار با checksum ثابت و بررسی اجرای واقعی. نصب تازه بستهٔ موجود را ارتقا یا حذف نمی‌کند. نسخهٔ بدون بستهٔ تأییدشده ثبت نمی‌شود. پورت دیتابیس دوم فقط همراه درخواست ذخیره می‌شود و تا موفقیت job روی سرویس اعمال نمی‌شود.</p></div>}
            {form.data.packages.length > 0 && !review && <section aria-label="فهرست نصب گروهی" className="space-y-3 rounded-2xl border border-accent/30 bg-accent-soft p-4"><div className="flex flex-wrap items-center justify-between gap-2"><h3 className="font-semibold">فهرست نصب گروهی · {form.data.packages.length.toLocaleString('fa-IR')} نسخه</h3><Button type="button" variant="ghost" disabled={pending} onClick={() => form.setData('packages', [])}>پاک کردن فهرست</Button></div><ul className="flex flex-wrap gap-2">{form.data.packages.map(key => <li key={key} className="flex max-w-full items-center gap-2 rounded-xl border border-line bg-surface px-3 py-1"><CatalogSoftwareIcon software={software.catalog[key]?.product ?? key} category={software.catalog[key]?.category} className="size-4 shrink-0 text-accent" /><bdi dir="ltr" className="break-words font-mono text-xs">{packageLabel(key)}</bdi><Button type="button" variant="ghost" disabled={pending} aria-label={`حذف ${packageLabel(key)}`} onClick={() => form.setData('packages', form.data.packages.filter(value => value !== key))}>حذف</Button></li>)}</ul>{second ? <Alert tone="warning">MySQL و MariaDB با هم روی پورت ۳۳۰۶ نصب نمی‌شوند. پورت {second === 'mysql' ? 'MySQL' : 'MariaDB'} را خودتان انتخاب کنید؛ مثلاً ۳۳۰۷ یا ۳۳۰۸.</Alert> : null}{second ? <Input name="listen_port" label={`پورت ${second === 'mysql' ? 'MySQL' : 'MariaDB'}`} dir="ltr" className="font-mono" inputMode="numeric" isRequired value={form.data.listen_port} onChange={event => form.setData('listen_port', event.target.value)} error={form.errors.listen_port} help="۳۳۰۶ برای موتور دیگر می‌ماند." /> : null}<p className="text-xs leading-6 text-muted">انتخاب‌ها با تغییر تب حفظ می‌شوند. پس از بازبینی، همهٔ موارد در یک عملیات پس‌زمینه نصب و جداگانه بررسی می‌شوند.</p></section>}
            {(form.errors.packages || form.errors.package_versions) && <p role="alert" className="text-sm text-danger">{form.errors.packages || form.errors.package_versions}</p>}
            <div className="flex flex-wrap gap-3">{review && !pending && <Button type="button" variant="secondary" onClick={() => setReview(false)}>ویرایش انتخاب‌ها</Button>}<Button type="submit" loading={form.processing || pending} loadingLabel={software.job?.status === 'queued' ? 'در صف نصب…' : 'در حال نصب و بررسی…'} disabled={pending || !software.available || !software.can_request || form.data.packages.length === 0}>{review ? 'تأیید نهایی و نصب همه' : `بازبینی و نصب ${form.data.packages.length.toLocaleString('fa-IR')} نسخه`}</Button></div>
            {!software.can_request && <p className="text-sm text-muted">نصب نرم‌افزار به دسترسی مالک یا مدیر نیاز دارد.</p>}
        </Form>
    </Card>;
}
