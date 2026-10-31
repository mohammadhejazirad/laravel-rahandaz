import type { VariantProps } from "class-variance-authority";
import { LoaderCircle } from "lucide-react";
import { cloneElement, isValidElement, type ButtonHTMLAttributes, type ReactElement, type ReactNode, type MouseEvent, type Ref } from "react";
import { Slot } from "@radix-ui/react-slot";
import { buttonVariants } from "@/components/ui/button-variants";
import { cn } from "@/lib/cn";

export type ButtonProps = ButtonHTMLAttributes<HTMLButtonElement> & VariantProps<typeof buttonVariants> & {
    ref?: Ref<HTMLButtonElement>;
    asChild?: boolean;
    loading?: boolean;
    loadingLabel?: string;
};

export function Button({
    asChild = false,
    children,
    className,
    variant,
    size,
    type = "button",
    loading = false,
    loadingLabel = "در حال انجام",
    disabled,
    onClickCapture,
    ...props
}: ButtonProps) {
    const classes = cn(
        buttonVariants({ variant, size }),
        asChild && (disabled || loading) && "pointer-events-none opacity-50",
        className,
    );
    const content = loading ? <><LoaderCircle className="size-4 shrink-0 motion-safe:animate-spin" aria-hidden="true" /><span className={size === 'icon' ? 'sr-only' : undefined}>{loadingLabel}</span></> : children;

    if (asChild) {
        const child = isValidElement(children) ? children as ReactElement<{ children?: ReactNode; onClickCapture?: ButtonProps['onClickCapture'] }> : null;
        return (
            <Slot
                data-slot="button"
                className={classes}
                aria-disabled={disabled || loading ? true : undefined}
                aria-busy={loading || undefined}
                {...props}
                tabIndex={disabled || loading ? -1 : props.tabIndex}
                onClickCapture={event => {
                    if (disabled || loading) { event.preventDefault(); event.stopPropagation(); return; }
                    child?.props.onClickCapture?.(event as MouseEvent<HTMLButtonElement>);
                    onClickCapture?.(event as MouseEvent<HTMLButtonElement>);
                }}
            >
                {child ? cloneElement(child, { onClickCapture: undefined }, loading ? content : child.props.children) : children}
            </Slot>
        );
    }

    return (
        <button
            data-slot="button"
            type={type}
            className={classes}
            disabled={disabled || loading}
            aria-busy={loading || undefined}
            {...props}
            onClickCapture={onClickCapture}
        >
            {content}
        </button>
    );
}
