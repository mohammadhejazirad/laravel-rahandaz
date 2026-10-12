import type { ReactNode } from "react";

export function EmptyState({
    title,
    children,
}: {
    title: string;
    children: ReactNode;
}) {
    return (
        <div className="rounded-xl border border-dashed border-line bg-surface px-4 py-8 text-center">
            <h2 className="text-base font-semibold">{title}</h2>
            <p className="mt-2 text-sm text-muted">{children}</p>
        </div>
    );
}
