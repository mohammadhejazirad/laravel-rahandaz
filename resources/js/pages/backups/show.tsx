import { Head, Link, usePage } from '@inertiajs/react';
import { ResourceList, type ListRow } from '@/components/resource-list';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { StatusBadge } from '@/components/ui/status-badge';
import { PersianDate } from '@/components/dates/persian-date';
import { Pagination, type PageMeta } from '@/components/ui/pagination';
import { AppLayout } from '@/layouts/app-layout';
import type { SharedProps } from '@/types';
export default function BackupShow({ backup: b, restores }: { backup: { uuid: string; backup_type: string; state: string; size_bytes: number; checksum_sha256: string | null; encryption_status: string; verified_at: string | null; retention_until: string | null; job_uuid: string | null }; restores: { data: ListRow[]; meta: PageMeta }; restore_available: false }) {
    const { app } = usePage<SharedProps>().props;
    return <AppLayout title="جزئیات پشتیبان‌گیری"><Head title="جزئیات پشتیبان‌گیری" /><Button variant="secondary" asChild><Link href={`${app.base_path}/backups`}>بازگشت به پشتیبان‌گیری</Link></Button><Card><dl className="grid gap-5 sm:grid-cols-2"><div><dt className="text-muted">وضعیت</dt><dd><StatusBadge value={b.state} /></dd></div><div><dt className="text-muted">رمزنگاری</dt><dd><StatusBadge value={b.encryption_status} /></dd></div><div><dt className="text-muted">راستی‌آزمایی</dt><dd><PersianDate iso={b.verified_at} /></dd></div><div><dt className="text-muted">نگهداری تا</dt><dd><PersianDate iso={b.retention_until} /></dd></div><div className="sm:col-span-2"><dt className="text-muted">SHA-256</dt><dd dir="ltr" className="break-all font-mono text-xs">{b.checksum_sha256 ?? '—'}</dd></div></dl></Card>{b.job_uuid ? <Button variant="secondary" asChild><Link href={`${app.base_path}/jobs/${b.job_uuid}`}>عملیات مرتبط</Link></Button> : null}<Alert tone="warning">بازیابی، دانلود و حذف فایل تا بررسی واقعی مسیر خصوصی، مالکیت، checksum و Recovery در محیط ایزوله فعال نیستند. این ردیف گواهی بازیابی نیست.</Alert><h2 className="text-lg font-semibold">تاریخچه بازیابی</h2><ResourceList rows={restores.data} label="تاریخچه بازیابی" columns={[{key:'uuid',label:'شناسه',kind:'technical'},{key:'status',label:'وضعیت',kind:'status'},{key:'approval_state',label:'تأیید',kind:'status'}]} /><Pagination meta={restores.meta} /></AppLayout>;
}
