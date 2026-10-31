import { Head, Link, usePage } from '@inertiajs/react';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { AppLayout } from '@/layouts/app-layout';
import type { SharedProps } from '@/types';

export default function DockerIndex({ installed, app_count }: { installed: boolean; app_count: number }) {
    const base = usePage<SharedProps>().props.app.base_path;

    return (
        <AppLayout title="مدیریت Docker" description="بعد از نصب Docker از کاتالوگ، برنامه‌های آماده و Compose خودتان از اینجا مدیریت می‌شوند." eyebrow="Docker">
            <Head title="مدیریت Docker" />
            {installed ? <Alert tone="info">Docker روی این میزبان دیده شد. اجرای استک تا اتصال پل env به موتور عملیات انجام نمی‌شود.</Alert> : <Alert tone="warning">Docker هنوز نصب نشده است. از کاتالوگ نرم‌افزارها آن را انتخاب کنید. نصب اولیهٔ پنل Docker را نصب نمی‌کند.</Alert>}
            <div className="grid gap-3 sm:grid-cols-2">
                <Card>
                    <h2 className="font-semibold">برنامه‌های آماده</h2>
                    <p className="mt-2 text-sm leading-7 text-muted">{app_count.toLocaleString('fa-IR')} قالب با env جدا. مقدار رمز در صفحه برنمی‌گردد.</p>
                    <Button asChild className="mt-4"><Link href={`${base}/docker/apps`}>مشاهده قالب‌ها</Link></Button>
                </Card>
                <Card>
                    <h2 className="font-semibold">Compose خودتان</h2>
                    <p className="mt-2 text-sm leading-7 text-muted">فایل yml را بنویسید. متغیرهای {'${NAME}'} خوانده می‌شوند و به‌صورت env پرسیده می‌شوند.</p>
                    <Button asChild variant="secondary" className="mt-4"><Link href={`${base}/docker/compose`}>نوشتن Compose</Link></Button>
                </Card>
            </div>
            <Card>
                <h2 className="font-semibold">نصب Docker</h2>
                <p className="mt-2 text-sm leading-7 text-muted">بستهٔ docker.io و افزونهٔ docker-compose-v2 فقط با درخواست شما از کاتالوگ نصب می‌شوند. منوی مدیریت وقتی `/usr/bin/docker` موجود باشد در سایدبار می‌آید.</p>
                <Button asChild variant="secondary" className="mt-4"><Link href={`${base}/catalog?category=runtime`}>کاتالوگ نرم‌افزارها</Link></Button>
            </Card>
        </AppLayout>
    );
}
