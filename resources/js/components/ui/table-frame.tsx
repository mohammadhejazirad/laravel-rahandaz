import { useId, type ReactNode } from "react";

export function TableFrame({ children, label = "جدول داده" }: { children: ReactNode; label?: string }) {
    const hintId = useId();

    return (
        <div
            className="surface-shadow overflow-x-auto rounded-2xl border border-line bg-surface/95 focus:outline-none focus:ring-2 focus:ring-accent"
            role="region"
            aria-label={label}
            aria-describedby={hintId}
            tabIndex={0}
        >
            <span id={hintId} className="sr-only">در نمایشگر کوچک می‌توانید جدول را به‌صورت افقی پیمایش کنید.</span>
            <table className="w-full min-w-[40rem] border-collapse text-sm [&_tbody_tr]:transition-colors [&_tbody_tr:hover]:bg-surface-muted/60">
                {children}
            </table>
        </div>
    );
}
