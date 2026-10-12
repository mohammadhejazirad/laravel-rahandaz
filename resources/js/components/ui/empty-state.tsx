import { Inbox, type LucideIcon } from "lucide-react";
import type { ReactNode } from "react";

export function EmptyState({
    title,
    children,
    icon: Icon = Inbox,
    action,
}: {
    title: string;
    children: ReactNode;
    icon?: LucideIcon;
    action?: ReactNode;
}) {
    return (
        <div data-slot="empty-state" className="rounded-2xl border border-dashed border-line bg-surface/75 px-5 py-12 text-center">
            <span className="mx-auto grid size-12 place-items-center rounded-2xl border border-line bg-surface-muted text-muted shadow-sm" aria-hidden="true">
                <Icon className="size-5" />
            </span>
            <h2 className="mt-4 break-words text-base font-semibold">{title}</h2>
            <p className="mx-auto mt-2 max-w-xl break-words text-sm leading-7 text-muted">{children}</p>
            {action ? <div className="mt-5 flex justify-center">{action}</div> : null}
        </div>
    );
}
