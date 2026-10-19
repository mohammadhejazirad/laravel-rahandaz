import { Link, router, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Form } from '@/components/ui/form';
import { StatusBadge } from '@/components/ui/status-badge';
import type { SharedProps } from '@/types';

export interface ChromeInstallationState {
    host_uuid: string | null;
    can_request: boolean;
    execution_available: boolean;
    job: { uuid: string; status: string; error_code: string | null } | null;
    executable_path: string | null;
}

export function ChromeInstallationCard({ chrome }: { chrome: ChromeInstallationState }) {
    const base = usePage<SharedProps>().props.app.base_path;
    const [submitting, setSubmitting] = useState(false);
    const [copyStatus, setCopyStatus] = useState('');
    const pathInput = useRef<HTMLInputElement>(null);
    const pending = chrome.job !== null && !['succeeded', 'failed', 'cancelled'].includes(chrome.job.status);
    useEffect(() => {
        if (!pending || document.visibilityState !== 'visible') return;
        const timer = window.setInterval(() => {
            if (document.visibilityState === 'visible') router.reload({ only: ['chrome'] });
        }, 5000);
        return () => window.clearInterval(timer);
    }, [pending]);

    async function copyPath() {
        if (!chrome.executable_path) return;
        try {
            await navigator.clipboard.writeText(chrome.executable_path);
            setCopyStatus('مسیر کپی شد.');
        } catch {
            pathInput.current?.focus();
            pathInput.current?.select();
            setCopyStatus('مسیر انتخاب شد؛ آن را دستی کپی کنید.');
        }
    }

    return <Card aria-labelledby="chrome-title" className="space-y-4">
        <div className="flex flex-wrap items-center justify-between gap-3">
            <h2 id="chrome-title" className="font-semibold">Google Chrome برای پروژه‌ها</h2>
            {chrome.job && <StatusBadge value={chrome.job.status} />}
        </div>
        <p className="text-sm text-muted">اختیاری برای تولید PDF، PNG و تصویر صفحه با Browsershot یا Puppeteer است. در نصب اولیهٔ پنل نصب نمی‌شود. runtime و وابستگی‌های پروژه جداگانه تنظیم می‌شوند.</p>
        {!chrome.execution_available && <p className="text-sm text-muted">اجرای نصب روی میزبان هنوز فعال نیست. دکمه فقط درخواست را ثبت می‌کند؛ تا تأیید واقعی نصب و رندر، مسیر اجرایی نمایش داده نمی‌شود.</p>}
        {chrome.executable_path ? <div className="space-y-2">
            <label htmlFor="chrome-executable" className="block text-sm font-medium">مسیر اجرایی تأییدشدهٔ Chrome</label>
            <div className="flex min-w-0 flex-col gap-2 sm:flex-row">
                <input ref={pathInput} id="chrome-executable" readOnly value={chrome.executable_path} dir="ltr" className="min-w-0 flex-1 rounded-lg border border-line bg-surface-muted px-3 py-2 font-mono text-sm" />
                <Button variant="secondary" onClick={copyPath}>کپی مسیر</Button>
            </div>
            <p className="text-sm text-muted">این مسیر باینری روی سرور است؛ آن را در تنظیم executable مرورگر پروژه یا متغیر محیطی‌ای که کد پروژه می‌خواند قرار دهید. نام متغیر با توجه به کتابخانه و کد پروژه تعیین می‌شود.</p>
            <p role="status" className="text-sm">{copyStatus}</p>
        </div> : <Form onSubmit={event => {
            event.preventDefault();
            if (!chrome.host_uuid || !chrome.can_request || submitting || pending) return;
            setSubmitting(true);
            router.post(`${base}/catalog/chrome/${chrome.host_uuid}/install`, {}, { preserveScroll: true, onFinish: () => setSubmitting(false) });
        }}>
            <Button type="submit" disabled={!chrome.can_request || pending} loading={submitting}>{pending ? 'درخواست در حال بررسی است' : chrome.job?.status === 'failed' ? 'تلاش دوباره برای نصب Chrome' : 'درخواست نصب Chrome'}</Button>
            {!chrome.host_uuid && <p className="mt-2 text-sm text-muted">ابتدا میزبان محلی باید ثبت شود.</p>}
            {chrome.host_uuid && !chrome.can_request && <p className="mt-2 text-sm text-muted">نصب نرم‌افزار میزبان به دسترسی مالک یا مدیر نیاز دارد.</p>}
        </Form>}
        {chrome.job && <div className="flex flex-wrap items-center gap-3 text-sm">
            <Link className="text-primary underline" href={`${base}/jobs/${chrome.job.uuid}`}>جزئیات درخواست و خطا</Link>
            {chrome.job.error_code && <code dir="ltr" className="break-all font-mono">{chrome.job.error_code}</code>}
            <Button variant="secondary" onClick={() => router.reload({ only: ['chrome'] })}>تازه‌سازی وضعیت</Button>
        </div>}
    </Card>;
}
