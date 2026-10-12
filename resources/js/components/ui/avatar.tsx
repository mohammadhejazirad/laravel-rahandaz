import * as AvatarPrimitive from "@radix-ui/react-avatar";
import type { ComponentProps } from "react";
import { cn } from "@/lib/cn";

function Avatar({
    className,
    size = "default",
    ...props
}: ComponentProps<typeof AvatarPrimitive.Root> & {
    size?: "default" | "sm" | "lg";
}) {
    return (
        <AvatarPrimitive.Root
            data-slot="avatar"
            data-size={size}
            className={cn(
                "group/avatar relative flex size-9 shrink-0 overflow-hidden rounded-xl border border-line bg-surface-muted select-none data-[size=lg]:size-11 data-[size=sm]:size-7",
                className,
            )}
            {...props}
        />
    );
}

function AvatarFallback({
    className,
    ...props
}: ComponentProps<typeof AvatarPrimitive.Fallback>) {
    return (
        <AvatarPrimitive.Fallback
            data-slot="avatar-fallback"
            className={cn(
                "flex size-full items-center justify-center bg-accent-soft text-sm font-bold text-accent group-data-[size=sm]/avatar:text-xs",
                className,
            )}
            {...props}
        />
    );
}

export { Avatar, AvatarFallback };
