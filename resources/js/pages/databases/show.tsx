import { Head, Link, usePage } from '@inertiajs/react';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { StatusBadge } from '@/components/ui/status-badge';
import { PersianDate } from '@/components/dates/persian-date';
import { AppLayout } from '@/layouts/app-layout';
import type { SharedProps } from '@/types';
export default function DatabaseShow({ database: d }: { database: { name: string; engine: string; engine_version: string; status: string; observed_state: string; observed_at: string | null; project_name: string; environment_name: string; username: string } }) {
    const { app } = usePage<SharedProps>().props;
    return <AppLayout title="جزئیات دیتابیس" description={`${d.project_name} / ${d.environment_name}`}><Head title="جزئیات دیتابیس" /><Button variant="secondary" asChild><Link href={`${app.base_path}/databases`}>بازگشت به دیتابیس‌ها</Link></Button><Card><dl className="grid gap-5 sm:grid-cols-2"><div><dt className="text-muted">نام</dt><dd dir="ltr" className="break-all font-mono">{d.name}</dd></div><div><dt className="text-muted">موتور</dt><dd dir="ltr" className="font-mono">{d.engine} {d.engine_version}</dd></div><div><dt className="text-muted">وضعیت دیتابیس</dt><dd><StatusBadge value={d.status} /></dd></div><div><dt className="text-muted">وضعیت مشاهده‌شده موتور</dt><dd><StatusBadge value={d.observed_state} /></dd></div><div><dt className="text-muted">کاربر مستقل</dt><dd dir="ltr" className="font-mono">{d.username}</dd></div><div><dt className="text-muted">زمان مشاهده</dt><dd><PersianDate iso={d.observed_at} /></dd></div></dl></Card><Alert tone="warning">گذرواژه و Connection String حساس در این صفحه نمایش داده نمی‌شود. تغییر رمز، Grant، آزمون اتصال، Backup/Restore و Drop واقعی تا تکمیل executor آزموده در دسترس نیستند.</Alert></AppLayout>;
}
