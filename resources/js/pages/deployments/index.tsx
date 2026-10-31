import { ActionPanel } from '@/components/ui/action-panel';
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
export default function DeploymentsIndex({ deployments, environments, can_operate, filters, request_key }: { deployments: { data: ListRow[]; meta: PageMeta }; environments: { uuid: string; name: string; branch: string }[]; request_key: string; can_operate: boolean; filters: { status?: string; environment?: string } }) {
    const { app } = usePage<SharedProps>().props;
    const form = useForm({ environment_uuid: environments[0]?.uuid ?? '', commit_sha: '', idempotency_key: request_key });
    const search = useForm({ status: filters.status ?? '', environment: filters.environment ?? '' });
    return <AppLayout title="استقرارها" description="محیط و نسخهٔ پروژه را انتخاب کنید؛ درخواست‌ها و نتیجهٔ هر استقرار را دنبال کنید."><Head title="استقرارها" />
        {can_operate ? <ActionPanel title="درخواست استقرار جدید" description="محیط پروژه و نسخهٔ ثابت کد را مشخص کنید."><h2 className="mb-4 font-semibold">ثبت استقرار دستی Git</h2><Form onSubmit={e => { e.preventDefault(); form.post(`${app.base_path}/deployments`); }} className="grid gap-4 md:grid-cols-2"><Select searchable name="environment_uuid" label="محیط پروژه" value={form.data.environment_uuid} onChange={e => form.setData('environment_uuid', e.target.value)} error={form.errors.environment_uuid} isRequired><option value="">انتخاب محیط</option>{environments.map(e => <option key={e.uuid} value={e.uuid}>{e.name} / {e.branch}</option>)}</Select><Input name="commit_sha" label="Commit SHA کامل" help="SHA ثابت ۴۰ یا ۶۴ نویسه؛ نام شاخه به‌تنهایی پذیرفته نمی‌شود." dir="ltr" className="font-mono" value={form.data.commit_sha} onChange={e => form.setData('commit_sha', e.target.value)} error={form.errors.commit_sha} isRequired /><Button type="submit" loading={form.processing} disabled={!environments.length}>ثبت درخواست استقرار</Button></Form><p className="mt-3 text-sm text-muted">منبع ZIP از صفحه جزئیات پروژه پذیرفته می‌شود. ثبت درخواست، build یا سلامت release را تأیید نمی‌کند.</p></ActionPanel> : null}
        <Card><Form onSubmit={e => { e.preventDefault(); search.get(`${app.base_path}/deployments`); }} className="flex flex-wrap items-end gap-3"><Select name="status" label="فیلتر وضعیت" value={search.data.status} onChange={e => search.setData('status', e.target.value)}><option value="">همه</option><option value="queued">در صف</option><option value="failed">ناموفق</option><option value="succeeded">موفق</option><option value="cancelled">لغوشده</option></Select><Select searchable name="environment" label="محیط" value={search.data.environment} onChange={e => search.setData('environment', e.target.value)}><option value="">همه محیط‌ها</option>{environments.map(e => <option key={e.uuid} value={e.uuid}>{e.name}</option>)}</Select><Button type="submit" variant="secondary" loading={search.processing}>اعمال فیلتر</Button></Form></Card>
        <ResourceList rows={deployments.data} label="فهرست استقرارها" detailBase={`${app.base_path}/deployments`} columns={[{ key: 'project_name', label: 'پروژه' }, { key: 'environment_name', label: 'محیط' }, { key: 'status', label: 'وضعیت', kind: 'status' }, { key: 'commit_sha', label: 'Commit', kind: 'technical' }, { key: 'created_at', label: 'ثبت', kind: 'date' }]} /><Pagination meta={deployments.meta} />
    </AppLayout>;
}
