import { Inbox } from "lucide-react";
import type { ReactNode } from "react";

export function EmptyState({
    title,
    children,
}: {
    title: string;
    children: ReactNode;
}) {
    return (
        <div className="surface-shadow rounded-2xl border border-dashed border-line bg-surface/90 px-5 py-10 text-center">
            <span className="mx-auto grid size-11 place-items-center rounded-xl bg-surface-muted text-muted" aria-hidden="true">
                <Inbox className="size-5" />
            </span>
            <h2 className="mt-4 break-words text-base font-semibold">{title}</h2>
            <p className="mt-2 break-words text-sm text-muted">{children}</p>
        </div>
    );
}
