import type { ReactNode } from "react";

export function TableFrame({ children }: { children: ReactNode }) {
    return (
        <div className="overflow-x-auto rounded-xl border border-line bg-surface">
            <table className="w-full min-w-[40rem] border-collapse text-sm">
                {children}
            </table>
        </div>
    );
}
