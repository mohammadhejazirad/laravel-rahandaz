import { Link } from "@inertiajs/react";

export interface PageMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    prev_url: string | null;
    next_url: string | null;
}

export function Pagination({ meta }: { meta: PageMeta }) {
    if (meta.last_page <= 1) {
        return null;
    }

    return (
        <nav className="flex w-full min-w-0 max-w-full flex-wrap items-center justify-between gap-3 rounded-xl border border-line bg-surface/80 px-3 py-2 text-sm" aria-label="صفحه‌بندی">
            <p className="text-muted">
                صفحه{" "}
                <span className="font-mono text-ink" dir="ltr">
                    {meta.current_page}
                </span>{" "}
                از{" "}
                <span className="font-mono text-ink" dir="ltr">
                    {meta.last_page}
                </span>
                <span className="hidden sm:inline"> · {meta.total.toLocaleString("fa-IR")} مورد</span>
            </p>
            <div className="flex gap-2">
                {meta.prev_url ? (
                    <Link className="inline-flex min-h-11 items-center rounded-lg border border-line px-3 hover:bg-surface-muted" href={meta.prev_url} preserveScroll>
                        قبلی
                    </Link>
                ) : (
                    <span aria-disabled="true" className="inline-flex min-h-11 items-center rounded-lg border border-line px-3 text-muted opacity-60">قبلی</span>
                )}
                {meta.next_url ? (
                    <Link className="inline-flex min-h-11 items-center rounded-lg border border-line px-3 hover:bg-surface-muted" href={meta.next_url} preserveScroll>
                        بعدی
                    </Link>
                ) : (
                    <span aria-disabled="true" className="inline-flex min-h-11 items-center rounded-lg border border-line px-3 text-muted opacity-60">بعدی</span>
                )}
            </div>
        </nav>
    );
}
