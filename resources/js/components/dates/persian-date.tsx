import { usePage } from "@inertiajs/react";
import { formatJalali } from "@/lib/datetime";
import type { SharedProps } from "@/types";

export function PersianDate({ iso }: { iso: string | null }) {
    const timezone = usePage<SharedProps>().props.auth.user?.timezone || "Asia/Tehran";

    if (!iso) {
        return <span className="text-muted">ثبت نشده</span>;
    }

    const shown = formatJalali(iso, timezone);

    if (!shown) {
        return <span className="text-muted">تاریخ نامعتبر</span>;
    }

    return (
        <time className="font-mono text-ink" dateTime={iso} dir="ltr" title={iso}>
            {shown}
        </time>
    );
}
