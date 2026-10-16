import { Head, Link, usePage } from "@inertiajs/react";
import { AlertTriangle } from "lucide-react";
import { AppLogo } from "@/components/branding/app-logo";
import { Alert } from "@/components/ui/alert";
import { Card } from "@/components/ui/card";
import { AppLayout } from "@/layouts/app-layout";
import { Button } from '@/components/ui/button';
import type { SharedProps, HostFacts } from "@/types";

interface SetupPageProps {
    holding: boolean;
    workspace: {
        uuid: string;
        name: string;
        setup_completed_at: string | null;
    };
    facts: HostFacts | null;
}

export default function Setup({ holding, workspace, facts }: SetupPageProps) {
    const base = usePage<SharedProps>().props.app.base_path;
    return (
        <AppLayout title="راه‌اندازی" description="مشاهده امن مشخصات همین میزبان، بدون نصب بسته یا تغییر پیکربندی سیستم.">
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
                        <p className="mt-2 text-muted">
                            جزئیات میزبان فقط برای مالک فضای «{workspace.name}»
                            نمایش داده می‌شود. تا پایان راه‌اندازی، اجرای دستور
                            روی سرور خاموش است.
                        </p>
                    </Card>
                ) : (
                    <Card className="overflow-hidden p-0">
                        <div className="border-b border-line bg-surface-muted/55 p-5 sm:p-6">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <AppLogo
                                    variant="symbol"
                                    size="sm"
                                    decorative
                                    className="mb-3"
                                />
                                <h2 className="text-lg font-semibold">
                                    {workspace.name}
                                </h2>
                                <p className="text-sm text-muted">
                                    سازگاری Ubuntu هنوز ادعا نمی‌شود.
                                </p>
                            </div>
                            <span className="inline-flex items-center gap-2 rounded-full border border-warning/40 px-3 py-1 text-sm text-warning">
                                <AlertTriangle
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                آزموده نشده
                            </span>
                        </div>
                        </div>
                        <dl className="grid gap-px bg-line sm:grid-cols-2">
                            <Fact
                                label="سیستم"
                                value={
                                    facts.os
                                        ? `${facts.os.name ?? "نامشخص"} ${facts.os.version ?? ""}`.trim()
                                        : "خوانده نشد"
                                }
                            />
                            <Fact label="هسته" value={facts.kernel} ltr />
                            <Fact
                                label="معماری"
                                value={facts.architecture}
                                ltr
                            />
                            <Fact label="PHP" value={facts.php} ltr />
                            <Fact
                                label="حافظه آزاد"
                                value={formatKib(facts.memory.available_kib)}
                                ltr
                            />
                            <Fact
                                label="دیسک آزاد"
                                value={formatBytes(facts.disk.free_bytes)}
                                ltr
                            />
                            <Fact
                                label="حالت اجرا"
                                value={facts.execution_mode}
                                ltr
                            />
                            <Fact
                                label="زمان مشاهده"
                                value={facts.observed_at}
                                ltr
                            />
                        </dl>
                    </Card>
                )}
                <Card><h2 className="font-semibold">مسیر شروع استفاده</h2><p className="mt-2 text-sm leading-7 text-muted">این صفحه مشاهدهٔ مشخصات است و نصب انجام نمی‌دهد. قدم بعدی را متناسب با وضعیت نصب انتخاب کنید.</p><ol className="mt-4 grid gap-3 sm:grid-cols-3">{[['tutorials/installation', '۱. آماده‌سازی نصب', 'راهنما، پیش‌نیازها و محدودیت‌های نصب'], ['git', '۲. اتصال منبع کد', 'افزودن حساب و انتخاب مخزن‌ها'], ['projects', '۳. بررسی پروژه‌ها', 'منبع، محیط‌ها و درخواست استقرار']].map(([path, title, detail]) => <li key={path} className="rounded-xl border border-line p-3"><h3 className="text-sm font-semibold">{title}</h3><p className="my-3 text-xs leading-6 text-muted">{detail}</p><Button asChild variant="secondary"><Link href={`${base}/${path}`}>بازکردن این مرحله</Link></Button></li>)}</ol></Card>
            </div>
        </AppLayout>
    );
}

function Fact({
    label,
    value,
    ltr = false,
}: {
    label: string;
    value: string;
    ltr?: boolean;
}) {
    return (
        <div className="bg-surface px-5 py-4 sm:px-6">
            <dt className="text-xs font-medium text-muted">{label}</dt>
            <dd
                className={
                    ltr
                        ? "mt-1 font-mono text-sm text-ink"
                        : "mt-1 text-sm font-medium text-ink"
                }
                dir={ltr ? "ltr" : undefined}
            >
                {value}
            </dd>
        </div>
    );
}

function formatKib(value: number | null): string {
    if (value === null) {
        return "نامشخص";
    }

    return `${value.toLocaleString("en-US")} KiB`;
}

function formatBytes(value: number | null): string {
    if (value === null) {
        return "نامشخص";
    }

    return `${value.toLocaleString("en-US")} B`;
}
