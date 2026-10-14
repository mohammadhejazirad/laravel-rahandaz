import { Head, useForm, usePage } from '@inertiajs/react';
import { ResourceList, type ListRow } from '@/components/resource-list';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Pagination, type PageMeta } from '@/components/ui/pagination';
import { AppLayout } from '@/layouts/app-layout';
import type { SharedProps } from '@/types';
export default function DeploymentsIndex({ deployments, environments, can_operate, filters }: { deployments: { data: ListRow[]; meta: PageMeta }; environments: { uuid: string; name: string; branch: string }[]; can_operate: boolean; filters: { status?: string; environment?: string } }) {
    const { app } = usePage<SharedProps>().props;
    const form = useForm({ environment_uuid: environments[0]?.uuid ?? '', commit_sha: '', idempotency_key: crypto.randomUUID() });
    const search = useForm({ status: filters.status ?? '', environment: filters.environment ?? '' });
    return <AppLayout title="استقرارها" description="تاریخچه و وضعیت واقعی استقرار هر محیط؛ عملیات میزبان هنوز به executor تأییدشده وابسته است."><Head title="استقرارها" />
        {can_operate ? <Card><h2 className="mb-4 font-semibold">ثبت استقرار دستی Git</h2><form onSubmit={e => { e.preventDefault(); form.post(`${app.base_path}/deployments`); }} className="grid gap-4 md:grid-cols-2"><Select name="environment_uuid" label="محیط پروژه" value={form.data.environment_uuid} onChange={e => form.setData('environment_uuid', e.target.value)} error={form.errors.environment_uuid} required><option value="">انتخاب محیط</option>{environments.map(e => <option key={e.uuid} value={e.uuid}>{e.name} / {e.branch}</option>)}</Select><Input name="commit_sha" label="Commit SHA کامل" help="SHA ثابت ۴۰ یا ۶۴ نویسه؛ نام شاخه به‌تنهایی پذیرفته نمی‌شود." dir="ltr" className="font-mono" value={form.data.commit_sha} onChange={e => form.setData('commit_sha', e.target.value)} error={form.errors.commit_sha} required /><Button type="submit" loading={form.processing} disabled={!environments.length}>ثبت درخواست استقرار</Button></form><p className="mt-3 text-sm text-muted">منبع ZIP از صفحه جزئیات پروژه پذیرفته می‌شود. ثبت درخواست، build یا سلامت release را تأیید نمی‌کند.</p></Card> : null}
        <Card><form onSubmit={e => { e.preventDefault(); search.get(`${app.base_path}/deployments`); }} className="flex flex-wrap items-end gap-3"><Select name="status" label="فیلتر وضعیت" value={search.data.status} onChange={e => search.setData('status', e.target.value)}><option value="">همه</option><option value="queued">در صف</option><option value="failed">ناموفق</option><option value="succeeded">موفق</option><option value="cancelled">لغوشده</option></Select><Select name="environment" label="محیط" value={search.data.environment} onChange={e => search.setData('environment', e.target.value)}><option value="">همه محیط‌ها</option>{environments.map(e => <option key={e.uuid} value={e.uuid}>{e.name}</option>)}</Select><Button type="submit" variant="secondary" loading={search.processing}>اعمال فیلتر</Button></form></Card>
        <ResourceList rows={deployments.data} label="فهرست استقرارها" detailBase={`${app.base_path}/deployments`} columns={[{ key: 'project_name', label: 'پروژه' }, { key: 'environment_name', label: 'محیط' }, { key: 'status', label: 'وضعیت', kind: 'status' }, { key: 'commit_sha', label: 'Commit', kind: 'technical' }, { key: 'created_at', label: 'ثبت', kind: 'date' }]} /><Pagination meta={deployments.meta} />
    </AppLayout>;
}
