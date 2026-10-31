import { PageSections } from '@/components/ui/page-sections';
import { PersianDate } from '@/components/dates/persian-date';
import { Head, router, usePage } from "@inertiajs/react";
import { UpdateAvailableCard } from "@/components/updates/UpdateAvailableCard";
import { updateStateText, type ReleaseStatus } from "@/components/updates/update-status";
import { CopyField } from '@/components/ui/copy-field';
import { RequestButton } from '@/components/ui/request-button';
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { AppLayout } from "@/layouts/app-layout";
import type { SharedProps } from "@/types";

export default function SystemUpdates({ release }: { release: ReleaseStatus }) {
    const base = usePage<SharedProps>().props.app.base_path;
    return (
        <AppLayout title="به‌روزرسانی" description="وضعیت نسخه راه‌انداز، یادداشت انتشار و دستور به‌روزرسانی. پنل این دستور را اجرا نمی‌کند.">
            <Head title="به‌روزرسانی راه‌انداز" />
            <UpdateAvailableCard release={release} detailsHref={`${base}/settings/updates`} />
            <PageSections label="به‌روزرسانی راه‌انداز" sections={[
                { id: 'version', label: 'وضعیت نسخه', content: <>            <Card>
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
                        <dd className="technical-value mt-1 text-sm"><PersianDate iso={release.published_at} /></dd>
                    </div>
                    <div>
                        <dt className="text-xs text-muted">زمان بررسی معتبر</dt>
                        <dd className="technical-value mt-1 text-sm"><PersianDate iso={release.verified_at} /></dd>
                    </div>
                </dl>
                <div className="mt-4 flex flex-wrap gap-2">
                    {release.notes_url ? (
                        <Button asChild variant="secondary"><a href={release.notes_url}>
                            مشاهده تغییرات
                        </a></Button>
                    ) : null}
                    <RequestButton variant="secondary" loadingLabel="در حال بررسی نسخه…" request={finish => router.post(`${base}/settings/updates/check`, {}, { onFinish: finish })}>
                        بررسی دوباره نسخه
                    </RequestButton>
                </div>
            </Card>
</> },
                { id: 'instructions', label: 'راهنمای اجرا', content: <>            <Card>
                <h2 className="text-base font-bold">دستور به‌روزرسانی</h2>
                <p className="mt-2 text-sm leading-6 text-muted">
                    به‌روزرسانی فقط با نسخه، مانیفست، امضا، کلید عمومی و فایل انتشار انجام می‌شود. پنل مدیریت این فرمان را اجرا نمی‌کند.
                    پیش از اجرا باید نسخه پشتیبان متعلق به راه‌انداز آماده باشد و PHP پنل همان ۸٫۴ بماند. پروژه‌ها، استخرهای PHP و سایت‌های دیگر تغییر نمی‌کنند.
                    اگر تغییر اسکیما ناسازگار باشد، بازگشت خودکار پایگاه انجام نمی‌شود.
                </p>
                <div className="mt-4"><CopyField value={release.command} label="فرمان قابل کپی" copyLabel="کپی دستور به‌روزرسانی" /></div>
                <p className="technical-value mt-3 text-xs text-muted" dir="ltr">
                    {release.version_command} · {release.history_command}
                </p>
            </Card>
</> },
                { id: 'checks', label: 'تاریخچهٔ بررسی', content: <>            <Card>
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
                                <span className="technical-value text-xs">{item.latest_version ?? "—"} · <PersianDate iso={item.checked_at} /></span>
                            </li>
                        ))}
                    </ul>
                )}
            </Card>
</> },
            ]} />
        </AppLayout>
    );
}
