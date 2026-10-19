import { useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Form } from '@/components/ui/form';
import { isSoftwarePending, useSoftwarePolling } from '@/lib/software-jobs';
import { SoftwarePicker, SoftwareProgress, type SoftwareState } from './software-picker';
import type { SharedProps } from '@/types';

export function SoftwareInstallationCard({ software }: { software: SoftwareState }) {
    const base = usePage<SharedProps>().props.app.base_path;
    const form = useForm({ packages: [] as string[], idempotency_key: software.request_key });
    const [review, setReview] = useState(false);
    const pending = isSoftwarePending(software.job);
    useSoftwarePolling(pending, ['software']);
    return <Card className="space-y-4">
        <h2 className="font-semibold">نصب نرم‌افزار روی سرور</h2>
        <SoftwareProgress job={software.job} />
        {!software.available && <p className="text-sm text-muted">سرویس نصب در دسترس نیست. سرویس‌های راه‌انداز را بررسی کنید.</p>}
        <Form className="space-y-4" onSubmit={event => {
            event.preventDefault();
            if (!review) { setReview(true); return; }
            form.transform(data => ({ ...data, idempotency_key: software.request_key }));
            form.post(`${base}/catalog/software/install`, { preserveScroll: true, onSuccess: () => { setReview(false); form.setData('packages', []); } });
        }}>
            {!review ? <SoftwarePicker catalog={software.catalog} selected={form.data.packages} onChange={keys => form.setData('packages', keys)} disabled={pending} /> : <div className="space-y-2 text-sm leading-7"><h3 className="font-semibold">بازبینی نصب</h3><p dir="ltr" className="break-words font-mono">{form.data.packages.map(key => software.catalog[key]?.name).join(', ')}</p><p className="text-muted">دریافت از مخزن APT فعلی سرور، تثبیت نسخه پیش از نصب و بررسی نسخهٔ نصب‌شده. بسته‌های موجود ارتقا یا حذف نمی‌شوند. نصب در پس‌زمینه انجام می‌شود؛ می‌توانید نتیجه را در همین صفحه پیگیری کنید.</p></div>}
            {form.errors.packages && <p role="alert" className="text-sm text-danger">{form.errors.packages}</p>}
            <div className="flex flex-wrap gap-3">{review && <Button type="button" variant="secondary" onClick={() => setReview(false)}>ویرایش انتخاب‌ها</Button>}<Button type="submit" loading={form.processing} disabled={pending || !software.available || !software.can_request || form.data.packages.length === 0}>{review ? 'تأیید و نصب' : 'بازبینی نصب'}</Button></div>
            {!software.can_request && <p className="text-sm text-muted">نصب نرم‌افزار به دسترسی مالک یا مدیر نیاز دارد.</p>}
        </Form>
    </Card>;
}
