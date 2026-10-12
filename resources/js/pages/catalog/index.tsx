import { Head } from "@inertiajs/react";
import { Alert } from "@/components/ui/alert";
import { EmptyState } from "@/components/ui/empty-state";
import { Pagination, type PageMeta } from "@/components/ui/pagination";
import { StatusBadge } from "@/components/ui/status-badge";
import { TableFrame } from "@/components/ui/table-frame";
import { AppLayout } from "@/layouts/app-layout";
import { labelOf } from "@/lib/labels";

interface PackageRow {
    uuid: string;
    key: string;
    display_name: string;
    service_kind: string;
    version_count: number;
    lifecycles: string[];
}

export default function CatalogIndex({
    packages,
    install_available,
}: {
    packages: { data: PackageRow[]; meta: PageMeta };
    install_available: false;
}) {
    return (
        <AppLayout title="نرم‌افزارها" description="تعریف‌های نسخه‌دار کاتالوگ؛ نمایش در این صفحه به‌معنی پشتیبانی یا امکان نصب نیست.">
            <Head title="نرم‌افزارها" />
            {install_available ? null : (
                <Alert tone="info">نصب از این فهرست انجام نمی‌شود. وضعیت نسخه تا آزمون واقعی «آزموده‌نشده» می‌ماند.</Alert>
            )}
            {packages.data.length === 0 ? (
                <EmptyState title="بسته‌ای در کاتالوگ نیست">کاتالوگ پس از seed یا ثبت تعریف بسته اینجا دیده می‌شود.</EmptyState>
            ) : (
                <TableFrame label="کاتالوگ نرم‌افزارها">
                    <thead className="bg-surface-muted text-muted">
                        <tr>
                            <th className="px-3 py-2 text-start font-medium">نام</th>
                            <th className="px-3 py-2 text-start font-medium">گونه</th>
                            <th className="px-3 py-2 text-start font-medium">نسخه‌ها</th>
                            <th className="px-3 py-2 text-start font-medium">چرخه</th>
                        </tr>
                    </thead>
                    <tbody>
                        {packages.data.map((item) => (
                            <tr key={item.uuid} className="border-t border-line">
                                <td className="px-3 py-2">
                                    {item.display_name}
                                    <div className="font-mono text-xs text-muted" dir="ltr">
                                        {item.key}
                                    </div>
                                </td>
                                <td className="px-3 py-2">{labelOf(item.service_kind)}</td>
                                <td className="px-3 py-2 font-mono" dir="ltr">
                                    {item.version_count}
                                </td>
                                <td className="px-3 py-2">
                                    <span className="inline-flex flex-wrap gap-1">
                                        {item.lifecycles.map((lifecycle) => (
                                            <StatusBadge key={lifecycle} value={lifecycle} />
                                        ))}
                                    </span>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </TableFrame>
            )}
            <Pagination meta={packages.meta} />
        </AppLayout>
    );
}
