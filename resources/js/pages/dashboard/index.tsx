import { Head } from '@inertiajs/react';
import { Card } from '@/components/ui/card';
import { AppLayout } from '@/layouts/app-layout';

interface DashboardPageProps {
    workspace: {
        id: string;
        name: string;
        slug: string;
    };
    execution_mode: string;
    operations: {
        queued: number;
        running: number;
        failed: number;
    };
    modules: {
        projects: 'not_installed';
        servers: 'not_installed';
    };
}

export default function Dashboard({ workspace, execution_mode, operations, modules }: DashboardPageProps) {
    return (
        <AppLayout title="داشبورد">
            <Head title="داشبورد" />
            <div className="space-y-4">
                <Card>
                    <p className="text-sm text-muted">فضای کاری</p>
                    <h2 className="text-2xl font-semibold">{workspace.name}</h2>
                    <p className="mt-2 text-sm text-muted">
                        حالت اجرا: <span className="font-mono text-ink" dir="ltr">{execution_mode}</span>
                    </p>
                    <p className="mt-1 text-sm text-muted">اجرای دستور میزبان در این نسخه خاموش است و هیچ عملیات موفقی از این پنل گزارش نمی‌شود.</p>
                </Card>
                <div className="grid gap-4 md:grid-cols-3">
                    <Metric label="در صف" value={operations.queued} />
                    <Metric label="در حال اجرا" value={operations.running} />
                    <Metric label="ناموفق" value={operations.failed} />
                </div>
                <Card>
                    <h2 className="text-lg font-semibold">ماژول‌ها</h2>
                    <ul className="mt-3 space-y-2 text-sm">
                        <li>پروژه‌ها: {labelFor(modules.projects)}</li>
                        <li>سرورها: {labelFor(modules.servers)}</li>
                    </ul>
                </Card>
            </div>
        </AppLayout>
    );
}

function Metric({ label, value }: { label: string; value: number }) {
    return (
        <Card>
            <p className="text-sm text-muted">{label}</p>
            <p className="font-mono text-3xl font-semibold" dir="ltr">{value.toLocaleString('en-US')}</p>
        </Card>
    );
}

function labelFor(state: 'not_installed'): string {
    return state === 'not_installed' ? 'هنوز نصب نشده است' : state;
}
