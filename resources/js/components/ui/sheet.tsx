import * as SheetPrimitive from "@animate-ui/react/primitives/radix-ui/dialog";
import { X } from "lucide-react";
import type { ComponentProps } from "react";
import { cn } from "@/lib/cn";

function Sheet(props: ComponentProps<typeof SheetPrimitive.Root>) {
    return <SheetPrimitive.Root data-slot="sheet" {...props} />;
}

function SheetTrigger(props: ComponentProps<typeof SheetPrimitive.Trigger>) {
    return <SheetPrimitive.Trigger data-slot="sheet-trigger" {...props} />;
}

function SheetOverlay({
    className,
    ...props
}: ComponentProps<typeof SheetPrimitive.Overlay>) {
    return (
        <SheetPrimitive.Overlay
            data-slot="sheet-overlay"
            className={cn(
                "fixed inset-0 z-50 bg-slate-950/55 backdrop-blur-sm",
                className,
            )}
            {...props}
        />
    );
}

function SheetContent({
    className,
    children,
    side = "right",
    showCloseButton = true,
    ...props
}: ComponentProps<typeof SheetPrimitive.Content> & {
    side?: "right" | "left";
    showCloseButton?: boolean;
}) {
    return (
        <SheetPrimitive.Portal>
            <SheetOverlay />
            <SheetPrimitive.Content
                data-slot="sheet-content"
                data-side={side}
                dir="rtl"
                className={cn(
                    "fixed inset-y-0 z-50 flex w-[min(86vw,340px)] max-w-full flex-col border-line bg-surface text-ink shadow-2xl outline-none",
                    side === "right" ? "right-0 border-s" : "left-0 border-e",
                    className,
                )}
                {...props}
                aria-modal="true"
            >
                {children}
                {showCloseButton ? (
                    <SheetPrimitive.Close
                        className="absolute end-3 top-3 grid size-11 place-items-center rounded-xl border border-line bg-surface text-muted hover:bg-surface-muted hover:text-ink"
                        aria-label="بستن منو"
                    >
                        <X className="size-4" aria-hidden="true" />
                    </SheetPrimitive.Close>
                ) : null}
            </SheetPrimitive.Content>
        </SheetPrimitive.Portal>
    );
}

function SheetHeader({ className, ...props }: ComponentProps<"div">) {
    return (
        <div
            data-slot="sheet-header"
            className={cn("border-b border-line p-4 pe-16", className)}
            {...props}
        />
    );
}

function SheetTitle({
    className,
    ...props
}: ComponentProps<typeof SheetPrimitive.Title>) {
    return (
        <SheetPrimitive.Title
            data-slot="sheet-title"
            className={cn("text-base font-semibold text-ink", className)}
            {...props}
        />
    );
}

function SheetDescription({
    className,
    ...props
}: ComponentProps<typeof SheetPrimitive.Description>) {
    return (
        <SheetPrimitive.Description
            data-slot="sheet-description"
            className={cn("mt-1 text-sm text-muted", className)}
            {...props}
        />
    );
}

export {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
};
