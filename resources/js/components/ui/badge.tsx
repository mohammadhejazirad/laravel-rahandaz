import type { HTMLAttributes } from "react";
import { cn } from "@/lib/cn";

const tones = {
    neutral: "border-line bg-surface-muted text-ink",
    success: "border-success/35 bg-success-soft text-success",
    warning: "border-warning/35 bg-warning-soft text-warning",
    danger: "border-danger/35 bg-danger-soft text-danger",
    info: "border-info/35 bg-info-soft text-info",
};

export function Badge({
    tone = "neutral",
    className,
    ...props
}: HTMLAttributes<HTMLSpanElement> & { tone?: keyof typeof tones }) {
    return (
        <span
            data-slot="badge"
            className={cn(
                "inline-flex min-h-6 items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-semibold leading-none",
                tones[tone],
                className,
            )}
            {...props}
        />
    );
}
