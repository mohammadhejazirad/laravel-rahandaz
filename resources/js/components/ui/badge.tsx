import type { HTMLAttributes } from "react";
import { cn } from "@/lib/cn";

const tones = {
    neutral: "border-line bg-surface-muted text-ink",
    success: "border-success/40 text-success",
    warning: "border-warning/40 text-warning",
    danger: "border-danger/40 text-danger",
    info: "border-info/40 text-info",
};

export function Badge({
    tone = "neutral",
    className,
    ...props
}: HTMLAttributes<HTMLSpanElement> & { tone?: keyof typeof tones }) {
    return (
        <span
            className={cn(
                "inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-xs font-medium",
                tones[tone],
                className,
            )}
            {...props}
        />
    );
}
