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
    const Component = asChild ? Slot : "button";

    return (
        <Component
            data-slot="button"
            type={asChild ? undefined : type}
            className={cn(buttonVariants({ variant, size }), className)}
            disabled={asChild ? undefined : disabled || loading}
            aria-disabled={asChild && (disabled || loading) ? true : undefined}
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
        </Component>
    );
}
