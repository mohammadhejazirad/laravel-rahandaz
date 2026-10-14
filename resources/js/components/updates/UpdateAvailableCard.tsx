import { Link } from "@inertiajs/react";
import { RefreshCw } from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { Card } from "@/components/ui/card";
import { updateStateText, type ReleaseStatus } from "@/components/updates/update-status";

export function UpdateAvailableCard({ release, detailsHref }: { release: ReleaseStatus; detailsHref: string }) {
    const title = release.update_available
        ? "نسخه جدید راه‌انداز منتشر شد"
        : "نسخه پنل مدیریت راه‌انداز";
    const description = release.update_available && release.latest_version
        ? `نسخه ${release.latest_version} راه‌انداز منتشر شده است. نسخه فعلی شما ${release.installed_version} است.`
        : `نسخه نصب‌شده ${release.installed_version} است. بررسی نسخه از داخل ورود انجام نمی‌شود.`;

    return (
        <Card>
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="min-w-0">
                    <p className="text-sm text-muted">سیستم به‌روزرسانی راه‌انداز</p>
                    <h2 className="mt-1 text-base font-bold text-ink sm:text-lg">{title}</h2>
                    <p className="mt-2 text-sm leading-6 text-muted">{description}</p>
                </div>
                <Badge>
                    <RefreshCw className="size-3.5" aria-hidden="true" />
                    {updateStateText(release.state)}
                </Badge>
            </div>
            <dl className="mt-4 grid gap-3 sm:grid-cols-3">
                <div>
                    <dt className="text-xs text-muted">نسخه فعلی</dt>
                    <dd className="technical-value mt-1 text-sm">{release.installed_version}</dd>
                </div>
                <div>
                    <dt className="text-xs text-muted">آخرین بررسی</dt>
                    <dd className="technical-value mt-1 text-sm">{release.checked_at ?? "ثبت نشده"}</dd>
                </div>
                <div>
                    <dt className="text-xs text-muted">PHP پنل</dt>
                    <dd className="technical-value mt-1 text-sm">{release.php}</dd>
                </div>
            </dl>
            <Link className="mt-4 inline-flex text-sm font-semibold text-accent" href={detailsHref}>
                مشاهده جزئیات به‌روزرسانی
            </Link>
        </Card>
    );
}
