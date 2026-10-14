import { Head, router, usePage } from "@inertiajs/react";
import { useState } from "react";
import { UpdateAvailableCard } from "@/components/updates/UpdateAvailableCard";
import { updateStateText, type ReleaseStatus } from "@/components/updates/update-status";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { AppLayout } from "@/layouts/app-layout";
import type { SharedProps } from "@/types";

export default function SystemUpdates({ release }: { release: ReleaseStatus }) {
    const base = usePage<SharedProps>().props.app.base_path;
    const [copied, setCopied] = useState(false);
    const published = release.published_at ?? "ثبت نشده";

    async function copyCommand() {
        try {
            await navigator.clipboard.writeText(release.command);
            setCopied(true);
        } catch {
            setCopied(false);
        }
    }

    return (
        <AppLayout title="به‌روزرسانی" description="وضعیت نسخه راه‌انداز، یادداشت انتشار و دستور به‌روزرسانی. پنل این دستور را اجرا نمی‌کند.">
            <Head title="به‌روزرسانی راه‌انداز" />
            <UpdateAvailableCard release={release} detailsHref={`${base}/settings/updates`} />
            <Card>
                <h2 className="text-base font-bold">وضعیت بررسی</h2>
                <p className="mt-2 text-sm leading-6 text-muted">
                    {updateStateText(release.state)}. کانال مجاز از تنظیم سرور خوانده می‌شود و در این صفحه عوض نمی‌شود.
                    {release.fresh ? " این نتیجه همان بررسی آخر است." : " اگر ساعت بررسی قدیمی است، آن را بررسی تازه حساب نکنید."}
                </p>
                <dl className="mt-4 grid gap-3 sm:grid-cols-2">
                    <div>
                        <dt className="text-xs text-muted">کانال</dt>
                        <dd className="technical-value mt-1 text-sm">{release.channel}</dd>
                    </div>
                    <div>
                        <dt className="text-xs text-muted">نسخه دیده‌شده</dt>
                        <dd className="technical-value mt-1 text-sm">{release.latest_version ?? "ندارد"}</dd>
                    </div>
                    <div>
                        <dt className="text-xs text-muted">زمان انتشار</dt>
                        <dd className="technical-value mt-1 text-sm">{published}</dd>
                    </div>
                    <div>
                        <dt className="text-xs text-muted">زمان بررسی معتبر</dt>
                        <dd className="technical-value mt-1 text-sm">{release.verified_at ?? "ثبت نشده"}</dd>
                    </div>
                </dl>
                <div className="mt-4 flex flex-wrap gap-2">
                    {release.notes_url ? (
                        <a className="inline-flex rounded-md border border-line px-3 py-2 text-sm font-semibold" href={release.notes_url}>
                            مشاهده تغییرات
                        </a>
                    ) : null}
                    <Button type="button" variant="secondary" onClick={() => router.post(`${base}/settings/updates/check`, {})}>
                        بررسی دوباره نسخه
                    </Button>
                </div>
            </Card>
            <Card>
                <h2 className="text-base font-bold">دستور به‌روزرسانی</h2>
                <p className="mt-2 text-sm leading-6 text-muted">
                    به‌روزرسانی فقط با نسخه، مانیفست، امضا، کلید عمومی و فایل انتشار انجام می‌شود. پنل مدیریت این فرمان را اجرا نمی‌کند.
                    پیش از اجرا باید نسخه پشتیبان متعلق به راه‌انداز آماده باشد و PHP پنل همان ۸٫۴ بماند. پروژه‌ها، استخرهای PHP و سایت‌های دیگر تغییر نمی‌کنند.
                    اگر تغییر اسکیما ناسازگار باشد، بازگشت خودکار پایگاه انجام نمی‌شود.
                </p>
                <label className="mt-4 block text-xs text-muted" htmlFor="update-command">
                    فرمان قابل کپی
                </label>
                <input
                    id="update-command"
                    className="technical-value mt-1 w-full rounded-md border border-line bg-surface px-3 py-2 text-sm"
                    dir="ltr"
                    readOnly
                    value={release.command}
                />
                <div className="mt-3 flex flex-wrap gap-2">
                    <Button type="button" onClick={copyCommand}>
                        {copied ? "کپی شد" : "کپی دستور به‌روزرسانی"}
                    </Button>
                </div>
                <p className="technical-value mt-3 text-xs text-muted" dir="ltr">
                    {release.version_command} · {release.history_command}
                </p>
            </Card>
            <Card>
                <h2 className="text-base font-bold">تاریخچه بررسی</h2>
                {release.history.length === 0 ? (
                    <p className="mt-2 text-sm text-muted">
                        هنوز بررسی‌ای در پنل ثبت نشده است. تاریخچه اجرای به‌روزرسانی روی سرور با فرمان history دیده می‌شود.
                    </p>
                ) : (
                    <ul className="mt-3 divide-y divide-line">
                        {release.history.map((item) => (
                            <li key={`${item.checked_at ?? "none"}-${item.result ?? "none"}`} className="flex flex-wrap items-center justify-between gap-2 py-3 text-sm">
                                <span>{updateStateText(item.update_available ? "update_available" : "up_to_date")}</span>
                                <span className="technical-value text-xs">{item.latest_version ?? "—"} · {item.checked_at ?? "بدون زمان"}</span>
                            </li>
                        ))}
                    </ul>
                )}
            </Card>
        </AppLayout>
    );
}
