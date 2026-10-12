import { useId, type ReactNode } from "react";

export function TableFrame({ children, label = "جدول داده" }: { children: ReactNode; label?: string }) {
    const hintId = useId();

    return (
        <div
            data-slot="table-container"
            className="w-full min-w-0 max-w-full overflow-x-auto rounded-2xl border border-line bg-surface/96 shadow-[0_1px_2px_color-mix(in_srgb,var(--color-ink)_4%,transparent)] focus:outline-none focus:ring-2 focus:ring-accent focus:ring-offset-2 focus:ring-offset-canvas"
            role="region"
            aria-label={label}
            aria-describedby={hintId}
            tabIndex={0}
        >
            <span id={hintId} className="sr-only">در نمایشگر کوچک می‌توانید جدول را به‌صورت افقی پیمایش کنید.</span>
            <table data-slot="table" className="w-full min-w-[44rem] border-collapse text-sm [&_tbody_tr]:transition-colors [&_tbody_tr:hover]:bg-surface-muted/55 [&_td]:align-middle [&_th]:whitespace-nowrap">
                {children}
            </table>
        </div>
    );
}
