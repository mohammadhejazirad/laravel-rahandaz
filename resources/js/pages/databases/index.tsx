import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ResourceList, type ListRow } from '@/components/resource-list';
import { ActionPanel } from '@/components/ui/action-panel';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Form } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { PageSections, type PageSection } from '@/components/ui/page-sections';
import { Pagination, type PageMeta } from '@/components/ui/pagination';
import { Select } from '@/components/ui/select';
import { StatusBadge } from '@/components/ui/status-badge';
import { AppLayout } from '@/layouts/app-layout';
import type { SharedProps } from '@/types';

interface DatabasesProps {
    databases: { data: ListRow[]; meta: PageMeta };
    instances: { uuid: string; engine: string; version: string; observed_state: string }[];
    environments: { uuid: string; name: string }[];
    can_manage: boolean;
}

export default function DatabasesIndex({ databases, instances, environments, can_manage }: DatabasesProps) {
    const { app } = usePage<SharedProps>().props;
    const form = useForm({ instance_uuid: instances[0]?.uuid ?? '', environment_uuid: environments[0]?.uuid ?? '', name: '', username: '', password: '' });
    const sections: PageSection[] = [
        {
            id: 'project-databases', label: 'دیتابیس‌های پروژه', content: <>
                <ResourceList rows={databases.data} detailBase={`${app.base_path}/databases`} label="دیتابیس‌های پروژه" columns={[{ key: 'name', label: 'دیتابیس', kind: 'technical' }, { key: 'engine', label: 'موتور', kind: 'technical' }, { key: 'project_name', label: 'پروژه' }, { key: 'status', label: 'وضعیت', kind: 'status' }, { key: 'observed_at', label: 'مشاهده موتور', kind: 'date' }]} />
                <Pagination meta={databases.meta} />
            </>,
        },
        {
            id: 'database-engines', label: 'موتورها و نسخه‌ها', content: <>
                <Card className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div><h2 className="font-semibold">نصب موتور دیتابیس</h2><p className="mt-1 text-sm leading-7 text-muted">وضعیت نصب و نسخهٔ بسته‌ها را در کاتالوگ بررسی و موتور مورد نیاز را به فهرست نصب اضافه کنید.</p></div>
                    <Button asChild variant="secondary"><Link href={`${app.base_path}/catalog?category=database`}>مشاهده و نصب موتورها</Link></Button>
                </Card>
                <h2 className="font-semibold">نمونه‌های ثبت‌شده برای پروژه‌ها</h2>
                <p className="text-sm leading-7 text-muted">این‌ها نمونه‌های ثبت‌شده‌اند؛ وضعیت هر نمونه آخرین مشاهدهٔ ذخیره‌شده است و با نصب بسته روی سرور تفاوت دارد.</p>
                {instances.length ? <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">{instances.map(instance => <Card key={instance.uuid}>
                    <h3 dir="ltr" className="break-all font-mono text-sm font-semibold">{instance.engine}</h3>
                    <p className="mt-2 text-sm text-muted">نسخه · <bdi dir="ltr" className="break-all font-mono">{instance.version || '—'}</bdi></p>
                    <div className="mt-3"><StatusBadge value={instance.observed_state} /></div>
                </Card>)}</div> : <Card><p className="text-sm text-muted">هنوز نمونهٔ موتور برای این فضای کاری ثبت نشده است.</p></Card>}
            </>,
        },
    ];
    if (can_manage) sections.push({
        id: 'create-database', label: 'ساخت دیتابیس', content: <ActionPanel title="درخواست دیتابیس جدید" description="موتور، محیط و کاربر خصوصی را انتخاب کنید.">
            <Alert tone="warning">ثبت درخواست به معنی ساخت موفق دیتابیس نیست. عملیات اجرایی و حذف داده هنوز تأیید نشده‌اند.</Alert>
            {!instances.length || !environments.length ? <p className="my-4 text-sm leading-7 text-muted">برای ثبت درخواست، یک نمونهٔ موتور مجاز و یک محیط پروژه لازم است.</p> : null}
            <Form className="mt-5 grid gap-4 md:grid-cols-2" onSubmit={event => { event.preventDefault(); form.post(`${app.base_path}/databases`, { onFinish: () => form.reset('password') }); }}>
                <Select name="instance_uuid" label="نمونه موتور" value={form.data.instance_uuid} onChange={event => form.setData('instance_uuid', event.target.value)} error={form.errors.instance_uuid} isRequired>
                    <option value="">انتخاب نمونه</option>{instances.map(instance => <option key={instance.uuid} value={instance.uuid}>{instance.engine} {instance.version}</option>)}
                </Select>
                <Select name="environment_uuid" label="محیط پروژه" value={form.data.environment_uuid} onChange={event => form.setData('environment_uuid', event.target.value)} error={form.errors.environment_uuid} isRequired>
                    <option value="">انتخاب محیط</option>{environments.map(environment => <option key={environment.uuid} value={environment.uuid}>{environment.name}</option>)}
                </Select>
                <Input name="name" label="نام دیتابیس" dir="ltr" className="font-mono" value={form.data.name} onChange={event => form.setData('name', event.target.value)} error={form.errors.name} isRequired />
                <Input name="username" label="نام کاربر" dir="ltr" className="font-mono" value={form.data.username} onChange={event => form.setData('username', event.target.value)} error={form.errors.username} isRequired />
                <Input name="password" type="password" autoComplete="new-password" label="گذرواژه خصوصی؛ حداقل ۱۶ نویسه" value={form.data.password} onChange={event => form.setData('password', event.target.value)} error={form.errors.password} isRequired />
                <Button type="submit" className="self-end" disabled={!instances.length || !environments.length} loading={form.processing}>ثبت درخواست ساخت</Button>
            </Form>
        </ActionPanel>,
    });

    return <AppLayout title="دیتابیس‌ها" description="دیتابیس‌های پروژه، نسخهٔ موتورها و درخواست ساخت را در بخش‌های جدا مدیریت کنید.">
        <Head title="دیتابیس‌ها" />
        <PageSections sections={sections} label="بخش‌های مدیریت دیتابیس" />
    </AppLayout>;
}
