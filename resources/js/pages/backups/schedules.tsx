import { Head, Link, usePage } from '@inertiajs/react';
import { ResourceList, type ListRow } from '@/components/resource-list';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Pagination, type PageMeta } from '@/components/ui/pagination';
import { AppLayout } from '@/layouts/app-layout';
import type { SharedProps } from '@/types';
export default function BackupSchedules({ schedules }: { schedules: { data: ListRow[]; meta: PageMeta } }) {
    const { app } = usePage<SharedProps>().props;
    return <AppLayout title="زمان‌بندی پشتیبان‌گیری"><Head title="زمان‌بندی پشتیبان‌گیری" /><Button variant="secondary" asChild><Link href={`${app.base_path}/backups`}>بازگشت به پشتیبان‌گیری</Link></Button><Alert tone="warning">نمایش زمان‌بندی ذخیره‌شده به معنی فعال‌بودن dispatcher و ایجاد Artifact نیست. فعال‌سازی زمان‌بندی عملیاتی هنوز آماده نیست.</Alert><ResourceList rows={schedules.data.map(r => ({ ...r, enabled_label: r.enabled ? 'فعال در داده ذخیره‌شده' : 'غیرفعال' }))} label="زمان‌بندی‌ها" columns={[{key:'operation_key',label:'عملیات',kind:'technical'},{key:'cron_expression',label:'Cron',kind:'technical'},{key:'timezone',label:'منطقه زمانی',kind:'technical'},{key:'enabled_label',label:'وضعیت'}]} /><Pagination meta={schedules.meta} /></AppLayout>;
}
