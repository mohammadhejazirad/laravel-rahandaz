import * as DropdownMenuPrimitive from "@radix-ui/react-dropdown-menu";
import { Check } from "lucide-react";
import type { ComponentProps } from "react";
import { cn } from "@/lib/cn";

function DropdownMenu(
    props: ComponentProps<typeof DropdownMenuPrimitive.Root>,
) {
    return <DropdownMenuPrimitive.Root data-slot="dropdown-menu" {...props} />;
}

function DropdownMenuTrigger(
    props: ComponentProps<typeof DropdownMenuPrimitive.Trigger>,
) {
    return (
        <DropdownMenuPrimitive.Trigger
            data-slot="dropdown-menu-trigger"
            {...props}
        />
    );
}

function DropdownMenuContent({
    className,
    align = "start",
    sideOffset = 8,
    ...props
}: ComponentProps<typeof DropdownMenuPrimitive.Content>) {
    return (
        <DropdownMenuPrimitive.Portal>
            <DropdownMenuPrimitive.Content
                data-slot="dropdown-menu-content"
                align={align}
                sideOffset={sideOffset}
                className={cn(
                    "z-[75] min-w-52 overflow-hidden rounded-xl border border-line bg-surface-raised p-1.5 text-ink shadow-xl outline-none",
                    className,
                )}
                {...props}
            />
        </DropdownMenuPrimitive.Portal>
    );
}

function DropdownMenuGroup(
    props: ComponentProps<typeof DropdownMenuPrimitive.Group>,
) {
    return <DropdownMenuPrimitive.Group data-slot="dropdown-menu-group" {...props} />;
}

function DropdownMenuLabel({
    className,
    ...props
}: ComponentProps<typeof DropdownMenuPrimitive.Label>) {
    return (
        <DropdownMenuPrimitive.Label
            data-slot="dropdown-menu-label"
            className={cn("px-3 py-2 text-xs font-medium text-muted", className)}
            {...props}
        />
    );
}

function DropdownMenuItem({
    className,
    variant = "default",
    ...props
}: ComponentProps<typeof DropdownMenuPrimitive.Item> & {
    variant?: "default" | "destructive";
}) {
    return (
        <DropdownMenuPrimitive.Item
            data-slot="dropdown-menu-item"
            data-variant={variant}
            className={cn(
                "flex min-h-10 cursor-pointer select-none items-center gap-2 rounded-lg px-3 py-2 text-sm outline-none data-[disabled]:pointer-events-none data-[disabled]:cursor-not-allowed data-[disabled]:opacity-50 [&_svg]:size-4 [&_svg]:shrink-0",
                variant === "destructive"
                    ? "text-danger focus:bg-danger-soft"
                    : "text-ink focus:bg-surface-muted",
                className,
            )}
            {...props}
        />
    );
}

function DropdownMenuRadioGroup(
    props: ComponentProps<typeof DropdownMenuPrimitive.RadioGroup>,
) {
    return (
        <DropdownMenuPrimitive.RadioGroup
            data-slot="dropdown-menu-radio-group"
            {...props}
        />
    );
}

function DropdownMenuRadioItem({
    className,
    children,
    ...props
}: ComponentProps<typeof DropdownMenuPrimitive.RadioItem>) {
    return (
        <DropdownMenuPrimitive.RadioItem
            data-slot="dropdown-menu-radio-item"
            className={cn(
                "flex min-h-10 cursor-pointer select-none items-center gap-2 rounded-lg px-3 py-2 text-sm text-ink outline-none focus:bg-surface-muted data-[disabled]:pointer-events-none data-[disabled]:cursor-not-allowed data-[disabled]:opacity-50 [&_svg]:size-4 [&_svg]:shrink-0",
                className,
            )}
            {...props}
        >
            {children}
            <span className="ms-auto grid size-4 place-items-center text-accent">
                <DropdownMenuPrimitive.ItemIndicator>
                    <Check aria-hidden="true" />
                </DropdownMenuPrimitive.ItemIndicator>
            </span>
        </DropdownMenuPrimitive.RadioItem>
    );
}

function DropdownMenuSeparator({
    className,
    ...props
}: ComponentProps<typeof DropdownMenuPrimitive.Separator>) {
    return (
        <DropdownMenuPrimitive.Separator
            data-slot="dropdown-menu-separator"
            className={cn("-mx-1 my-1 h-px bg-line", className)}
            {...props}
        />
    );
}

export {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
};
