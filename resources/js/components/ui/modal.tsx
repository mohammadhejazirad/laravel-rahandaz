import * as DialogPrimitive from "@radix-ui/react-dialog";
import { X } from "lucide-react";
import type { ComponentProps } from "react";
import { cn } from "@/lib/cn";

function Modal(props: ComponentProps<typeof DialogPrimitive.Root>) {
    return <DialogPrimitive.Root data-slot="modal" {...props} />;
}

function ModalTrigger(props: ComponentProps<typeof DialogPrimitive.Trigger>) {
    return <DialogPrimitive.Trigger data-slot="modal-trigger" {...props} />;
}

function ModalClose(props: ComponentProps<typeof DialogPrimitive.Close>) {
    return <DialogPrimitive.Close data-slot="modal-close" {...props} />;
}

function ModalOverlay({
    className,
    ...props
}: ComponentProps<typeof DialogPrimitive.Overlay>) {
    return (
        <DialogPrimitive.Overlay
            data-slot="modal-overlay"
            className={cn(
                "fixed inset-0 z-[65] bg-slate-950/60 backdrop-blur-sm data-[state=closed]:opacity-0 data-[state=open]:opacity-100 motion-safe:transition-opacity",
                className,
            )}
            {...props}
        />
    );
}

function ModalContent({
    className,
    children,
    showCloseButton = true,
    ...props
}: ComponentProps<typeof DialogPrimitive.Content> & {
    showCloseButton?: boolean;
}) {
    return (
        <DialogPrimitive.Portal>
            <ModalOverlay />
            <DialogPrimitive.Content
                data-slot="modal-content"
                dir="rtl"
                className={cn(
                    "surface-shadow fixed left-1/2 top-1/2 z-[70] w-[calc(100%-2rem)] max-w-lg -translate-x-1/2 -translate-y-1/2 rounded-[1.75rem] border border-line bg-surface-raised p-5 text-ink outline-none data-[state=closed]:scale-95 data-[state=closed]:opacity-0 data-[state=open]:scale-100 data-[state=open]:opacity-100 motion-safe:transition-[opacity,transform] sm:p-6",
                    className,
                )}
                {...props}
                aria-modal="true"
            >
                {children}
                {showCloseButton ? (
                    <DialogPrimitive.Close
                        className="absolute end-4 top-4 grid size-10 cursor-pointer place-items-center rounded-xl text-muted transition-colors hover:bg-surface-muted hover:text-ink focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent"
                        aria-label="بستن پنجره"
                    >
                        <X className="size-4" aria-hidden="true" />
                    </DialogPrimitive.Close>
                ) : null}
            </DialogPrimitive.Content>
        </DialogPrimitive.Portal>
    );
}

function ModalHeader({ className, ...props }: ComponentProps<"div">) {
    return (
        <div
            data-slot="modal-header"
            className={cn("space-y-2 pe-10", className)}
            {...props}
        />
    );
}

function ModalTitle({
    className,
    ...props
}: ComponentProps<typeof DialogPrimitive.Title>) {
    return (
        <DialogPrimitive.Title
            data-slot="modal-title"
            className={cn("text-lg font-bold leading-8 text-ink", className)}
            {...props}
        />
    );
}

function ModalDescription({
    className,
    ...props
}: ComponentProps<typeof DialogPrimitive.Description>) {
    return (
        <DialogPrimitive.Description
            data-slot="modal-description"
            className={cn("text-sm leading-7 text-muted", className)}
            {...props}
        />
    );
}

function ModalFooter({ className, ...props }: ComponentProps<"div">) {
    return (
        <div
            data-slot="modal-footer"
            className={cn(
                "mt-6 flex flex-col gap-2 border-t border-line pt-5 sm:flex-row sm:justify-end",
                className,
            )}
            {...props}
        />
    );
}

export {
    Modal,
    ModalClose,
    ModalContent,
    ModalDescription,
    ModalFooter,
    ModalHeader,
    ModalTitle,
    ModalTrigger,
};
