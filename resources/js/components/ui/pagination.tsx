import { Link } from "@inertiajs/react";
import { ChevronLeft, ChevronRight } from "lucide-react";

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
        <nav className="flex w-full min-w-0 max-w-full flex-wrap items-center justify-between gap-3 rounded-2xl border border-line bg-surface/80 px-3 py-2.5 text-sm" aria-label="صفحه‌بندی">
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
                    <Link className="inline-flex min-h-11 items-center gap-1 rounded-xl border border-line px-3 font-medium hover:bg-surface-muted" href={meta.prev_url} preserveScroll>
                        <ChevronRight className="size-4" aria-hidden="true" /> قبلی
                    </Link>
                ) : (
                    <span aria-disabled="true" className="inline-flex min-h-11 items-center gap-1 rounded-xl border border-line px-3 text-muted opacity-50"><ChevronRight className="size-4" aria-hidden="true" /> قبلی</span>
                )}
                {meta.next_url ? (
                    <Link className="inline-flex min-h-11 items-center gap-1 rounded-xl border border-line px-3 font-medium hover:bg-surface-muted" href={meta.next_url} preserveScroll>
                        بعدی <ChevronLeft className="size-4" aria-hidden="true" />
                    </Link>
                ) : (
                    <span aria-disabled="true" className="inline-flex min-h-11 items-center gap-1 rounded-xl border border-line px-3 text-muted opacity-50">بعدی <ChevronLeft className="size-4" aria-hidden="true" /></span>
                )}
            </div>
        </nav>
    );
}
