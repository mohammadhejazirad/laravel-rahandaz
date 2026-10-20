import { useEffect, useState } from 'react';
import { Activity, Cpu, Database, MemoryStick, Network } from 'lucide-react';
import { Card } from '@/components/ui/card';
import { Alert } from '@/components/ui/alert';
import { PersianDate } from '@/components/dates/persian-date';
import { formatBytes } from '@/lib/format';

export interface HostMetric {
    collected_at: string;
    cpu_percent: number | null;
    cpu_cores: number | null;
    interval_seconds: number | null;
    ram_used_bytes: number | null;
    ram_total_bytes: number | null;
    swap_used_bytes: number | null;
    swap_total_bytes: number | null;
    disk_used_bytes: number | null;
    disk_total_bytes: number | null;
    network_rx_bytes_per_second: number | null;
    network_tx_bytes_per_second: number | null;
    load_1: number | null;
    load_5: number | null;
    load_15: number | null;
    uptime_seconds: number | null;
}
export interface LiveMetrics { current: HostMetric | null; history: HostMetric[]; error: string | null; }
const number = (value: number | null, digits = 1) => value === null ? '—' : value.toLocaleString('fa-IR', { maximumFractionDigits: digits });
const percentage = (used: number | null, total: number | null) => used !== null && total !== null && total > 0 ? Math.min(100, used / total * 100) : null;

export function MonitoringResources({ live }: { live: LiveMetrics }) {
    const [now, setNow] = useState(() => Date.now());
    const [online, setOnline] = useState(() => navigator.onLine);
    useEffect(() => {
        const refresh = () => { setNow(Date.now()); setOnline(navigator.onLine); };
        const timer = window.setInterval(refresh, 1000);
        window.addEventListener('online', refresh);
        window.addEventListener('offline', refresh);
        return () => { clearInterval(timer); window.removeEventListener('online', refresh); window.removeEventListener('offline', refresh); };
    }, []);
    const sample = live.current;
    const age = sample ? Math.max(0, (now - Date.parse(sample.collected_at)) / 1000) : null;
    const stale = age === null || age > 15;
    const points = live.history.filter((item): item is HostMetric & {cpu_percent: number} => item.cpu_percent !== null);
    return <section className="space-y-4" aria-label="منابع زندهٔ سرور">
        <div className="flex flex-wrap items-center justify-between gap-3"><h2 className="text-lg font-semibold">منابع زندهٔ سرور</h2><span className={`text-sm ${stale || !online ? 'text-warning' : 'text-success'}`} role="status">{!online ? 'ارتباط اینترنت قطع است؛ در انتظار اتصال' : stale ? 'دادهٔ تازه در دسترس نیست؛ تلاش مجدد خودکار' : `دریافت خودکار · ${number(age, 0)} ثانیه پیش`}</span></div>
        {live.error && <Alert tone="warning">{live.error}</Alert>}
        {!sample ? <Card><p className="text-sm text-muted">در انتظار خواندن منابع میزبان؛ نبود داده به معنی مصرف صفر نیست.</p></Card> : <>
            <div className={`grid min-w-0 gap-4 sm:grid-cols-2 xl:grid-cols-4 ${stale ? 'opacity-65' : ''}`}>
                <Usage icon={Cpu} label="پردازنده" value={`${number(sample.cpu_percent)} %`} percent={sample.cpu_percent} detail={`${number(sample.cpu_cores, 0)} هسته · میانگین بازهٔ ${number(sample.interval_seconds)} ثانیه`}/>
                <Usage icon={MemoryStick} label="حافظه" value={formatBytes(sample.ram_used_bytes)} percent={percentage(sample.ram_used_bytes, sample.ram_total_bytes)} detail={`از ${formatBytes(sample.ram_total_bytes)} · بر پایهٔ MemAvailable`}/>
                <Usage icon={Database} label="دیسک /" value={formatBytes(sample.disk_used_bytes)} percent={percentage(sample.disk_used_bytes, sample.disk_total_bytes)} detail={`از ${formatBytes(sample.disk_total_bytes)}`}/>
                <Usage icon={Network} label="ترافیک شبکه" value={`↓ ${formatBytes(sample.network_rx_bytes_per_second)}/s`} percent={null} detail={`↑ ${formatBytes(sample.network_tx_bytes_per_second)}/s · مجموع رابط‌ها بدون loopback`}/>
            </div>
            <Card className="space-y-4">
                <div className="flex flex-wrap items-center justify-between gap-3"><h3 className="flex items-center gap-2 font-semibold"><Activity className="size-4" aria-hidden="true"/>مصرف پردازنده در مشاهده‌های اخیر</h3><span className="text-xs text-muted">آخرین مشاهده: <PersianDate iso={sample.collected_at}/></span></div>
                {points.length >= 2 ? <svg viewBox="0 0 600 110" role="img" aria-label={`مصرف پردازنده در ${number(points.length, 0)} مشاهدهٔ واقعی؛ آخرین مقدار ${number(sample.cpu_percent)} درصد`} className="h-32 w-full overflow-visible text-accent" preserveAspectRatio="none" style={{ direction: 'ltr' }}><path d="M0 5H600 M0 55H600 M0 105H600" className="stroke-line" fill="none" strokeWidth="1"/><path d={live.history.map((p, i) => p.cpu_percent === null ? '' : `${i > 0 && live.history[i - 1].cpu_percent !== null ? 'L' : 'M'}${i / Math.max(1, live.history.length - 1) * 600},${105 - p.cpu_percent}`).join(' ')} fill="none" stroke="currentColor" strokeWidth="2" vectorEffect="non-scaling-stroke"/></svg> : <p className="text-sm text-muted">برای محاسبهٔ مصرف پردازنده و نرخ شبکه، دو مشاهدهٔ متوالی لازم است.</p>}
                <dl className="grid gap-3 text-sm sm:grid-cols-3"><div><dt className="text-muted">Load یک / پنج / پانزده دقیقه</dt><dd dir="ltr" className="mt-1 font-mono">{number(sample.load_1)} / {number(sample.load_5)} / {number(sample.load_15)}</dd></div><div><dt className="text-muted">Swap مصرف‌شده / کل</dt><dd dir="ltr" className="mt-1 font-mono">{formatBytes(sample.swap_used_bytes)} / {formatBytes(sample.swap_total_bytes)}</dd></div><div><dt className="text-muted">زمان روشن‌بودن</dt><dd className="mt-1">{sample.uptime_seconds === null ? '—' : `${number(sample.uptime_seconds / 3600)} ساعت`}</dd></div></dl>
                <p className="text-xs leading-6 text-muted">منابع هنگام بازبودن صفحه حدود هر ۱۵ ثانیه خوانده می‌شوند. زمان قطع ارتباط، دادهٔ قبلی تازه محسوب نمی‌شود. نمودار از مشاهده‌های واقعی همین میزبان است؛ پردازنده و شبکه میانگین فاصلهٔ دو مشاهده‌اند.</p>
            </Card>
        </>}
    </section>;
}
function Usage({ icon: Icon, label, value, detail, percent }: { icon: typeof Cpu; label: string; value: string; detail: string; percent: number | null }) {
    return <Card className="min-w-0 space-y-3"><h3 className="flex items-center gap-2 text-sm text-muted"><Icon className="size-4" aria-hidden="true"/>{label}</h3><p dir="ltr" className="break-words font-mono text-xl font-semibold">{value}</p>{percent !== null && <div role="progressbar" aria-label={label} aria-valuenow={Math.round(percent)} aria-valuemin={0} aria-valuemax={100} className="h-2 overflow-hidden rounded-full bg-surface-muted"><div className="h-full rounded-full bg-accent" style={{ width: `${Math.max(0, Math.min(100, percent))}%` }}/></div>}<p className="text-xs leading-6 text-muted">{detail}</p></Card>;
}
