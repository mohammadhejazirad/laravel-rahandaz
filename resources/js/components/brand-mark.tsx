import { Terminal } from "lucide-react";
import { cn } from "@/lib/cn";

export function BrandMark({ compact = false, className }: { compact?: boolean; className?: string }) {
    return (
        <div className={cn("inline-flex items-center gap-3", className)}>
            <span
                className="relative grid size-10 shrink-0 place-items-center overflow-hidden rounded-xl bg-accent text-accent-foreground shadow-sm"
                aria-hidden="true"
            >
                <span className="absolute inset-x-1.5 top-1.5 h-px bg-current opacity-40" />
                <Terminal className="size-5" strokeWidth={2.2} />
            </span>
            {compact ? null : (
                <span className="leading-tight">
                    <span className="block text-lg font-bold text-ink">راه‌انداز</span>
                    <span className="block text-[11px] font-medium text-muted">کنترل‌پلین سرور محلی</span>
                </span>
            )}
        </div>
    );
}
