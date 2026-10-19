import { PersianDate } from '@/components/dates/persian-date';
import { Card } from '@/components/ui/card';

export interface ServerLocation {
    status: 'known' | 'unknown';
    in_iran: boolean | null;
    country_code: string | null;
    country_label: string;
    city: string | null;
    isp: string | null;
    org: string | null;
    asn: string | null;
    public_ip: string | null;
    source: string | null;
    source_label: string | null;
    looked_up_at: string | null;
}

export function ServerLocationCard({ location }: { location: ServerLocation }) {
    const place = location.status === 'known'
        ? (location.in_iran ? 'این سرور در ایران است.' : 'این سرور خارج از ایران شناسایی شد.')
        : 'کشور این سرور تشخیص داده نشد.';

    return (
        <Card>
            <h2 className="font-semibold">موقعیت سرور</h2>
            <p className="mt-2 text-sm leading-6">{place}</p>
            <dl className="mt-4 grid gap-3 sm:grid-cols-2">
                <div>
                    <dt className="text-sm text-muted">کشور</dt>
                    <dd>{location.country_label}</dd>
                </div>
                <div>
                    <dt className="text-sm text-muted">شهر</dt>
                    <dd dir="ltr" className="font-mono text-sm">{location.city ?? '—'}</dd>
                </div>
                <div>
                    <dt className="text-sm text-muted">ارائه‌دهنده</dt>
                    <dd dir="ltr" className="break-words font-mono text-sm">{location.isp ?? '—'}</dd>
                </div>
                <div>
                    <dt className="text-sm text-muted">سازمان</dt>
                    <dd dir="ltr" className="break-words font-mono text-sm">{location.org ?? '—'}</dd>
                </div>
                <div>
                    <dt className="text-sm text-muted">ASN</dt>
                    <dd dir="ltr" className="font-mono text-sm">{location.asn ?? '—'}</dd>
                </div>
                <div>
                    <dt className="text-sm text-muted">نشانی عمومی</dt>
                    <dd dir="ltr" className="font-mono text-sm">{location.public_ip ?? '—'}</dd>
                </div>
                <div>
                    <dt className="text-sm text-muted">منبع تشخیص</dt>
                    <dd dir="ltr" className="font-mono text-sm">{location.source_label ?? '—'}</dd>
                </div>
                <div>
                    <dt className="text-sm text-muted">زمان تشخیص</dt>
                    <dd><PersianDate iso={location.looked_up_at} /></dd>
                </div>
            </dl>
            <p className="mt-3 text-xs leading-6 text-muted">این نتیجه از سرویس تشخیص IP خوانده شده و نصب بسته یا تغییر مخزن را انجام نداده است.</p>
        </Card>
    );
}
