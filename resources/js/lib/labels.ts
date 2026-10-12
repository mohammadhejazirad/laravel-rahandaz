const labels: Record<string, string> = {
    queued: "در صف",
    running: "در حال اجرا",
    waiting_retry: "منتظر تلاش دوباره",
    succeeded: "موفق",
    failed: "ناموفق",
    failure: "شکست",
    denied: "ردشده",
    interrupted: "قطع‌شده",
    cancelling: "در حال لغو",
    cancelled: "لغوشده",
    reconciling: "در حال تطبیق",
    draft: "پیش‌نویس",
    active: "فعال",
    disabled: "غیرفعال",
    archived: "بایگانی",
    pending_probe: "منتظر بررسی",
    compatible: "سازگار",
    degraded: "ناپایدار",
    unsupported: "پشتیبانی‌نشده",
    untested: "آزموده‌نشده",
    supported: "پشتیبانی‌شده",
    trusted: "مورد اعتماد",
    candidate: "نامزد",
    web: "وب",
    runtime: "زمان اجرا",
    database: "دیتابیس",
    cache: "کش",
    tool: "ابزار",
    security: "امنیت",
    other: "سایر",
    info: "اطلاع",
    success: "موفق",
    warning: "هشدار",
    danger: "خطر",
    eol: "پایان عمر",
};

export function labelOf(value: string): string {
    return labels[value] ?? value;
}

export function statusTone(
    value: string,
): "neutral" | "success" | "warning" | "danger" | "info" {
    if (value === "succeeded" || value === "success" || value === "trusted" || value === "active") {
        return "success";
    }

    if (value === "failed" || value === "failure" || value === "denied" || value === "danger" || value === "unsupported" || value === "eol") {
        return "danger";
    }

    if (
        value === "warning" ||
        value === "candidate" ||
        value === "untested" ||
        value === "degraded" ||
        value === "waiting_retry" ||
        value === "cancelling"
    ) {
        return "warning";
    }

    if (value === "running" || value === "info" || value === "queued") {
        return "info";
    }

    return "neutral";
}
