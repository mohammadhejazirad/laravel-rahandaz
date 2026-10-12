import { Head, Link, usePage } from "@inertiajs/react";
import { Card } from "@/components/ui/card";
import { AppLayout } from "@/layouts/app-layout";
import type { SharedProps } from "@/types";

interface DashboardPageProps {
    workspace: {
        uuid: string;
        name: string;
        slug: string;
    };
    execution_mode: string;
    operations: {
        queued: number;
        running: number;
        failed: number;
    };
    inventory: {
        projects: number;
        servers: number;
        metric_samples: number;
    };
}

export default function Dashboard({ workspace, execution_mode, operations, inventory }: DashboardPageProps) {
    const base = usePage<SharedProps>().props.app.base_path;

    return (
        <AppLayout title="داشبورد">
            <Head title="داشبورد" />
            <Card>
                <p className="text-sm text-muted">فضای کاری</p>
                <h2 className="text-2xl font-semibold">{workspace.name}</h2>
                <p className="mt-2 text-sm text-muted">
                    حالت اجرا:{" "}
                    <span className="font-mono text-ink" dir="ltr">
                        {execution_mode}
                    </span>
                </p>
            </Card>
            <div className="grid gap-4 md:grid-cols-3">
                <Metric label="در صف" value={operations.queued} href={`${base}/jobs`} />
                <Metric label="در حال اجرا" value={operations.running} href={`${base}/jobs`} />
                <Metric label="ناموفق" value={operations.failed} href={`${base}/jobs`} />
            </div>
            <div className="grid gap-4 md:grid-cols-3">
                <Metric label="پروژه‌ها" value={inventory.projects} href={`${base}/projects`} />
                <Metric label="سرورها" value={inventory.servers} href={`${base}/servers`} />
                <Card>
                    <p className="text-sm text-muted">نمونه متریک</p>
                    <p className="font-mono text-3xl font-semibold" dir="ltr">
                        {inventory.metric_samples.toLocaleString("en-US")}
                    </p>
                    <p className="mt-2 text-sm text-muted">
                        نمودار رسم نمی‌شود. نمونه تجمیعی برای نمایش سری زمانی هنوز از جمع‌آوری واقعی نمی‌آید.
                    </p>
                </Card>
            </div>
        </AppLayout>
    );
}

function Metric({ label, value, href }: { label: string; value: number; href: string }) {
    return (
        <Card>
            <p className="text-sm text-muted">{label}</p>
            <p className="font-mono text-3xl font-semibold" dir="ltr">
                {value.toLocaleString("en-US")}
            </p>
            <Link className="mt-2 inline-block text-sm text-accent" href={href}>
                مشاهده
            </Link>
        </Card>
    );
}
