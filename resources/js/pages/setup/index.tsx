import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { isSoftwarePending, useSoftwarePolling } from '@/lib/software-jobs';
import { SoftwarePicker, SoftwareProgress, type SoftwareState, type SoftwareJob } from '@/components/catalog/software-picker';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Form } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { AppLayout } from '@/layouts/app-layout';
import type { HostFacts, SharedProps } from '@/types';

interface SetupProps {
    holding: boolean;
    workspace: { uuid: string; name: string; setup_completed_at: string | null };
    facts: HostFacts | null;
    software: SoftwareState;
    session: { uuid: string; state: string; answers: { display_name: string; packages: string[] }; job: SoftwareJob | null } | null;
}
export default function Setup({ holding, workspace, facts, software, session }: SetupProps) {
    const base = usePage<SharedProps>().props.app.base_path;
    const [review, setReview] = useState(false);
    const form = useForm({ display_name: session?.answers.display_name ?? workspace.name, packages: session?.answers.packages ?? [] as string[], idempotency_key: software.request_key });
    const pending = isSoftwarePending(session?.job);
    const complete = !!workspace.setup_completed_at;
    useEffect(() => {
        if (holding || complete || pending || review || form.processing || !form.data.display_name.trim() || form.data.display_name.length > 100) return;
        if (session?.state === 'in_progress' && session.answers.display_name === form.data.display_name && JSON.stringify(session.answers.packages.slice().sort()) === JSON.stringify(form.data.packages.slice().sort())) return;
        const timer = window.setTimeout(() => form.patch(`${base}/setup`, { preserveScroll: true, preserveState: true, only: ['session', 'software', 'errors'] }), 1000);
        return () => window.clearTimeout(timer);
    }, [holding, complete, pending, review, form, session, base]);
    useSoftwarePolling(pending, ['session', 'workspace', 'software']);
    return <AppLayout title="راه‌اندازی اولیه" description="سرور را بررسی کنید، نرم‌افزارهای مورد نیاز را انتخاب کنید و وارد پنل مدیریت شوید.">
        <Head title="راه‌اندازی اولیه" />
        <div className="mx-auto max-w-4xl space-y-5">
            {holding ? <Card><h2 className="font-semibold">راه‌اندازی در انتظار مالک است</h2><p className="mt-2 text-sm leading-7 text-muted">مالک فضای «{workspace.name}» باید راه‌اندازی اولیه را تکمیل کند.</p></Card> : complete ? <Card className="space-y-4"><h2 className="text-lg font-semibold">راه‌اندازی اولیه تکمیل شد</h2><SoftwareProgress job={session?.job} /><p className="text-sm leading-7 text-muted">سرویس‌های اصلی پنل بررسی شدند. می‌توانید نرم‌افزارهای اختیاری را بعداً از بخش نرم‌افزارها نصب کنید.</p><Button asChild><Link href={`${base}/dashboard`}>ورود به پنل مدیریت</Link></Button></Card> : <>
                <ol className="grid gap-2 text-sm sm:grid-cols-3" aria-label="مراحل راه‌اندازی">{['۱. سرور و نرم‌افزارها', '۲. بازبینی انتخاب‌ها', '۳. بررسی و پایان'].map((title, index) => <li key={title} aria-current={(pending ? 2 : review ? 1 : 0) === index ? 'step' : undefined} className={`rounded-xl border p-3 ${(pending ? 2 : review ? 1 : 0) === index ? 'border-accent bg-accent/10' : 'border-line text-muted'}`}>{title}</li>)}</ol>
                {facts && <Card><h2 className="mb-3 font-semibold">مشخصات سرور</h2><dl className="grid gap-4 text-sm sm:grid-cols-2">{[['سیستم‌عامل', `${facts.os?.name ?? ''} ${facts.os?.version ?? ''}`], ['نام میزبان', facts.hostname], ['معماری', facts.architecture], ['حافظه', facts.memory.total_kib === null ? '—' : `${Math.round(facts.memory.total_kib / 1024)} MiB`]].map(([label, value]) => <div key={label}><dt className="text-muted">{label}</dt><dd dir="ltr" className="mt-1 break-all font-mono">{value}</dd></div>)}</dl></Card>}
                {pending ? <Card><h2 className="mb-4 font-semibold">راه‌اندازی در حال انجام است</h2><SoftwareProgress job={session?.job} /></Card> : <Card className="space-y-5">
                    {session?.job?.status === 'failed' && <SoftwareProgress job={session.job} />}
                    {!software.available && <Alert tone="warning">سرویس نصب در دسترس نیست. سلامت سرویس‌های راه‌انداز باید بررسی شود؛ انتخاب‌های شما قابل ذخیره است.</Alert>}
                    <Form className="space-y-5" onSubmit={event => {
                        event.preventDefault();
                        if (review) form.post(`${base}/setup/apply`, { preserveScroll: true, onSuccess: () => setReview(false) });
                        else { form.setData('idempotency_key', software.request_key); form.patch(`${base}/setup`, { preserveScroll: true, onSuccess: () => setReview(true) }); }
                    }}>
                        {!review ? <><Input label="نام نمایشی سرور" name="display_name" isRequired maxLength={100} value={form.data.display_name} error={form.errors.display_name} onChange={event => form.setData('display_name', event.target.value)} />
                            <SoftwarePicker catalog={software.catalog} selected={form.data.packages} onChange={keys => form.setData('packages', keys)} />
                            <p className="text-sm leading-7 text-muted">انتخاب نرم‌افزار اختیاری است؛ می‌توانید بدون نصب مورد اضافه ادامه دهید. Chrome و runtime پروژه‌ها در این مرحله نصب نمی‌شوند.</p></> : <div className="space-y-3 text-sm leading-7"><h2 className="font-semibold">پیش از شروع</h2><p>نام سرور: {form.data.display_name}</p><p>بررسی الزامی: سلامت پنل، پایگاه داده، صف، کش، حساب سرویس و دسترسی نصب.</p><p>نرم‌افزارهای انتخابی: <span dir="ltr" className="font-mono">{form.data.packages.map(key => software.catalog[key]?.name).join(', ') || '—'}</span></p><p className="text-muted">بسته‌ها از مخزن APT تنظیم‌شدهٔ سرور دریافت می‌شوند. نسخهٔ دقیق پیش از نصب ثابت و پس از نصب بررسی می‌شود. بستهٔ موجود ارتقا یا حذف نمی‌شود. تغییر SSH، فایروال و تنظیمات پروژه‌ها در این درخواست انجام نمی‌شود.</p></div>}
                        {form.errors.packages && <p role="alert" className="text-sm text-danger">{form.errors.packages}</p>}
                        <div className="flex flex-wrap gap-3">{review && <Button type="button" variant="secondary" onClick={() => setReview(false)}>ویرایش انتخاب‌ها</Button>}<Button type="submit" loading={form.processing} disabled={review && !software.available}>{review ? 'تأیید و شروع راه‌اندازی' : 'ذخیره و بازبینی'}</Button></div>
                    </Form>
                </Card>}
            </>}
        </div>
    </AppLayout>;
}
