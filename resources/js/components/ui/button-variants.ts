import { cva } from "class-variance-authority";

export const buttonVariants = cva(
    "inline-flex shrink-0 items-center justify-center gap-2 rounded-xl text-sm font-semibold transition-[color,background-color,border-color,box-shadow,opacity,transform] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 focus-visible:ring-offset-canvas disabled:pointer-events-none disabled:opacity-50 active:translate-y-px [&_svg]:pointer-events-none [&_svg]:shrink-0",
    {
        variants: {
            variant: {
                primary:
                    "bg-accent text-accent-foreground shadow-sm hover:brightness-95",
                secondary:
                    "border border-line bg-surface text-ink shadow-sm hover:border-accent/35 hover:bg-surface-muted",
                ghost: "text-muted hover:bg-surface-muted hover:text-ink",
                danger: "bg-danger text-white shadow-sm hover:brightness-95",
            },
            size: {
                default: "min-h-11 px-4 py-2",
                sm: "min-h-10 rounded-lg px-3 py-1.5 text-xs",
                icon: "size-11 p-0",
            },
        },
        defaultVariants: {
            variant: "primary",
            size: "default",
        },
    },
);
