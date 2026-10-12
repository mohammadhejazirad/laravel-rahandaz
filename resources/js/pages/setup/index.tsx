import { Head } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';
import { Alert } from '@/components/ui/alert';
import { Card } from '@/components/ui/card';
import { AppLayout } from '@/layouts/app-layout';
import type { HostFacts } from '@/types';

interface SetupPageProps {
    holding: boolean;
    workspace: {
        id: string;
        name: string;
        setup_completed_at: string | null;
    };
    facts: HostFacts | null;
}

export default function Setup({ holding, workspace, facts }: SetupPageProps) {
    return (
        <AppLayout title="راه‌اندازی">
            <Head title="راه‌اندازی" />
            <div className="mx-auto max-w-4xl space-y-4">
                <Alert tone="warning">
                    <span className="inline-flex items-center gap-2">
                        <AlertTriangle className="size-4" aria-hidden="true" />
                        این صفحه فقط خواندنی است و هیچ بسته‌ای نصب نمی‌کند.
                    </span>
                </Alert>
                {holding || facts === null ? (
                    <Card>
                        <h2 className="text-lg font-semibold">منتظر مالک</h2>
                        <p className="mt-2 text-muted">جزئیات میزبان فقط برای مالک فضای «{workspace.name}» نمایش داده می‌شود. تا پایان راه‌اندازی، اجرای دستور روی سرور خاموش است.</p>
                    </Card>
                ) : (
                    <Card>
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h2 className="text-lg font-semibold">{workspace.name}</h2>
                                <p className="text-sm text-muted">سازگاری Ubuntu هنوز ادعا نمی‌شود.</p>
                            </div>
                            <span className="inline-flex items-center gap-2 rounded-full border border-warning/40 px-3 py-1 text-sm text-warning">
                                <AlertTriangle className="size-4" aria-hidden="true" />
                                آزموده نشده
                            </span>
                        </div>
                        <dl className="mt-5 grid gap-3 sm:grid-cols-2">
                            <Fact label="سیستم" value={facts.os ? `${facts.os.name ?? 'نامشخص'} ${facts.os.version ?? ''}`.trim() : 'خوانده نشد'} />
                            <Fact label="هسته" value={facts.kernel} ltr />
                            <Fact label="معماری" value={facts.architecture} ltr />
                            <Fact label="PHP" value={facts.php} ltr />
                            <Fact label="حافظه آزاد" value={formatKib(facts.memory.available_kib)} ltr />
                            <Fact label="دیسک آزاد" value={formatBytes(facts.disk.free_bytes)} ltr />
                            <Fact label="حالت اجرا" value={facts.execution_mode} ltr />
                            <Fact label="زمان مشاهده" value={facts.observed_at} ltr />
                        </dl>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}

function Fact({ label, value, ltr = false }: { label: string; value: string; ltr?: boolean }) {
    return (
        <div className="rounded-lg bg-surface-muted px-3 py-2">
            <dt className="text-xs text-muted">{label}</dt>
            <dd className={ltr ? 'font-mono text-sm' : 'text-sm'} dir={ltr ? 'ltr' : undefined}>
                {value}
            </dd>
        </div>
    );
}

function formatKib(value: number | null): string {
    if (value === null) {
        return 'نامشخص';
    }

    return `${value.toLocaleString('en-US')} KiB`;
}

function formatBytes(value: number | null): string {
    if (value === null) {
        return 'نامشخص';
    }

    return `${value.toLocaleString('en-US')} B`;
}
