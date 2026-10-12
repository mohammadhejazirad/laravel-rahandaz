export function labelOf(value: string, labels: Record<string, string>): string {
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
