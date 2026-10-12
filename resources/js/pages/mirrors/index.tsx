import { Head } from "@inertiajs/react";
import { PersianDate } from "@/components/dates/persian-date";
import { Alert } from "@/components/ui/alert";
import { EmptyState } from "@/components/ui/empty-state";
import { Pagination, type PageMeta } from "@/components/ui/pagination";
import { StatusBadge } from "@/components/ui/status-badge";
import { TableFrame } from "@/components/ui/table-frame";
import { AppLayout } from "@/layouts/app-layout";

interface MirrorRow {
    uuid: string;
    provider: string;
    software_kind: string;
    country: string | null;
    base_url: string;
    trust_status: string;
    last_checked_at: string | null;
}

export default function MirrorsIndex({
    mirrors,
    apply_available,
}: {
    mirrors: { data: MirrorRow[]; meta: PageMeta };
    apply_available: false;
}) {
    return (
        <AppLayout title="میرورها" description="منابع ثبت‌شده برای دریافت بسته‌ها همراه وضعیت اعتماد و زمان آخرین بررسی.">
            <Head title="میرورها" />
            {apply_available ? null : (
                <Alert tone="warning">اعمال یا بازگشت میرور از این صفحه انجام نمی‌شود. اعتماد «نامزد» به‌معنی آزموده‌بودن نیست.</Alert>
            )}
            {mirrors.data.length === 0 ? (
                <EmptyState title="میروری ثبت نشده است">نشانی‌های میرور پس از ثبت در پایگاه اینجا دیده می‌شوند.</EmptyState>
            ) : (
                <TableFrame label="فهرست میرورها">
                    <thead className="bg-surface-muted text-muted">
                        <tr>
                            <th className="px-3 py-2 text-start font-medium">نرم‌افزار</th>
                            <th className="px-3 py-2 text-start font-medium">نشانی</th>
                            <th className="px-3 py-2 text-start font-medium">اعتماد</th>
                            <th className="px-3 py-2 text-start font-medium">آخرین آزمون</th>
                        </tr>
                    </thead>
                    <tbody>
                        {mirrors.data.map((mirror) => (
                            <tr key={mirror.uuid} className="border-t border-line">
                                <td className="px-3 py-2">
                                    <span className="font-mono" dir="ltr">
                                        {mirror.software_kind}
                                    </span>
                                    <div className="text-xs text-muted">{mirror.provider}</div>
                                </td>
                                <td className="px-3 py-2 font-mono text-xs" dir="ltr">
                                    {mirror.base_url}
                                </td>
                                <td className="px-3 py-2">
                                    <StatusBadge value={mirror.trust_status} />
                                </td>
                                <td className="px-3 py-2">
                                    <PersianDate iso={mirror.last_checked_at} />
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </TableFrame>
            )}
            <Pagination meta={mirrors.meta} />
        </AppLayout>
    );
}
