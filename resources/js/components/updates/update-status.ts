export interface ReleaseHistoryItem {
    result: string | null;
    latest_version: string | null;
    update_available: boolean;
    published_at: string | null;
    notes_url: string | null;
    release_url: string | null;
    checked_at: string | null;
    error_code: string | null;
}

export interface ReleaseStatus {
    state: string;
    installed_version: string;
    latest_version: string | null;
    channel: string;
    published_at: string | null;
    notes_url: string | null;
    release_url: string | null;
    update_available: boolean;
    checked_at: string | null;
    verified_at: string | null;
    result: string | null;
    error_code: string | null;
    fresh: boolean;
    command: string;
    history_command: string;
    version_command: string;
    php: string;
    history: ReleaseHistoryItem[];
    panel_executes_update: false;
}

export const updateStateLabel: Record<string, string> = {
    up_to_date: "به‌روز",
    update_available: "نسخه جدید موجود است",
    checking: "در حال بررسی",
    check_failed: "بررسی ناموفق",
    offline_cached: "آفلاین، نتیجه ذخیره‌شده",
    unsupported: "به‌روزرسانی پشتیبانی نمی‌شود",
    update_running: "به‌روزرسانی در حال اجرا",
    update_succeeded: "به‌روزرسانی انجام شد",
    update_failed: "به‌روزرسانی ناموفق",
    recovery_required: "بازیابی دستی لازم است",
    not_checked: "هنوز بررسی نشده",
    source_unconfigured: "منبع نسخه تنظیم نشده",
};

export function updateStateText(state: string): string {
    return updateStateLabel[state] ?? "وضعیت نامشخص";
}
