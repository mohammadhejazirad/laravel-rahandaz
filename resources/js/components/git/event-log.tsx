import { usePage } from '@inertiajs/react';
import { Card } from '@/components/ui/card';
import { Pagination } from '@/components/ui/pagination';
import { formatJalali } from '@/lib/datetime';
import type { SharedProps } from '@/types';
import type { GitEvents } from './types';

const labels: Record<string, string> = {
    'git.manual_connect_failed': 'ثبت اتصال دستی ناموفق بود',
    'git.manual_test_started': 'تست توکن دستی آغاز شد',
    'git.manual_test': 'نتیجهٔ بررسی هویت و دسترسی توکن دستی',
    'git.oauth_preflight_started': 'بررسی دسترسی به صفحهٔ مجوز آغاز شد',
    'git.oauth_preflight': 'نتیجهٔ دسترسی به صفحهٔ مجوز؛ هویت هنوز تأیید نشده',
    'git.connection_test_started': 'تست حساب متصل آغاز شد',
    'git.connection_test': 'نتیجهٔ تست هویت حساب و دسترسی خواندن مخزن‌ها',
    'git.settings': 'تنظیمات برنامه ذخیره شد',
    'git.authorization_started': 'فرایند تأیید دسترسی آغاز شد',
    'git.authorization_rejected': 'تأیید نامعتبر یا منقضی رد شد',
    'git.installation_returned': 'بازگشت از نصب برنامه؛ در انتظار تأیید هویت',
    'git.authorization_failed': 'اتصال حساب تأیید نشد',
    'git.connect': 'هویت حساب تأیید و اتصال ذخیره شد',
    'git.sync_requested': 'درخواست دریافت مخزن‌ها در صف ثبت شد',
    'git.sync_started': 'دریافت فهرست مخزن‌ها آغاز شد',
    'git.repositories_page': 'یک صفحه از مخزن‌های سرویس دریافت شد',
    'git.sync': 'همگام‌سازی مخزن‌ها کامل شد',
    'git.sync_failed': 'همگام‌سازی شکست خورد؛ امکان تلاش دوباره وجود دارد',
    'git.repository_allowed': 'دسترسی مخزن در پنل فعال شد',
    'git.repository_denied': 'دسترسی مخزن در پنل غیرفعال شد',
    'git.revoke': 'اتصال در پنل قطع شد؛ لغو مجوز سرویس جداگانه است',
    'git.refresh_started': 'تمدید مجوز حساب آغاز شد',
    'git.refresh_failed': 'تمدید مجوز انجام نشد؛ اتصال مجدد را بررسی کنید',
    'git.refresh': 'مجوز حساب تمدید شد',
    'git.branches': 'شاخه‌های مخزن دریافت شدند',
};

export function GitEventLog({ events }: { events: GitEvents }) {
    const timezone = usePage<SharedProps>().props.auth.user?.timezone ?? 'Asia/Tehran';
    return <Card>
        <h2 className="font-semibold">تاریخچهٔ اتصال و دسترسی</h2>
        <p className="mt-2 text-sm text-muted">رویدادهای واقعی، از جدید به قدیم؛ زمان‌ها به وقت {timezone} هستند. رمزها و توکن‌ها نمایش داده نمی‌شوند.</p>
        {events.data.length === 0 ? <p className="py-8 text-center text-sm text-muted">هنوز رویدادی ثبت نشده است.</p> : <ol className="my-4 divide-y divide-line" aria-label="رویدادهای اتصال">
            {events.data.map(event => <li key={event.uuid} className="grid min-w-0 gap-2 py-4 sm:grid-cols-[12rem_1fr]">
                <time dateTime={event.recorded_at} dir="ltr" className="font-mono text-xs text-muted">{formatJalali(event.recorded_at, timezone)}</time>
                <div className="min-w-0 text-sm">
                    <p className={event.result === 'failure' || event.result === 'denied' ? 'text-danger' : 'text-ink'}>{labels[event.action] ?? event.action} · {event.result === 'success' ? 'موفق' : event.result === 'denied' ? 'رد شد' : 'ناموفق'}</p>
                    {event.provider || event.account_label ? <p className="mt-1 break-all text-xs text-muted"><bdi dir="ltr" className="font-mono">{[event.provider, event.account_label].filter(Boolean).join(' / ')}</bdi></p> : null}
                    {event.page !== null ? <p className="mt-1 text-xs text-muted">صفحهٔ دریافت: {event.page.toLocaleString('fa-IR')}</p> : null}
                    {event.repository_name ? <p dir="ltr" className="mt-1 break-all text-start font-mono text-xs text-muted">{event.repository_name}</p> : null}
                    {event.repository_count !== null ? <p className="mt-1 text-xs text-muted">تعداد مخزن: {event.repository_count.toLocaleString('fa-IR')}</p> : null}
                </div>
            </li>)}
        </ol>}
        <Pagination meta={events.meta} />
    </Card>;
}
