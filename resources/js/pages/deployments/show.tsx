import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { Card } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Alert } from '@/components/ui/alert';
import { StatusBadge } from '@/components/ui/status-badge';
import { PersianDate } from '@/components/dates/persian-date';
import { useVisiblePoll } from '@/hooks/use-visible-poll';
import { AppLayout } from '@/layouts/app-layout';
import type { SharedProps } from '@/types';
export default function DeploymentShow({ deployment: d, can_operate, rollback_block_reason }: { deployment: { uuid: string; project_name: string; environment_name: string; commit_sha: string | null; artifact_digest: string | null; status: string; build_status: string; health_status: string; failure_code: string | null; job_uuid: string | null; job_status: string | null; active_release_uuid: string | null; previous_release_uuid: string | null; created_at: string }; can_operate: boolean; rollback_block_reason: string }) {
    const { app } = usePage<SharedProps>().props;
    const form = useForm<{ operation?: string }>({});
    useVisiblePoll(['queued', 'running', 'verifying', 'rolling_back'].includes(d.status), 'deployment', 10000);
    const act = (action: 'cancel' | 'retry') => form.post(`${app.base_path}/deployments/${d.uuid}/${action}`);
    return <AppLayout title="جزئیات استقرار" description={`${d.project_name} / ${d.environment_name}`}><Head title="جزئیات استقرار" /><Button variant="secondary" asChild><Link href={`${app.base_path}/deployments`}>بازگشت به استقرارها</Link></Button>
        {form.errors.operation ? <Alert tone="danger">{form.errors.operation}</Alert> : null}
        <Card><dl className="grid gap-5 sm:grid-cols-2"><div><dt className="text-muted">وضعیت استقرار</dt><dd><StatusBadge value={d.status} /></dd></div><div><dt className="text-muted">ثبت</dt><dd><PersianDate iso={d.created_at} /></dd></div><div><dt className="text-muted">ساخت</dt><dd><StatusBadge value={d.build_status} /></dd></div><div><dt className="text-muted">سلامت</dt><dd><StatusBadge value={d.health_status} /></dd></div><div className="sm:col-span-2"><dt className="text-muted">Commit / digest ثابت</dt><dd dir="ltr" className="break-all font-mono text-sm">{d.commit_sha ?? d.artifact_digest ?? '—'}</dd></div><div><dt className="text-muted">Release فعال</dt><dd dir="ltr" className="break-all font-mono text-xs">{d.active_release_uuid ?? '—'}</dd></div><div><dt className="text-muted">Release قبلی</dt><dd dir="ltr" className="break-all font-mono text-xs">{d.previous_release_uuid ?? '—'}</dd></div></dl></Card>
        {d.failure_code ? <Alert tone="danger">استقرار انجام نشد. کد تشخیص: <bdi dir="ltr" className="font-mono">{d.failure_code}</bdi></Alert> : null}
        <div className="flex flex-wrap gap-3">{d.job_uuid ? <Button variant="secondary" asChild><Link href={`${app.base_path}/jobs/${d.job_uuid}`}>وضعیت و لاگ عملیات</Link></Button> : null}{can_operate && d.status === 'queued' ? <Button variant="danger" loading={form.processing} onClick={() => act('cancel')}>لغو استقرار شروع‌نشده</Button> : null}{can_operate && ['failed', 'cancelled'].includes(d.status) && d.commit_sha ? <Button loading={form.processing} onClick={() => act('retry')}>ثبت تلاش دوباره با همین Commit</Button> : null}</div><Alert tone="warning">{rollback_block_reason}</Alert>
    </AppLayout>;
}
