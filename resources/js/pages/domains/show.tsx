import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { StatusBadge } from '@/components/ui/status-badge';
import { PersianDate } from '@/components/dates/persian-date';
import { AppLayout } from '@/layouts/app-layout';
import type { SharedProps } from '@/types';
export default function DomainShow({ domain: d, can_operate }: { domain: { uuid: string; hostname: string; project_name: string; environment_name: string; validation_status: string; tls_mode: string; certificate: { uuid: string; install_state: string; issuer: string | null; not_after: string | null; renewal_status: string } | null }; can_operate: boolean }) {
    const { app } = usePage<SharedProps>().props;
    const form = useForm<{ operation?: string }>({});
    return <AppLayout title="جزئیات دامنه" description={`${d.project_name} / ${d.environment_name}`}><Head title="جزئیات دامنه" /><Button variant="secondary" asChild><Link href={`${app.base_path}/domains`}>بازگشت به دامنه‌ها</Link></Button><Card><p dir="ltr" className="mb-5 break-all font-mono text-lg">{d.hostname}</p><dl className="grid gap-4 sm:grid-cols-2"><div><dt className="text-muted">اعتبارسنجی</dt><dd><StatusBadge value={d.validation_status} /></dd></div><div><dt className="text-muted">TLS</dt><dd><StatusBadge value={d.tls_mode} /></dd></div>{d.certificate ? <><div><dt className="text-muted">وضعیت نصب گواهی</dt><dd><StatusBadge value={d.certificate.install_state} /></dd></div><div><dt className="text-muted">انقضای گواهی</dt><dd><PersianDate iso={d.certificate.not_after} /></dd></div></> : null}</dl></Card><Alert tone="warning">ثبت دامنه، تنظیمات DNS را تغییر نمی‌دهد. اعمال وب‌سرور، صدور و تمدید گواهی هنوز فعال نیستند؛ نتیجهٔ درخواست را در عملیات دنبال کنید.</Alert>{form.errors.operation ? <Alert tone="danger">{form.errors.operation}</Alert> : null}{can_operate && !d.certificate ? <Button loading={form.processing} onClick={() => form.post(`${app.base_path}/domains/${d.uuid}/certificate`)}>ثبت درخواست گواهی</Button> : null}</AppLayout>;
}
