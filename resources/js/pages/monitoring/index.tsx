import { formatBytes } from '@/lib/format';
import { Head } from '@inertiajs/react';
import { ResourceList, type ListRow } from '@/components/resource-list';
import { Alert } from '@/components/ui/alert';
import { Card } from '@/components/ui/card';
import { PersianDate } from '@/components/dates/persian-date';
import { Pagination, type PageMeta } from '@/components/ui/pagination';
import { useVisiblePoll } from '@/hooks/use-visible-poll';
import { AppLayout } from '@/layouts/app-layout';
interface Sample { uuid: string; host_slug: string; source: string; collected_at: string | null; cpu_percent: number | null; ram_used_bytes: number | null; disk_used_bytes: number | null; load_1: number | null; stale: boolean; }
export default function MonitoringIndex({ board, generated_at, services, alerts }: { board: { samples: Sample[] }; generated_at: string; services: { data: ListRow[]; meta: PageMeta }; alerts: { data: ListRow[]; meta: PageMeta } }) {
    useVisiblePoll(true, 'board,services,alerts,generated_at', 30000);
    const samples: ListRow[] = board.samples.map(s => ({ ...s, freshness: s.stale ? 'قدیمی یا زمان نامعتبر' : 'تازه', ram_display: formatBytes(s.ram_used_bytes), disk_display: formatBytes(s.disk_used_bytes) }));
    return <AppLayout title="پایش" description="نمونه‌های واقعی ذخیره‌شده با منبع و زمان؛ صفحه پنهان درخواست دوره‌ای نمی‌فرستد."><Head title="پایش" /><Alert tone="warning">Collector و ارزیابی خودکار Alert هنوز کامل نیستند. نبود نمونه به معنی مصرف صفر یا سرویس سالم نیست. نمودار بدون نمونه معتبر رسم نمی‌شود.</Alert><Card><span className="text-sm text-muted">زمان پاسخ پنل: <PersianDate iso={generated_at} />؛ زمان هر مشاهده در جدول مستقل است.</span></Card><h2 className="text-lg font-semibold">نمونه‌های منابع میزبان</h2><ResourceList rows={samples} label="CPU، حافظه و دیسک" columns={[{key:'host_slug',label:'میزبان',kind:'technical'},{key:'cpu_percent',label:'CPU (%)',kind:'technical'},{key:'ram_display',label:'حافظهٔ مصرف‌شده',kind:'technical'},{key:'disk_display',label:'دیسک مصرف‌شده',kind:'technical'},{key:'load_1',label:'Load',kind:'technical'},{key:'collected_at',label:'مشاهده',kind:'date'},{key:'freshness',label:'تازگی'}]} /><h2 className="text-lg font-semibold">سلامت سرویس‌ها</h2><ResourceList rows={services.data.map(s => ({...s,freshness:s.stale ? 'قدیمی' : 'تازه'}))} label="سلامت سرویس" columns={[{key:'kind',label:'سرویس',kind:'status'},{key:'runtime_state',label:'وضعیت',kind:'status'},{key:'health_status',label:'سلامت',kind:'status'},{key:'observed_at',label:'مشاهده',kind:'date'},{key:'freshness',label:'تازگی'}]} /><Pagination meta={services.meta} /><h2 className="text-lg font-semibold">هشدارهای ثبت‌شده</h2><ResourceList rows={alerts.data} label="هشدارها" columns={[{key:'summary',label:'شرح'},{key:'state',label:'وضعیت',kind:'status'},{key:'started_at',label:'آغاز',kind:'date'}]} /><Pagination meta={alerts.meta} /></AppLayout>;
}
