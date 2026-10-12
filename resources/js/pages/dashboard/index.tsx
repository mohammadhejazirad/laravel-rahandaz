import { Head, Link, usePage } from "@inertiajs/react";
import { Activity, ArrowUpLeft, CircleX, Clock3, FolderGit2, Server, type LucideIcon } from "lucide-react";
import { Badge } from "@/components/ui/badge";
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
        <AppLayout title="داشبورد" description="نمای فشرده‌ای از موجودی واقعی و وضعیت Jobهای فضای کاری، بدون نمودار یا سلامت ساختگی.">
            <Head title="داشبورد" />
            <Card className="relative overflow-hidden border-0 bg-[#0b1220] p-6 text-white sm:p-7">
                <div className="absolute -start-20 -top-24 size-64 rounded-full border border-white/10" aria-hidden="true" />
                <div className="absolute -bottom-24 end-10 size-56 rounded-full bg-indigo-500/15 blur-3xl" aria-hidden="true" />
                <div className="relative flex flex-wrap items-end justify-between gap-5">
                    <div>
                        <p className="text-sm text-slate-300">فضای کاری فعال</p>
                        <h2 className="mt-1 text-2xl font-bold sm:text-3xl">{workspace.name}</h2>
                        <p className="technical-value mt-2 text-xs text-slate-400">{workspace.slug}</p>
                    </div>
                    <div className="rounded-2xl border border-white/10 bg-white/[0.06] px-4 py-3 backdrop-blur-sm">
                        <p className="text-xs text-slate-400">وضعیت کنترل‌پلین</p>
                        <Badge className="mt-2 border-amber-300/25 bg-amber-300/10 text-amber-200">
                            <span className="size-1.5 rounded-full bg-current" aria-hidden="true" />
                            <span className="technical-value">{execution_mode}</span>
                        </Badge>
                    </div>
                </div>
            </Card>
            <SectionTitle title="وضعیت عملیات" description="شمار Jobهای پایدار ثبت‌شده در پایگاه" />
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <Metric icon={Clock3} label="در صف" value={operations.queued} href={`${base}/jobs`} tone="info" />
                <Metric icon={Activity} label="در حال اجرا" value={operations.running} href={`${base}/jobs`} tone="success" />
                <Metric icon={CircleX} label="ناموفق" value={operations.failed} href={`${base}/jobs`} tone="danger" />
            </div>
            <SectionTitle title="موجودی فضای کاری" description="منابعی که واقعاً برای این فضای کاری ثبت شده‌اند" />
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <Metric icon={FolderGit2} label="پروژه‌ها" value={inventory.projects} href={`${base}/projects`} />
                <Metric icon={Server} label="سرورها" value={inventory.servers} href={`${base}/servers`} />
                <Card className="border-dashed">
                    <div className="flex items-start justify-between gap-3">
                        <div>
                            <p className="text-sm text-muted">نمونه متریک</p>
                            <p className="technical-value mt-1 text-3xl font-bold">{inventory.metric_samples.toLocaleString("en-US")}</p>
                        </div>
                        <span className="grid size-10 place-items-center rounded-xl bg-surface-muted text-muted" aria-hidden="true"><Activity className="size-5" /></span>
                    </div>
                    <p className="mt-2 text-sm text-muted">
                        نمودار رسم نمی‌شود. نمونه تجمیعی برای نمایش سری زمانی هنوز از جمع‌آوری واقعی نمی‌آید.
                    </p>
                </Card>
            </div>
        </AppLayout>
    );
}

const metricTones = {
    neutral: "bg-surface-muted text-muted",
    info: "bg-info-soft text-info",
    success: "bg-success-soft text-success",
    danger: "bg-danger-soft text-danger",
};

function Metric({
    icon: Icon,
    label,
    value,
    href,
    tone = "neutral",
}: {
    icon: LucideIcon;
    label: string;
    value: number;
    href: string;
    tone?: keyof typeof metricTones;
}) {
    return (
        <Card className="group transition-[border-color,transform,box-shadow] hover:-translate-y-0.5 hover:border-accent/30 hover:shadow-md">
            <div className="flex items-start justify-between gap-3">
                <div>
                    <p className="text-sm text-muted">{label}</p>
                    <p className="technical-value mt-1 text-3xl font-bold">{value.toLocaleString("en-US")}</p>
                </div>
                <span className={`grid size-10 place-items-center rounded-xl ${metricTones[tone]}`} aria-hidden="true"><Icon className="size-5" /></span>
            </div>
            <Link className="mt-4 inline-flex items-center gap-1 text-sm font-semibold text-accent" href={href}>
                مشاهده جزئیات
                <ArrowUpLeft className="size-4 transition-transform group-hover:-translate-x-0.5 group-hover:-translate-y-0.5" aria-hidden="true" />
            </Link>
        </Card>
    );
}

function SectionTitle({ title, description }: { title: string; description: string }) {
    return (
        <div className="flex flex-wrap items-end justify-between gap-2 pt-1">
            <h2 className="text-base font-bold text-ink sm:text-lg">{title}</h2>
            <p className="text-xs text-muted sm:text-sm">{description}</p>
        </div>
    );
}
