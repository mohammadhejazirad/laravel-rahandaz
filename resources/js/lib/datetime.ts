const formatterCache = new Map<string, Intl.DateTimeFormat>();

function formatter(timeZone: string): Intl.DateTimeFormat {
    const existing = formatterCache.get(timeZone);

    if (existing) {
        return existing;
    }

    const created = new Intl.DateTimeFormat("fa-IR-u-ca-persian-nu-latn", {
        timeZone,
        year: "numeric",
        month: "2-digit",
        day: "2-digit",
        hour: "2-digit",
        minute: "2-digit",
        second: "2-digit",
        hourCycle: "h23",
    });
    formatterCache.set(timeZone, created);

    return created;
}

export function formatJalali(iso: string, timeZone: string): string | null {
    const date = new Date(iso);

    if (Number.isNaN(date.getTime())) {
        return null;
    }

    let parts: Intl.DateTimeFormatPart[];

    try {
        parts = formatter(timeZone).formatToParts(date);
    } catch {
        return null;
    }
    const value = (type: Intl.DateTimeFormatPartTypes) =>
        parts.find((part) => part.type === type)?.value ?? "";
    const year = value("year");
    const month = value("month");
    const day = value("day");
    const hour = value("hour");
    const minute = value("minute");
    const second = value("second");

    if (!year || !month || !day) {
        return null;
    }

    return `${year}/${month}/${day} ${hour}:${minute}:${second}`;
}
