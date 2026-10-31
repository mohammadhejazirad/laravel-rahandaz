import { useEffect, useState } from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import { Form } from '@/components/ui/form';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { GitConnectionSteps } from '@/components/git/connection-steps';
import { GitProviderIcon } from '@/components/git/provider-icon';
import { AppLayout } from '@/layouts/app-layout';
import type { SharedProps } from '@/types';

export default function GitRegister({ action, manifest }: { action: string; manifest: string }) {
    const [submitting, setSubmitting] = useState(false);
    useEffect(() => {
        const restore = () => setSubmitting(false);
        window.addEventListener('pageshow', restore);
        return () => window.removeEventListener('pageshow', restore);
    }, []);
    const base = usePage<SharedProps>().props.app.base_path;
    return <AppLayout title="اتصال به GitHub" description="اطلاعات اتصال با تأیید شما مستقیم دریافت می‌شود؛ نیازی به کپی‌کردن کلید نیست.">
        <Head title="تأیید اتصال GitHub" />
        <Card className="mx-auto w-full max-w-2xl">
            <GitConnectionSteps current={1} />
            <h2 className="flex items-center gap-3 text-lg font-semibold"><GitProviderIcon provider="github" />یک تأیید برای راه‌اندازی اولیه</h2>
            <p className="mt-4 text-sm leading-8 text-muted">در GitHub نام برنامه را تأیید کنید و «Create GitHub App» را بزنید. تنظیمات و کلید اتصال خودکار به راه‌انداز برمی‌گردند. سپس حساب و مخزن‌های مجاز را انتخاب می‌کنید.</p>
            <p className="mt-3 text-sm leading-8 text-muted">در صفحهٔ نصب، «Only select repositories» را انتخاب کنید تا دسترسی واقعی برنامه به همان مخزن‌ها محدود شود. این برنامه فقط مجوز خواندن کد و اطلاعات مخزن دارد.</p>
            <Form action={action} method="post" onSubmit={event => { if (submitting) { event.preventDefault(); return; } setSubmitting(true); }} className="mt-6 flex flex-wrap gap-3">
                <input type="hidden" name="manifest" value={manifest} />
                <Button type="submit" loading={submitting} loadingLabel="در حال انتقال به GitHub…">بازکردن GitHub و تأیید اتصال</Button>
                <Button asChild variant="secondary"><Link href={`${base}/git`}>بازگشت</Link></Button>
            </Form>
        </Card>
    </AppLayout>;
}
