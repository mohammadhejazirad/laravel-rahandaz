import { Form } from '@/components/ui/form';
import { Head, useForm, usePage } from '@inertiajs/react';
import { ResourceList, type ListRow } from '@/components/resource-list';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Pagination, type PageMeta } from '@/components/ui/pagination';
import { AppLayout } from '@/layouts/app-layout';
import type { SharedProps } from '@/types';
interface DomainRow { uuid: string; hostname: string; project_name: string; environment_name: string; validation_status: string; tls_mode: string; }
export default function DomainsIndex({ domains, environments, can_operate }: { domains: { data: DomainRow[]; meta: PageMeta }; environments: { uuid: string; name: string }[]; can_operate: boolean }) {
    const { app } = usePage<SharedProps>().props;
    const form = useForm({ environment_uuid: environments[0]?.uuid ?? '', hostname: '' });
    return <AppLayout title="دامنه‌ها" description="دامنه هر محیط، وضعیت اعتبارسنجی و گواهی ثبت‌شده."><Head title="دامنه‌ها" />{can_operate ? <Card><h2 className="mb-4 font-semibold">افزودن دامنه</h2><Form className="grid gap-4 md:grid-cols-2" onSubmit={e => { e.preventDefault(); form.post(`${app.base_path}/domains`); }}><Select name="environment_uuid" label="محیط پروژه" value={form.data.environment_uuid} onChange={e => form.setData('environment_uuid', e.target.value)} error={form.errors.environment_uuid} isRequired><option value="">انتخاب محیط</option>{environments.map(e => <option key={e.uuid} value={e.uuid}>{e.name}</option>)}</Select><Input name="hostname" label="نام دامنه" dir="ltr" className="font-mono" placeholder="app.example.com" value={form.data.hostname} onChange={e => form.setData('hostname', e.target.value)} error={form.errors.hostname} maxLength={253} isRequired /><Button type="submit" loading={form.processing} disabled={!environments.length}>ثبت دامنه و درخواست پیکربندی</Button></Form></Card> : null}<ResourceList rows={domains.data as unknown as ListRow[]} detailBase={`${app.base_path}/domains`} label="دامنه‌ها" columns={[{key:'hostname',label:'دامنه',kind:'technical'},{key:'project_name',label:'پروژه'},{key:'environment_name',label:'محیط'},{key:'validation_status',label:'اعتبارسنجی',kind:'status'},{key:'tls_mode',label:'TLS',kind:'status'}]} /><Pagination meta={domains.meta} /></AppLayout>;
}
