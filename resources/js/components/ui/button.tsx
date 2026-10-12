import type { VariantProps } from "class-variance-authority";
import { LoaderCircle } from "lucide-react";
import type { ButtonHTMLAttributes } from "react";
import { Slot } from "@radix-ui/react-slot";
import { buttonVariants } from "@/components/ui/button-variants";
import { cn } from "@/lib/cn";

type ButtonProps = ButtonHTMLAttributes<HTMLButtonElement> & VariantProps<typeof buttonVariants> & {
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
    ...props
}: ButtonProps) {
    const classes = cn(
        buttonVariants({ variant, size }),
        asChild && (disabled || loading) && "pointer-events-none opacity-50",
        className,
    );

    if (asChild) {
        return (
            <Slot
                data-slot="button"
                className={classes}
                aria-disabled={disabled || loading ? true : undefined}
                aria-busy={loading || undefined}
                {...props}
            >
                {children}
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
        >
            {loading ? (
                <LoaderCircle
                    className="size-4 animate-spin"
                    aria-hidden="true"
                />
            ) : null}
            {loading ? loadingLabel : children}
        </button>
    );
}
