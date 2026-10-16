import { Form } from '@/components/ui/form';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ResourceList, type ListRow } from '@/components/resource-list';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Select } from '@/components/ui/select';
import { Pagination, type PageMeta } from '@/components/ui/pagination';
import { AppLayout } from '@/layouts/app-layout';
import type { SharedProps } from '@/types';
export default function BackupsIndex({ backups, targets, can_manage }: { backups: { data: ListRow[]; meta: PageMeta }; targets: { uuid: string; type: string; name: string }[]; can_manage: boolean }) {
    const { app } = usePage<SharedProps>().props;
    const form = useForm({ target_uuid: targets[0]?.uuid ?? '', target_type: targets[0]?.type ?? 'local_host', idempotency_key: crypto.randomUUID() });
    return <AppLayout title="پشتیبان‌گیری" description="Artifactهای ثبت‌شده، راستی‌آزمایی و تاریخچه بازیابی."><Head title="پشتیبان‌گیری" /><Alert tone="warning">درخواست بدون فایل واقعی، پشتیبان موفق محسوب نمی‌شود. checksum جای آزمون بازیابی ایزوله را نمی‌گیرد.</Alert>{can_manage ? <Card><Form onSubmit={e => { e.preventDefault(); form.post(`${app.base_path}/backups`); }} className="grid gap-4 sm:grid-cols-[1fr_auto]"><Select name="target_uuid" label="منبع پشتیبان‌گیری" value={form.data.target_uuid} onChange={e => { const target = targets.find(t => t.uuid === e.target.value); form.setData({ ...form.data, target_uuid: target?.uuid ?? '', target_type: target?.type ?? 'local_host' }); }} error={form.errors.target_uuid} isRequired><option value="">انتخاب منبع</option>{targets.map(t => <option key={t.uuid} value={t.uuid}>{t.name}</option>)}</Select><Button type="submit" className="self-end" loading={form.processing} disabled={!targets.length}>ثبت درخواست پشتیبان‌گیری</Button></Form></Card> : null}<Button variant="secondary" asChild><Link href={`${app.base_path}/backups/schedules`}>زمان‌بندی پشتیبان‌گیری</Link></Button><ResourceList rows={backups.data} detailBase={`${app.base_path}/backups`} label="فهرست پشتیبان‌گیری" columns={[{key:'backup_type',label:'نوع',kind:'status'},{key:'database_name',label:'دیتابیس',kind:'technical'},{key:'state',label:'وضعیت',kind:'status'},{key:'size_bytes',label:'اندازه (بایت)',kind:'technical'},{key:'verified_at',label:'راستی‌آزمایی',kind:'date'}]} /><Pagination meta={backups.meta} /></AppLayout>;
}
